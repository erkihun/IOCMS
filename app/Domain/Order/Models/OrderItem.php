<?php

declare(strict_types=1);

namespace App\Domain\Order\Models;

use App\Support\Uuid\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * Immutable order line snapshot (BR-002). Catalog changes never rewrite these
 * (BR-016). Quantity ladder invariants: delivered ≤ shipped ≤ fulfilled ≤
 * ordered − cancelled (BR-010).
 *
 * @property int $quantity_ordered
 */
final class OrderItem extends Model
{
    use HasPublicId;

    protected $table = 'order_items';

    protected $fillable = [
        'public_id', 'order_id', 'product_variant_id', 'seller_id', 'product_name',
        'sku', 'selected_options', 'image_reference', 'unit_price', 'discount', 'tax',
        'line_total', 'currency', 'quantity_ordered', 'quantity_allocated',
        'quantity_cancelled', 'quantity_fulfilled', 'quantity_returned',
        'quantity_refunded', 'return_policy_version', 'return_window_days',
    ];

    protected function casts(): array
    {
        return ['selected_options' => 'array'];
    }
}
