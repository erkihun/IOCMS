<?php

declare(strict_types=1);

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Actions\ProcessPaymentWebhookAction;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use App\Platform\Integration\Adapters\FakePaymentGateway;
use App\Support\Exceptions\BusinessRuleException;
use App\Support\Uuid\PublicId;
use Illuminate\Support\Facades\DB;

/**
 * FR-PAY-005/006/007, BR-004/020, prompt §18 — signed, idempotent, order-safe
 * payment webhook processing. Test basis T-FR-PAY-007.
 */
beforeEach(fn () => $this->action = app(ProcessPaymentWebhookAction::class));

function makePendingOrderWithPayment(string $amount = '100.0000'): Payment
{
    $order = Order::query()->create([
        'public_id' => PublicId::generate(),
        'order_number' => 'ORD-TEST-'.random_int(1000, 9999),
        'status' => OrderStatus::PendingPayment->value,
        'currency' => 'ETB',
        'grand_total' => $amount,
        'lock_version' => 0,
    ]);

    return Payment::query()->create([
        'public_id' => PublicId::generate(),
        'order_id' => $order->id,
        'method' => 'online',
        'provider' => 'fake',
        'status' => PaymentStatus::Pending->value,
        'amount' => $amount,
        'currency' => 'ETB',
        'provider_reference' => 'fake_ref_'.$order->id,
    ]);
}

function captureWebhook(Payment $payment, string $eventId = 'evt-1'): array
{
    return FakePaymentGateway::signWebhook([
        'event_id' => $eventId,
        'type' => 'payment.captured',
        'provider_reference' => $payment->provider_reference,
        'amount' => $payment->amount,
        'currency' => 'ETB',
    ]);
}

it('applies a signed capture, confirms the order and posts the ledger', function () {
    $payment = makePendingOrderWithPayment();
    $wh = captureWebhook($payment);

    $result = $this->action->execute($wh['body'], $wh['headers']);

    expect($result['status'])->toBe('applied');
    expect($payment->fresh()->status)->toBe(PaymentStatus::Captured);
    expect(Order::query()->find($payment->order_id)->status)->toBe(OrderStatus::Confirmed);
    expect(DB::table('financial_ledger_entries')->where('account', 'sales')->count())->toBe(1);
    expect(DB::table('outbox_events')->where('event_name', 'PaymentCaptured')->count())->toBe(1);
});

it('rejects an invalid webhook signature (BR-004: no unverified evidence)', function () {
    $payment = makePendingOrderWithPayment();
    $wh = captureWebhook($payment);
    $wh['headers']['x-fake-signature'] = 'tampered';

    expect(fn () => $this->action->execute($wh['body'], $wh['headers']))
        ->toThrow(BusinessRuleException::class);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending); // unchanged
});

it('processes a duplicate callback idempotently (BR-020)', function () {
    $payment = makePendingOrderWithPayment();
    $wh = captureWebhook($payment, 'evt-dup');

    $first = $this->action->execute($wh['body'], $wh['headers']);
    $second = $this->action->execute($wh['body'], $wh['headers']);

    expect($first['status'])->toBe('applied')
        ->and($second['status'])->toBe('duplicate');
    // Only one ledger entry despite two deliveries.
    expect(DB::table('financial_ledger_entries')->count())->toBe(1);
    expect(Payment::query()->find($payment->id)->status)->toBe(PaymentStatus::Captured);
});

it('holds an out-of-order refund event as pending rather than forcing an invalid transition', function () {
    $payment = makePendingOrderWithPayment();
    // A refund event arrives while payment is still Pending (out of order).
    $wh = FakePaymentGateway::signWebhook([
        'event_id' => 'evt-refund-early',
        'type' => 'payment.refunded',
        'provider_reference' => $payment->provider_reference,
        'amount' => '100.0000',
        'currency' => 'ETB',
    ]);

    $result = $this->action->execute($wh['body'], $wh['headers']);

    expect($result['status'])->toBe('pending');
    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending); // not forced
    expect(DB::table('payment_events')->where('processing_status', 'pending')->count())->toBe(1);
});

it('flags an amount mismatch as pending for inquiry (FR-PAY-006)', function () {
    $payment = makePendingOrderWithPayment('100.0000');
    $wh = FakePaymentGateway::signWebhook([
        'event_id' => 'evt-wrong-amt',
        'type' => 'payment.captured',
        'provider_reference' => $payment->provider_reference,
        'amount' => '99.0000', // mismatch
        'currency' => 'ETB',
    ]);

    $result = $this->action->execute($wh['body'], $wh['headers']);

    expect($result['status'])->toBe('amount_mismatch');
    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('stores an unknown-reference event for diagnosis without altering state (BR-048)', function () {
    $wh = FakePaymentGateway::signWebhook([
        'event_id' => 'evt-orphan',
        'type' => 'payment.captured',
        'provider_reference' => 'fake_ref_does_not_exist',
        'amount' => '10.0000',
        'currency' => 'ETB',
    ]);

    $result = $this->action->execute($wh['body'], $wh['headers']);

    expect($result['status'])->toBe('unknown');
    expect(DB::table('payment_events')->where('processing_status', 'unknown')->count())->toBe(1);
});
