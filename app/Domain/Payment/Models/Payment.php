<?php

declare(strict_types=1);

namespace App\Domain\Payment\Models;

use App\Domain\Payment\Enums\PaymentStatus;
use App\Support\Money\Money;
use App\Support\Uuid\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * Payment aggregate (PAY). Owns attempts, events, captures and refunds
 * (SDD §8.4). Refunded amount can never exceed captured (BR-005).
 *
 * @property string $public_id
 * @property PaymentStatus $status
 * @property string $amount
 * @property string $captured_amount
 * @property string $refunded_amount
 * @property string $currency
 */
final class Payment extends Model
{
    use HasPublicId;

    protected $table = 'payments';

    protected $fillable = [
        'public_id', 'order_id', 'method', 'provider', 'status', 'amount',
        'captured_amount', 'refunded_amount', 'currency', 'provider_reference',
        'lock_version', 'correlation_id',
    ];

    protected function casts(): array
    {
        return ['status' => PaymentStatus::class, 'lock_version' => 'integer'];
    }

    public function capturedMoney(): Money
    {
        return Money::fromDecimalString((string) $this->captured_amount, (string) $this->currency);
    }

    public function refundedMoney(): Money
    {
        return Money::fromDecimalString((string) $this->refunded_amount, (string) $this->currency);
    }

    /** Remaining amount eligible for refund (BR-005). */
    public function refundableMoney(): Money
    {
        return $this->capturedMoney()->subtract($this->refundedMoney());
    }
}
