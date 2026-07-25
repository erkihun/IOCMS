<?php

declare(strict_types=1);

namespace App\Domain\Order\Models;

use App\Domain\Order\Enums\OrderStatus;
use App\Support\Uuid\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Order aggregate (ORD). Immutable commercial snapshot after placement
 * (BR-002/016). Status is an independent state machine (BR-003) advanced only
 * through TransitionOrderStatusAction.
 *
 * @property string $public_id
 * @property string $order_number
 * @property OrderStatus $status
 * @property int $lock_version
 * @property CarbonImmutable|null $confirmed_at
 */
final class Order extends Model
{
    use HasPublicId;

    protected $table = 'orders';

    protected $fillable = [
        'public_id', 'order_number', 'user_id', 'guest_email', 'status', 'currency',
        'items_subtotal', 'discount_total', 'tax_total', 'delivery_total', 'grand_total',
        'payment_method', 'delivery_method', 'terms_version', 'quote_snapshot',
        'lock_version', 'correlation_id', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'quote_snapshot' => 'array',
            'lock_version' => 'integer',
            'confirmed_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
