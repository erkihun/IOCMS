<?php

declare(strict_types=1);

namespace App\Domain\Marketplace\Actions;

use App\Support\Clock\Clock;
use App\Support\Exceptions\ConflictException;
use App\Support\Money\Money;
use App\Support\Uuid\PublicId;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Posts a commission snapshot for a seller order line (FR-COM-001/003, BR-028).
 *
 * The commission rate and amount are snapshotted at order-item level using the
 * rule effective when the order is confirmed, so later rule changes never alter
 * historical transactions (BR-003 immutable evidence). Posting is idempotent —
 * a unique constraint on order_item_id prevents duplicate commission posting
 * (prompt §18 duplicate-commission test → ledger.duplicate_source).
 *
 * Gated: only runs when the marketplace feature is enabled (decision D-005).
 */
final class PostCommissionAction
{
    public function execute(int $orderItemId, int $sellerId, string $rate, Money $lineAmount): string
    {
        $commission = $lineAmount->multiplyByRate($rate);
        $publicId = PublicId::generate();

        try {
            return DB::transaction(function () use ($orderItemId, $sellerId, $rate, $commission, $lineAmount, $publicId): string {
                DB::table('commission_snapshots')->insert([
                    'public_id' => $publicId,
                    'order_item_id' => $orderItemId,
                    'seller_id' => $sellerId,
                    'applied_rate' => $rate,
                    'commission_amount' => $commission->toDecimalString(),
                    'currency' => $commission->currency,
                    'created_at' => Clock::now(),
                    'updated_at' => Clock::now(),
                ]);

                // Post the sale and commission to the append-only seller ledger.
                $this->postLedger($sellerId, 'sale', 'available', $lineAmount, 'order_item', (string) $orderItemId);
                $this->postLedger($sellerId, 'commission', 'available', $commission->negate(), 'order_item', (string) $orderItemId);

                return $publicId;
            });
        } catch (QueryException $e) {
            if (str_contains(mb_strtolower($e->getMessage()), 'unique')) {
                // Duplicate commission posting is rejected (BR-028).
                throw new ConflictException('ledger.duplicate_source', 'Commission was already posted for this order line.', [], $e);
            }
            throw $e;
        }
    }

    private function postLedger(int $sellerId, string $type, string $bucket, Money $amount, string $sourceType, string $sourceRef): void
    {
        DB::table('seller_ledger_entries')->insertOrIgnore([
            'public_id' => PublicId::generate(),
            'seller_id' => $sellerId,
            'type' => $type,
            'bucket' => $bucket,
            'amount' => $amount->toDecimalString(),
            'currency' => $amount->currency,
            'source_type' => $sourceType,
            'source_reference' => $sourceRef,
            'posted_at' => Clock::now(),
        ]);
    }
}
