<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Models;

use App\Support\Uuid\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * Coupon with usage limits and reservation counters for concurrency-safe
 * redemption (FR-PRI-005). used_count + reserved_count are updated via atomic
 * conditional updates so limits cannot be exceeded under concurrency.
 *
 * @property string $code
 * @property int|null $usage_limit
 * @property int $used_count
 * @property int $reserved_count
 */
final class Coupon extends Model
{
    use HasPublicId;

    protected $table = 'coupons';

    protected $fillable = [
        'public_id', 'promotion_id', 'code', 'status', 'min_spend', 'usage_limit',
        'per_customer_limit', 'used_count', 'reserved_count', 'lock_version', 'starts_at', 'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }

    public function hasRemainingCapacity(): bool
    {
        if ($this->usage_limit === null) {
            return true;
        }

        return ($this->used_count + $this->reserved_count) < $this->usage_limit;
    }
}
