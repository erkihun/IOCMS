<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Services;

use App\Domain\Pricing\Models\Coupon;
use App\Support\Clock\Clock;
use App\Support\Exceptions\BusinessRuleException;
use App\Support\Uuid\PublicId;
use Illuminate\Support\Facades\DB;

/**
 * Concurrency-safe coupon reservation and redemption (FR-PRI-005, prompt §18
 * coupon-concurrency test). Capacity is enforced by an atomic conditional
 * UPDATE that only succeeds while (used + reserved) < usage_limit, so two
 * concurrent redemptions of the last coupon cannot both win.
 */
final class CouponRedemptionService
{
    /**
     * Attempt to reserve one unit of coupon capacity for a cart/checkout holder.
     * Returns the reservation public id, or throws pricing.coupon_ineligible.
     */
    public function reserve(string $code, string $holderKey, int $ttlMinutes = 30): string
    {
        $coupon = Coupon::query()->where('code', $code)->first();

        if ($coupon === null || $coupon->status !== 'active' || ! $this->withinWindow($coupon)) {
            throw new BusinessRuleException('pricing.coupon_ineligible', 'Coupon is invalid, expired or not eligible.');
        }

        // Atomic capacity acquisition: only increments reserved_count while there
        // is remaining capacity. Unlimited coupons (usage_limit null) always pass.
        if ($coupon->usage_limit !== null) {
            $acquired = Coupon::query()
                ->where('id', $coupon->id)
                ->whereRaw('(used_count + reserved_count) < usage_limit')
                ->update([
                    'reserved_count' => DB::raw('reserved_count + 1'),
                    'lock_version' => DB::raw('lock_version + 1'),
                ]);

            if ($acquired === 0) {
                throw new BusinessRuleException('pricing.coupon_ineligible', 'Coupon is exhausted.');
            }
        } else {
            Coupon::query()->where('id', $coupon->id)->update(['reserved_count' => DB::raw('reserved_count + 1')]);
        }

        $publicId = PublicId::generate();
        DB::table('coupon_reservations')->insert([
            'public_id' => $publicId,
            'coupon_id' => $coupon->id,
            'holder_key' => $holderKey,
            'status' => 'held',
            'expires_at' => Clock::now()->addMinutes($ttlMinutes),
            'created_at' => Clock::now(),
            'updated_at' => Clock::now(),
        ]);

        return $publicId;
    }

    /**
     * Consume a held reservation on successful order placement: reserved→used.
     */
    public function consume(string $reservationPublicId, ?int $userId, string $orderReference): void
    {
        DB::transaction(function () use ($reservationPublicId, $userId, $orderReference): void {
            $reservation = DB::table('coupon_reservations')
                ->where('public_id', $reservationPublicId)
                ->where('status', 'held')
                ->lockForUpdate()
                ->first();

            if ($reservation === null) {
                throw new BusinessRuleException('pricing.coupon_ineligible', 'Coupon reservation is no longer valid.');
            }

            Coupon::query()->where('id', $reservation->coupon_id)->update([
                'used_count' => DB::raw('used_count + 1'),
                'reserved_count' => DB::raw('CASE WHEN reserved_count > 0 THEN reserved_count - 1 ELSE 0 END'),
            ]);

            DB::table('coupon_reservations')->where('id', $reservation->id)->update(['status' => 'consumed', 'updated_at' => Clock::now()]);

            // Idempotency: unique (coupon_id, order_reference) prevents double count.
            DB::table('coupon_usages')->insertOrIgnore([
                'coupon_id' => $reservation->coupon_id,
                'user_id' => $userId,
                'order_reference' => $orderReference,
                'created_at' => Clock::now(),
            ]);
        });
    }

    public function release(string $reservationPublicId): void
    {
        $reservation = DB::table('coupon_reservations')
            ->where('public_id', $reservationPublicId)
            ->where('status', 'held')
            ->first();

        if ($reservation === null) {
            return;
        }

        Coupon::query()->where('id', $reservation->coupon_id)
            ->update(['reserved_count' => DB::raw('CASE WHEN reserved_count > 0 THEN reserved_count - 1 ELSE 0 END')]);
        DB::table('coupon_reservations')->where('id', $reservation->id)
            ->update(['status' => 'released', 'updated_at' => Clock::now()]);
    }

    private function withinWindow(Coupon $coupon): bool
    {
        $now = Clock::now();

        return (! $coupon->starts_at || $now->gte($coupon->starts_at))
            && (! $coupon->ends_at || $now->lte($coupon->ends_at));
    }
}
