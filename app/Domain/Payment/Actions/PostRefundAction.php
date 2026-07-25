<?php

declare(strict_types=1);

namespace App\Domain\Payment\Actions;

use App\Domain\Governance\Services\AuditRecorder;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use App\Platform\Integration\Services\OutboxRecorder;
use App\Support\Clock\Clock;
use App\Support\Correlation\CorrelationContext;
use App\Support\Exceptions\BusinessRuleException;
use App\Support\Money\Money;
use App\Support\Uuid\PublicId;
use Illuminate\Support\Facades\DB;

/**
 * Posts a refund against a captured payment (FR-PAY-009/010, BR-005/039).
 *
 * Double-refund prevention (prompt §18): the cumulative refunded amount can
 * never exceed the captured amount less prior settled refunds. The check runs
 * under a row lock on the payment so concurrent refunds cannot both pass. A
 * compensating financial ledger entry is posted and the payment transitions to
 * PartiallyRefunded/Refunded. Refund evidence (reason, actor, linked return) is
 * preserved (BR-039). A high-value refund additionally requires maker-checker
 * approval upstream (BR-024, enforced by policy/approval workflow).
 */
final class PostRefundAction
{
    public function __construct(
        private readonly OutboxRecorder $outbox,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(
        string $paymentPublicId,
        Money $amount,
        string $reason,
        ?int $makerId = null,
        ?string $returnReference = null,
    ): string {
        if (! $amount->isPositive()) {
            throw new BusinessRuleException('refund.exceeds_eligible_amount', 'Refund amount must be positive.');
        }

        return DB::transaction(function () use ($paymentPublicId, $amount, $reason, $makerId, $returnReference): string {
            /** @var Payment $payment */
            $payment = Payment::query()->where('public_id', $paymentPublicId)->lockForUpdate()->firstOrFail();

            if (! $payment->status->isSettled()) {
                throw new BusinessRuleException('payment.invalid_transition', 'Only captured payments can be refunded.');
            }

            $refundable = $payment->refundableMoney();
            if ($amount->greaterThan($refundable)) {
                // BR-005: cannot refund more than captured − prior refunds.
                throw new BusinessRuleException(
                    'refund.exceeds_eligible_amount',
                    'Requested refund exceeds the remaining eligible amount.',
                );
            }

            $newRefunded = $payment->refundedMoney()->add($amount);
            $refundPublicId = PublicId::generate();

            DB::table('refunds')->insert([
                'public_id' => $refundPublicId,
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'status' => 'settled',
                'amount' => $amount->toDecimalString(),
                'currency' => $payment->currency,
                'reason' => $reason,
                'maker_id' => $makerId,
                'return_reference' => $returnReference,
                'created_at' => Clock::now(),
                'updated_at' => Clock::now(),
            ]);

            $refundId = DB::table('refunds')->where('public_id', $refundPublicId)->value('id');
            DB::table('refund_events')->insert([
                'refund_id' => $refundId,
                'event' => 'settled',
                'detail' => json_encode(['amount' => $amount->toDecimalString(), 'reason' => $reason], JSON_THROW_ON_ERROR),
                'created_at' => Clock::now(),
            ]);

            // Update payment refunded total + status.
            $fullyRefunded = $newRefunded->equals($payment->capturedMoney());
            $payment->refunded_amount = $newRefunded->toDecimalString();
            $payment->status = $fullyRefunded ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded;
            $payment->lock_version += 1;
            $payment->save();

            // Compensating ledger entry (append-only, FR-PAY-014).
            DB::table('financial_ledger_entries')->insert([
                'public_id' => PublicId::generate(),
                'account' => 'refunds',
                'entry_type' => 'debit',
                'amount' => $amount->toDecimalString(),
                'currency' => $payment->currency,
                'source_type' => 'refund',
                'source_reference' => $refundPublicId,
                'correlation_id' => CorrelationContext::id(),
                'posted_at' => Clock::now(),
            ]);

            $this->audit->record(
                action: 'payment.refund_posted',
                resourceType: 'payment',
                resourcePublicId: $payment->public_id,
                after: ['refund_amount' => $amount->toDecimalString(), 'reason' => $reason, 'return_reference' => $returnReference],
            );

            $this->outbox->record(
                eventName: 'RefundPosted',
                aggregateType: 'payment',
                aggregatePublicId: $payment->public_id,
                payload: ['refund_public_id' => $refundPublicId, 'amount' => $amount->toDecimalString()],
                aggregateVersion: $payment->lock_version,
            );

            return $refundPublicId;
        });
    }
}
