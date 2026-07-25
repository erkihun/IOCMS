<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Models\InventoryItem;
use App\Support\Clock\Clock;
use App\Support\Correlation\CorrelationContext;
use App\Support\Exceptions\BusinessRuleException;
use App\Support\Uuid\PublicId;
use Illuminate\Support\Facades\DB;

/**
 * Concurrency-safe stock reservation (FR-INV-004/005/007, BR-006/007/008,
 * prompt §12 order-placement + §18 concurrent-final-stock test).
 *
 * Overselling is prevented by an **atomic conditional update**:
 *   UPDATE inventory_items
 *   SET reserved = reserved + :qty, available = available - :qty, ...
 *   WHERE id = :id AND available >= :qty
 * If zero rows are affected, the last units were taken by a competitor and the
 * reservation is rejected. This is exact even without SELECT…FOR UPDATE, so it
 * behaves correctly on both MySQL and SQLite. A reservation reduces `available`
 * (and `reserved`) but never `on_hand` until consumption (BR-006).
 *
 * Callers MUST invoke this inside the order-placement transaction so the
 * reservation, order snapshot, audit and outbox commit atomically.
 */
final class InventoryReservationService
{
    /**
     * Reserve `quantity` units of a variant for a holder (checkout/order).
     * Records an immutable movement. Throws inventory.insufficient_available
     * when stock cannot cover the request (and backorder is not allowed).
     *
     * @return string reservation public id
     */
    public function reserve(int $variantId, int $quantity, string $holderType, string $holderReference, int $ttlMinutes): string
    {
        if ($quantity < 1) {
            throw new BusinessRuleException('inventory.insufficient_available', 'Quantity must be at least 1.');
        }

        $item = InventoryItem::query()
            ->where('product_variant_id', $variantId)
            ->first();

        if ($item === null) {
            throw new BusinessRuleException('inventory.insufficient_available', 'No stock record for this item.');
        }

        if (! $item->backorder_allowed) {
            // Atomic guard: only succeeds while available >= quantity.
            $affected = InventoryItem::query()
                ->where('id', $item->id)
                ->where('available', '>=', $quantity)
                ->update([
                    'reserved' => DB::raw("reserved + {$quantity}"),
                    'available' => DB::raw("available - {$quantity}"),
                    'lock_version' => DB::raw('lock_version + 1'),
                ]);

            if ($affected === 0) {
                throw new BusinessRuleException(
                    'inventory.insufficient_available',
                    'The selected quantity is no longer available.',
                );
            }
        } else {
            InventoryItem::query()->where('id', $item->id)->update([
                'reserved' => DB::raw("reserved + {$quantity}"),
                'available' => DB::raw("available - {$quantity}"),
            ]);
        }

        $reservationPublicId = PublicId::generate();

        DB::table('stock_reservations')->insert([
            'public_id' => $reservationPublicId,
            'inventory_item_id' => $item->id,
            'holder_type' => $holderType,
            'holder_reference' => $holderReference,
            'quantity' => $quantity,
            'status' => 'active',
            'expires_at' => Clock::now()->addMinutes($ttlMinutes),
            'created_at' => Clock::now(),
            'updated_at' => Clock::now(),
        ]);

        $this->recordMovement($item->id, 'reservation', -$quantity, 'reserve', $holderReference);

        return $reservationPublicId;
    }

    /**
     * Consume an active reservation on fulfillment: reserved→0, on_hand reduced.
     * Idempotent — a reservation already consumed is a no-op.
     */
    public function consume(string $reservationPublicId): void
    {
        DB::transaction(function () use ($reservationPublicId): void {
            $reservation = DB::table('stock_reservations')
                ->where('public_id', $reservationPublicId)
                ->lockForUpdate()
                ->first();

            if ($reservation === null || $reservation->status !== 'active') {
                return; // idempotent
            }

            InventoryItem::query()->where('id', $reservation->inventory_item_id)->update([
                'on_hand' => DB::raw("on_hand - {$reservation->quantity}"),
                'reserved' => DB::raw("reserved - {$reservation->quantity}"),
            ]);

            DB::table('stock_reservations')->where('id', $reservation->id)
                ->update(['status' => 'consumed', 'resolved_at' => Clock::now(), 'updated_at' => Clock::now()]);

            $this->recordMovement($reservation->inventory_item_id, 'consume', -$reservation->quantity, 'fulfill', (string) $reservation->holder_reference);
        });
    }

    /**
     * Release an active reservation (expiry/cancel/failed payment). Restores
     * `available`. Exactly-once (BR-007) — a non-active reservation is a no-op.
     */
    public function release(string $reservationPublicId): void
    {
        DB::transaction(function () use ($reservationPublicId): void {
            $reservation = DB::table('stock_reservations')
                ->where('public_id', $reservationPublicId)
                ->lockForUpdate()
                ->first();

            if ($reservation === null || $reservation->status !== 'active') {
                return;
            }

            InventoryItem::query()->where('id', $reservation->inventory_item_id)->update([
                'reserved' => DB::raw("reserved - {$reservation->quantity}"),
                'available' => DB::raw("available + {$reservation->quantity}"),
            ]);

            DB::table('stock_reservations')->where('id', $reservation->id)
                ->update(['status' => 'released', 'resolved_at' => Clock::now(), 'updated_at' => Clock::now()]);

            $this->recordMovement($reservation->inventory_item_id, 'release', $reservation->quantity, 'release', (string) $reservation->holder_reference);
        });
    }

    private function recordMovement(int $inventoryItemId, string $type, int $signedQuantity, string $reason, string $reference): void
    {
        DB::table('stock_movements')->insert([
            'public_id' => PublicId::generate(),
            'inventory_item_id' => $inventoryItemId,
            'type' => $type,
            'quantity' => $signedQuantity,
            'reason' => $reason,
            'reference' => $reference,
            'correlation_id' => CorrelationContext::id(),
            'created_at' => Clock::now(),
        ]);
    }

    /**
     * Seed or set on-hand for a variant (receiving/opening stock). Recomputes
     * available. Used by receiving and tests.
     */
    public function setOnHand(int $variantId, int $onHand, ?int $warehouseId = null): InventoryItem
    {
        $item = InventoryItem::query()->firstOrCreate(
            ['product_variant_id' => $variantId, 'warehouse_id' => $warehouseId],
            ['public_id' => PublicId::generate(), 'on_hand' => 0, 'reserved' => 0, 'available' => 0],
        );

        $available = $onHand - $item->reserved - $item->safety_stock;
        $item->update(['on_hand' => $onHand, 'available' => max($available, 0)]);
        $this->recordMovement($item->id, 'receipt', $onHand - ($item->on_hand ?? 0), 'set_on_hand', 'opening');

        return $item->refresh();
    }
}
