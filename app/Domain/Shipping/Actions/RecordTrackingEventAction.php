<?php

declare(strict_types=1);

namespace App\Domain\Shipping\Actions;

use App\Domain\Shipping\Enums\ShipmentStatus;
use App\Domain\Shipping\Models\Shipment;
use App\Support\Clock\Clock;
use App\Support\Exceptions\BusinessRuleException;
use Illuminate\Support\Facades\DB;

/**
 * Records a courier tracking event, deduplicated per shipment (FR-SHP-008,
 * prompt §18 duplicate-shipment-event test). A duplicate external event id is a
 * no-op (unique constraint). Valid status events advance the shipment state
 * machine (SRS §10.2.4); the Delivered transition additionally requires proof of
 * delivery to exist (FR-SHP-011) — a bare "delivered" event without proof leaves
 * the shipment OutForDelivery/pending inquiry, never falsely delivered (FR-SHP-012).
 */
final class RecordTrackingEventAction
{
    /**
     * @return array{status: string}
     */
    public function execute(
        string $shipmentPublicId,
        string $externalEventId,
        string $trackingStatus,
        ?string $description = null,
        ?string $location = null,
    ): array {
        return DB::transaction(function () use ($shipmentPublicId, $externalEventId, $trackingStatus, $description, $location): array {
            /** @var Shipment $shipment */
            $shipment = Shipment::query()->where('public_id', $shipmentPublicId)->lockForUpdate()->firstOrFail();

            $inserted = DB::table('tracking_events')->insertOrIgnore([
                'shipment_id' => $shipment->id,
                'external_event_id' => $externalEventId,
                'status' => $trackingStatus,
                'description' => $description,
                'location' => $location,
                'occurred_at' => Clock::now(),
                'created_at' => Clock::now(),
            ]);

            if ($inserted === 0) {
                return ['status' => 'duplicate']; // FR-SHP-008 dedup
            }

            $target = $this->mapStatus($trackingStatus);
            if ($target === null || ! $shipment->status->canTransitionTo($target)) {
                return ['status' => 'recorded']; // event stored, no state change
            }

            // Delivered requires proof of delivery (FR-SHP-011).
            if ($target === ShipmentStatus::Delivered) {
                $hasProof = DB::table('proof_of_deliveries')->where('shipment_id', $shipment->id)->exists();
                if (! $hasProof) {
                    // Never falsely mark delivered (FR-SHP-012).
                    throw new BusinessRuleException('shipping.proof_required', 'Proof of delivery is required before marking delivered.');
                }
            }

            $shipment->status = $target;
            $shipment->lock_version += 1;
            $shipment->save();

            return ['status' => 'applied'];
        });
    }

    private function mapStatus(string $trackingStatus): ?ShipmentStatus
    {
        return match ($trackingStatus) {
            'dispatched' => ShipmentStatus::Dispatched,
            'in_transit' => ShipmentStatus::InTransit,
            'out_for_delivery' => ShipmentStatus::OutForDelivery,
            'delivered' => ShipmentStatus::Delivered,
            'failed' => ShipmentStatus::FailedAttempt,
            default => null,
        };
    }
}
