<?php

declare(strict_types=1);

namespace App\Domain\Engagement\Actions;

use App\Domain\Governance\Services\AuditRecorder;
use App\Support\Clock\Clock;
use App\Support\Exceptions\BusinessRuleException;
use App\Support\Uuid\PublicId;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Submits a verified-purchase product review (FR-REV-001/002, BR-013).
 *
 * Eligibility: the reviewer must own an order line for the product that is in a
 * delivered/completed state. Only one active review per order line is allowed
 * (unique constraint → review.duplicate). Reviews enter moderation before
 * publication per configured policy (FR-REV-004).
 */
final class SubmitReviewAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(int $userId, int $orderItemId, int $rating, ?string $title, ?string $comment): string
    {
        if ($rating < 1 || $rating > 5) {
            throw new BusinessRuleException('review.not_eligible', 'Rating must be between 1 and 5.');
        }

        // Verify the order line belongs to the user and is delivered/completed.
        $eligible = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.id', $orderItemId)
            ->where('orders.user_id', $userId)
            ->whereIn('orders.status', ['delivered', 'completed'])
            ->select('order_items.id', 'order_items.product_variant_id')
            ->first();

        if ($eligible === null) {
            throw new BusinessRuleException('review.not_eligible', 'No eligible delivered order item exists.');
        }

        $productId = DB::table('product_variants')->where('id', $eligible->product_variant_id)->value('product_id');

        try {
            $publicId = PublicId::generate();
            DB::table('reviews')->insert([
                'public_id' => $publicId,
                'order_item_id' => $orderItemId,
                'product_id' => $productId,
                'user_id' => $userId,
                'rating' => $rating,
                'title' => $title,
                'comment' => $comment,
                'verified_purchase' => true,
                'status' => 'pending', // moderation required (FR-REV-004)
                'created_at' => Clock::now(),
                'updated_at' => Clock::now(),
            ]);
        } catch (QueryException $e) {
            if (str_contains(mb_strtolower($e->getMessage()), 'unique')) {
                throw new BusinessRuleException('review.duplicate', 'An active review already exists for this order item.', [], $e);
            }
            throw $e;
        }

        $this->audit->record(action: 'review.submitted', resourceType: 'review', resourcePublicId: $publicId);

        return $publicId;
    }
}
