<?php

declare(strict_types=1);

namespace App\Domain\Product\Actions;

use App\Domain\Governance\Services\AuditRecorder;
use App\Domain\Product\Enums\ProductStatus;
use App\Domain\Product\Models\Product;
use App\Platform\Integration\Services\OutboxRecorder;
use App\Support\Exceptions\BusinessRuleException;
use App\Support\Exceptions\ConflictException;
use Illuminate\Support\Facades\DB;

/**
 * Transitions a product through its lifecycle (FR-PRD-009, SRS §10.2.1).
 *
 * Enforces the allowed-transition guard (invalid transitions → 422
 * product.invalid_transition), publication readiness (a product must have at
 * least one active variant and required content before it can be Published —
 * FR-PRD-010 / product.publication_incomplete), optimistic locking on
 * lock_version (409 on concurrent edit), plus audit + outbox in one transaction.
 */
final class TransitionProductStatusAction
{
    public function __construct(
        private readonly OutboxRecorder $outbox,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(Product $product, ProductStatus $to, int $expectedLockVersion): Product
    {
        return DB::transaction(function () use ($product, $to, $expectedLockVersion): Product {
            /** @var Product $fresh */
            $fresh = Product::query()->lockForUpdate()->findOrFail($product->id);

            if ($fresh->lock_version !== $expectedLockVersion) {
                throw new ConflictException('product.concurrent_update', 'Product changed during processing.');
            }

            $from = $fresh->status;

            if (! $from->canTransitionTo($to)) {
                throw new BusinessRuleException(
                    'product.invalid_transition',
                    "Cannot move product from {$from->value} to {$to->value}.",
                );
            }

            if ($to === ProductStatus::Published) {
                $this->assertPublishable($fresh);
            }

            $fresh->status = $to;
            $fresh->lock_version = $expectedLockVersion + 1;
            $fresh->save();

            $this->audit->record(
                action: 'product.status_changed',
                resourceType: 'product',
                resourcePublicId: $fresh->public_id,
                before: ['status' => $from->value],
                after: ['status' => $to->value],
            );

            $this->outbox->record(
                eventName: 'ProductStatusChanged',
                aggregateType: 'product',
                aggregatePublicId: $fresh->public_id,
                payload: ['from' => $from->value, 'to' => $to->value],
                aggregateVersion: $fresh->lock_version,
            );

            return $fresh;
        });
    }

    private function assertPublishable(Product $product): void
    {
        $hasActiveVariant = $product->variants()->where('status', 'active')->exists();
        $hasContent = $product->translations()->exists();

        if (! $hasActiveVariant || ! $hasContent) {
            throw new BusinessRuleException(
                'product.publication_incomplete',
                'Product lacks required variant or content to publish.',
            );
        }
    }
}
