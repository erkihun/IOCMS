<?php

declare(strict_types=1);

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Shipping\Actions\RecordTrackingEventAction;
use App\Domain\Shipping\Enums\ShipmentStatus;
use App\Domain\Shipping\Models\Shipment;
use App\Support\Exceptions\BusinessRuleException;
use App\Support\Uuid\PublicId;
use Illuminate\Support\Facades\DB;

/**
 * FR-SHP-008/011/012, prompt §18 — duplicate shipment events + proof-of-delivery
 * gating. Test basis T-FR-SHP-008.
 */
beforeEach(fn () => $this->action = app(RecordTrackingEventAction::class));

function readyShipment(): Shipment
{
    $order = Order::query()->create(['public_id' => PublicId::generate(), 'order_number' => 'ORD-S-'.random_int(1000, 9999), 'status' => OrderStatus::Fulfilled->value, 'currency' => 'ETB', 'grand_total' => '10.0000', 'lock_version' => 0]);

    return Shipment::query()->create([
        'public_id' => PublicId::generate(),
        'shipment_number' => 'SHP-'.random_int(1000, 9999),
        'order_id' => $order->id,
        'status' => ShipmentStatus::Ready->value,
        'lock_version' => 0,
    ]);
}

it('advances the shipment on a valid tracking event', function () {
    $shipment = readyShipment();
    $result = $this->action->execute($shipment->public_id, 'evt-1', 'dispatched');

    expect($result['status'])->toBe('applied');
    expect($shipment->fresh()->status)->toBe(ShipmentStatus::Dispatched);
});

it('deduplicates a duplicate tracking event (FR-SHP-008)', function () {
    $shipment = readyShipment();
    $this->action->execute($shipment->public_id, 'evt-dup', 'dispatched');
    $second = $this->action->execute($shipment->public_id, 'evt-dup', 'dispatched');

    expect($second['status'])->toBe('duplicate');
    expect(DB::table('tracking_events')->where('shipment_id', $shipment->id)->count())->toBe(1);
});

it('refuses to mark delivered without proof of delivery (FR-SHP-011/012)', function () {
    $shipment = readyShipment();
    $this->action->execute($shipment->public_id, 'e1', 'dispatched');
    $this->action->execute($shipment->public_id, 'e2', 'in_transit');
    $this->action->execute($shipment->public_id, 'e3', 'out_for_delivery');

    expect(fn () => $this->action->execute($shipment->public_id, 'e4', 'delivered'))
        ->toThrow(BusinessRuleException::class);

    expect($shipment->fresh()->status)->toBe(ShipmentStatus::OutForDelivery); // not falsely delivered
});

it('marks delivered once proof of delivery exists', function () {
    $shipment = readyShipment();
    $this->action->execute($shipment->public_id, 'e1', 'dispatched');
    $this->action->execute($shipment->public_id, 'e2', 'in_transit');
    $this->action->execute($shipment->public_id, 'e3', 'out_for_delivery');

    DB::table('proof_of_deliveries')->insert([
        'public_id' => PublicId::generate(), 'shipment_id' => $shipment->id,
        'proof_type' => 'otp', 'delivered_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    $result = $this->action->execute($shipment->public_id, 'e4', 'delivered');
    expect($result['status'])->toBe('applied');
    expect($shipment->fresh()->status)->toBe(ShipmentStatus::Delivered);
});
