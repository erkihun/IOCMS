<?php

declare(strict_types=1);

namespace App\Domain\Payment\Actions;

use App\Domain\Governance\Services\AuditRecorder;
use App\Domain\Order\Actions\TransitionOrderStatusAction;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use App\Platform\Integration\Ports\PaymentGateway;
use App\Platform\Integration\Services\OutboxRecorder;
use App\Support\Clock\Clock;
use App\Support\Correlation\CorrelationContext;
use App\Support\Money\Money;
use App\Support\Uuid\PublicId;
use Illuminate\Support\Facades\DB;

/**
 * Processes an authenticated payment webhook (FR-PAY-005/006/007, BR-004/020,
 * LLD §6.2 / Figure 7). Idempotent and safe under duplication and reordering:
 *
 *  - Signature + replay window verified by the provider adapter (invalid → reject).
 *  - The provider event is recorded once in `payment_events` (unique per provider
 *    + event id) — a duplicate is acknowledged without re-applying (BR-020).
 *  - Amount/currency are validated against the payment obligation before applying.
 *  - A state transition is applied only if valid for the current payment state;
 *    otherwise the event is stored as `pending`/`unknown` for inquiry, never
 *    forcing an invalid transition (out-of-order safety, FR-PAY-outcome_ambiguous).
 *  - On capture, the linked order advances PendingPayment → Confirmed and an
 *    outbox event fires — all in one transaction.
 *
 * A browser redirect never reaches this path (BR-004).
 */
final class ProcessPaymentWebhookAction
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly OutboxRecorder $outbox,
        private readonly AuditRecorder $audit,
        private readonly TransitionOrderStatusAction $transitionOrder,
    ) {}

    /**
     * @param  array<string, string>  $headers
     * @return array{status: string, event_id: string}
     */
    public function execute(string $rawBody, array $headers): array
    {
        // Signature + replay validation (throws integration.signature_invalid).
        $event = $this->gateway->verifyWebhook($rawBody, $headers);

        return DB::transaction(function () use ($event, $rawBody): array {
            // Inbox dedup: one recorded event per (provider, event id).
            $inserted = DB::table('payment_events')->insertOrIgnore([
                'public_id' => PublicId::generate(),
                'provider' => $this->gateway->name(),
                'provider_event_id' => $event['provider_event_id'],
                'event_type' => $event['event_type'],
                'amount' => $event['amount'],
                'currency' => $event['currency'],
                'payload' => $this->redactedPayload($rawBody),
                'signature_status' => 'verified',
                'processing_status' => 'pending',
                'created_at' => Clock::now(),
                'updated_at' => Clock::now(),
            ]);

            if ($inserted === 0) {
                // Duplicate event — safe acknowledgement, no re-apply (BR-020).
                return ['status' => 'duplicate', 'event_id' => $event['provider_event_id']];
            }

            $payment = Payment::query()
                ->where('provider_reference', $event['provider_reference'])
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                // Unknown/pending — retained for diagnosis (BR-048), not applied.
                $this->markEvent($event['provider_event_id'], 'unknown', null);

                return ['status' => 'unknown', 'event_id' => $event['provider_event_id']];
            }

            $targetStatus = $this->mapEventType($event['event_type']);
            if ($targetStatus === null || ! $payment->status->canTransitionTo($targetStatus)) {
                // Out-of-order or not applicable yet — leave pending for inquiry.
                $this->markEvent($event['provider_event_id'], 'pending', $payment->id);

                return ['status' => 'pending', 'event_id' => $event['provider_event_id']];
            }

            // Validate amount/currency for capture (FR-PAY-006).
            if ($targetStatus === PaymentStatus::Captured) {
                $eventAmount = Money::fromDecimalString((string) $event['amount'], (string) $event['currency']);
                $obligation = Money::fromDecimalString((string) $payment->amount, (string) $payment->currency);
                if (! $eventAmount->equals($obligation)) {
                    $this->markEvent($event['provider_event_id'], 'pending', $payment->id);

                    return ['status' => 'amount_mismatch', 'event_id' => $event['provider_event_id']];
                }
                $payment->captured_amount = $eventAmount->toDecimalString();
            }

            $from = $payment->status;
            $payment->status = $targetStatus;
            $payment->lock_version += 1;
            $payment->save();

            $this->markEvent($event['provider_event_id'], 'applied', $payment->id, $targetStatus->value);

            $this->audit->record(
                action: 'payment.status_changed',
                resourceType: 'payment',
                resourcePublicId: $payment->public_id,
                before: ['status' => $from->value],
                after: ['status' => $targetStatus->value],
            );

            // On capture, confirm the order (independent state machines, BR-003).
            if ($targetStatus === PaymentStatus::Captured) {
                $this->postLedger($payment);
                $order = Order::query()->find($payment->order_id);
                if ($order !== null && $order->status === OrderStatus::PendingPayment) {
                    $this->transitionOrder->execute($order, OrderStatus::Confirmed, $order->lock_version, 'payment_captured');
                }

                $this->outbox->record(
                    eventName: 'PaymentCaptured',
                    aggregateType: 'payment',
                    aggregatePublicId: $payment->public_id,
                    payload: ['order_id' => $payment->order_id, 'amount' => $payment->captured_amount],
                );
            }

            return ['status' => 'applied', 'event_id' => $event['provider_event_id']];
        });
    }

    private function mapEventType(string $type): ?PaymentStatus
    {
        return match ($type) {
            'payment.authorized' => PaymentStatus::Authorized,
            'payment.captured', 'payment.succeeded' => PaymentStatus::Captured,
            'payment.failed' => PaymentStatus::Failed,
            'payment.cancelled' => PaymentStatus::Cancelled,
            default => null,
        };
    }

    private function markEvent(string $eventId, string $processing, ?int $paymentId, ?string $statusApplied = null): void
    {
        DB::table('payment_events')
            ->where('provider', $this->gateway->name())
            ->where('provider_event_id', $eventId)
            ->update([
                'processing_status' => $processing,
                'payment_id' => $paymentId,
                'status_applied' => $statusApplied,
                'updated_at' => Clock::now(),
            ]);
    }

    private function postLedger(Payment $payment): void
    {
        DB::table('financial_ledger_entries')->insert([
            'public_id' => PublicId::generate(),
            'account' => 'sales',
            'entry_type' => 'credit',
            'amount' => $payment->captured_amount,
            'currency' => $payment->currency,
            'source_type' => 'payment',
            'source_reference' => $payment->public_id,
            'correlation_id' => CorrelationContext::id(),
            'posted_at' => Clock::now(),
        ]);
    }

    private function redactedPayload(string $rawBody): string
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($rawBody, true) ?: [];
        foreach (['card_number', 'cvv', 'otp', 'token'] as $key) {
            if (isset($data[$key])) {
                $data[$key] = '[redacted]';
            }
        }

        return json_encode($data, JSON_THROW_ON_ERROR);
    }
}
