<?php

declare(strict_types=1);

namespace App\Domain\Shipping\Models;

use App\Domain\Shipping\Enums\ShipmentStatus;
use App\Support\Uuid\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * Shipment aggregate (SHP). Tracking events are deduplicated (FR-SHP-008) and
 * proof-of-delivery is required before Delivered (FR-SHP-011).
 *
 * @property string $public_id
 * @property ShipmentStatus $status
 * @property int $lock_version
 */
final class Shipment extends Model
{
    use HasPublicId;

    protected $table = 'shipments';

    protected $fillable = [
        'public_id', 'shipment_number', 'order_id', 'status', 'provider',
        'tracking_reference', 'shipping_method_id', 'lock_version',
    ];

    protected function casts(): array
    {
        return ['status' => ShipmentStatus::class, 'lock_version' => 'integer'];
    }
}
