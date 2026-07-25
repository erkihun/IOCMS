<?php

declare(strict_types=1);

namespace App\Domain\Marketplace\Actions;

use App\Domain\Governance\Services\AuditRecorder;
use App\Support\Clock\Clock;
use App\Support\Exceptions\AuthorizationException;
use App\Support\Exceptions\BusinessRuleException;
use Illuminate\Support\Facades\DB;

/**
 * Approves a seller payout under maker-checker separation (FR-COM-007/009,
 * BR-024/030, prompt §18 payout-approval-separation test).
 *
 * - The checker must differ from the maker who created the payout (BR-024) —
 *   self-approval is rejected (payout.approval_required / self-approval forbidden).
 * - The payout amount must not exceed the seller's eligible payable balance
 *   (BR-030 → payout.insufficient_available_balance).
 * - Approval is idempotent and moves the payout Pending Approval → Approved.
 *
 * Gated behind the marketplace feature (decision D-005).
 */
final class ApprovePayoutAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(string $payoutPublicId, int $checkerId): void
    {
        DB::transaction(function () use ($payoutPublicId, $checkerId): void {
            $payout = DB::table('seller_payouts')->where('public_id', $payoutPublicId)->lockForUpdate()->first();

            if ($payout === null) {
                throw new BusinessRuleException('payout.approval_required', 'Payout not found.');
            }

            // BR-024: maker cannot be the checker.
            if ((int) $payout->maker_id === $checkerId) {
                throw new AuthorizationException('seller.self_approval_forbidden', 'You cannot approve a payout you created.');
            }

            if ($payout->status !== 'pending_approval') {
                // Idempotent: already approved is a no-op if the same decision.
                if ($payout->status === 'approved') {
                    return;
                }
                throw new BusinessRuleException('payout.approval_required', 'Payout is not awaiting approval.');
            }

            // BR-030: payout cannot exceed the seller's eligible payable balance.
            $payable = (float) DB::table('seller_ledger_entries')
                ->where('seller_id', $payout->seller_id)
                ->whereIn('bucket', ['available', 'payable'])
                ->sum('amount');

            if ((float) $payout->amount > $payable + 1e-6) {
                throw new BusinessRuleException('payout.insufficient_available_balance', 'Payout exceeds the available balance.');
            }

            DB::table('seller_payouts')->where('id', $payout->id)->update([
                'status' => 'approved',
                'checker_id' => $checkerId,
                'updated_at' => Clock::now(),
            ]);

            DB::table('payout_approvals')->insert([
                'seller_payout_id' => $payout->id,
                'actor_id' => $checkerId,
                'decision' => 'approved',
                'decided_at' => Clock::now(),
            ]);

            $this->audit->record(
                action: 'payout.approved',
                resourceType: 'seller_payout',
                resourcePublicId: $payoutPublicId,
                after: ['checker_id' => $checkerId, 'amount' => $payout->amount],
            );
        });
    }
}
