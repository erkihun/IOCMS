<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Support\Uuid\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * Inventory balance for a variant at a location (FR-INV-001). Tracks on_hand,
 * reserved and a materialized `available` (= on_hand − reserved − safety) used
 * for the atomic oversell guard (BR-008, FR-INV-007).
 *
 * @property int $on_hand
 * @property int $reserved
 * @property int $available
 * @property bool $backorder_allowed
 */
final class InventoryItem extends Model
{
    use HasPublicId;

    protected $table = 'inventory_items';

    protected $fillable = [
        'public_id', 'product_variant_id', 'warehouse_id', 'on_hand', 'reserved',
        'available', 'safety_stock', 'damaged', 'quarantined', 'backorder_allowed', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'on_hand' => 'integer',
            'reserved' => 'integer',
            'available' => 'integer',
            'safety_stock' => 'integer',
            'backorder_allowed' => 'boolean',
            'lock_version' => 'integer',
        ];
    }
}
