<?php

declare(strict_types=1);

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Actions\PostRefundAction;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use App\Support\Exceptions\BusinessRuleException;
use App\Support\Money\Money;
use App\Support\Uuid\PublicId;
use Illuminate\Support\Facades\DB;

/**
 * FR-PAY-009/010, BR-005 — refunds and double-refund prevention (prompt §18).
 */
beforeEach(fn () => $this->action = app(PostRefundAction::class));

function capturedPayment(string $captured = '100.0000'): Payment
{
    $order = Order::query()->create([
        'public_id' => PublicId::generate(),
        'order_number' => 'ORD-R-'.random_int(1000, 9999),
        'status' => OrderStatus::Confirmed->value,
        'currency' => 'ETB',
        'grand_total' => $captured,
        'lock_version' => 0,
    ]);

    return Payment::query()->create([
        'public_id' => PublicId::generate(),
        'order_id' => $order->id,
        'method' => 'online',
        'provider' => 'fake',
        'status' => PaymentStatus::Captured->value,
        'amount' => $captured,
        'captured_amount' => $captured,
        'refunded_amount' => '0.0000',
        'currency' => 'ETB',
    ]);
}

it('posts a partial refund and marks the payment partially refunded', function () {
    $payment = capturedPayment('100.0000');

    $this->action->execute($payment->public_id, Money::fromDecimalString('40.00', 'ETB'), 'damaged');

    $fresh = $payment->fresh();
    expect((float) $fresh->refunded_amount)->toBe(40.0)
        ->and($fresh->status)->toBe(PaymentStatus::PartiallyRefunded);
    expect(DB::table('financial_ledger_entries')->where('account', 'refunds')->count())->toBe(1);
});

it('marks the payment fully refunded when the captured amount is reached', function () {
    $payment = capturedPayment('100.0000');
    $this->action->execute($payment->public_id, Money::fromDecimalString('100.00', 'ETB'), 'return');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Refunded);
});

it('prevents cumulative refunds from exceeding the captured amount (BR-005)', function () {
    $payment = capturedPayment('100.0000');
    $this->action->execute($payment->public_id, Money::fromDecimalString('60.00', 'ETB'), 'partial-1');

    // A second refund of 60 would total 120 > 100 captured — must be rejected.
    expect(fn () => $this->action->execute($payment->public_id, Money::fromDecimalString('60.00', 'ETB'), 'partial-2'))
        ->toThrow(BusinessRuleException::class);

    expect((float) $payment->fresh()->refunded_amount)->toBe(60.0); // unchanged after failed attempt
});

it('rejects a refund on a non-captured payment', function () {
    $payment = capturedPayment('50.0000');
    $payment->update(['status' => PaymentStatus::Pending->value]);

    expect(fn () => $this->action->execute($payment->public_id, Money::fromDecimalString('10.00', 'ETB'), 'x'))
        ->toThrow(BusinessRuleException::class);
});
