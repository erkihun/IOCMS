<?php

declare(strict_types=1);

namespace App\Domain\Marketplace\Services;

use App\Support\Exceptions\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Enforces seller data isolation as a security boundary (FR-SEL-005/MVO-007,
 * BR-026/032, prompt §18 seller-cross-scope test).
 *
 * A seller user may only access records owned by a seller they belong to. This
 * is checked server-side on every seller-scoped query/mutation, in addition to
 * role permissions (deny-by-default). Attempting to access another seller's
 * records raises seller.out_of_scope (403).
 */
final class SellerScopeGuard
{
    /**
     * The seller ids the given user is a member of.
     *
     * @return int[]
     */
    public function sellerIdsForUser(int $userId): array
    {
        return DB::table('seller_users')->where('user_id', $userId)->pluck('seller_id')->map(fn ($v): int => (int) $v)->all();
    }

    public function assertCanAccessSeller(int $userId, int $targetSellerId): void
    {
        if (! in_array($targetSellerId, $this->sellerIdsForUser($userId), true)) {
            throw new AuthorizationException('seller.out_of_scope', 'The record does not belong to your seller account.');
        }
    }

    /**
     * Assert a seller order belongs to a seller the user is a member of.
     */
    public function assertCanAccessSellerOrder(int $userId, int $sellerOrderId): void
    {
        $sellerId = DB::table('seller_orders')->where('id', $sellerOrderId)->value('seller_id');
        if ($sellerId === null) {
            throw new AuthorizationException('seller.out_of_scope', 'Record not found in your scope.');
        }
        $this->assertCanAccessSeller($userId, (int) $sellerId);
    }
}
