<?php

declare(strict_types=1);

use App\Domain\Product\Actions\TransitionProductStatusAction;
use App\Domain\Product\Enums\ProductStatus;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductTranslation;
use App\Domain\Product\Models\ProductVariant;
use App\Support\Exceptions\BusinessRuleException;
use App\Support\Exceptions\ConflictException;
use App\Support\Uuid\PublicId;

/**
 * FR-PRD-009/010 — product lifecycle transitions, publication readiness and
 * optimistic locking (SRS §10.2.1). Test basis T-FR-PRD-009.
 */
beforeEach(fn () => $this->action = app(TransitionProductStatusAction::class));

function draftProduct(bool $withVariantAndContent = true): Product
{
    $p = Product::query()->create([
        'public_id' => PublicId::generate(),
        'status' => ProductStatus::Draft->value,
        'type' => 'standard',
        'lock_version' => 0,
    ]);
    if ($withVariantAndContent) {
        ProductVariant::query()->create(['public_id' => PublicId::generate(), 'product_id' => $p->id, 'sku' => 'T-'.$p->id, 'status' => 'active', 'is_default' => true]);
        ProductTranslation::query()->create(['product_id' => $p->id, 'locale' => 'en', 'name' => 'Test', 'slug' => 'test-'.$p->id]);
    }

    return $p;
}

it('follows the approved transition path draft→pending→approved→published', function () {
    $p = draftProduct();
    $p = $this->action->execute($p, ProductStatus::PendingReview, 0);
    $p = $this->action->execute($p, ProductStatus::Approved, 1);
    $p = $this->action->execute($p, ProductStatus::Published, 2);

    expect($p->status)->toBe(ProductStatus::Published);
});

it('rejects an invalid transition (draft→published)', function () {
    $p = draftProduct();
    expect(fn () => $this->action->execute($p, ProductStatus::Published, 0))
        ->toThrow(BusinessRuleException::class);
});

it('blocks publication when the product lacks a variant or content', function () {
    $p = draftProduct(withVariantAndContent: false);
    $p = $this->action->execute($p, ProductStatus::PendingReview, 0);
    $p = $this->action->execute($p, ProductStatus::Approved, 1);

    expect(fn () => $this->action->execute($p, ProductStatus::Published, 2))
        ->toThrow(BusinessRuleException::class);
});

it('rejects a stale lock_version with a conflict', function () {
    $p = draftProduct();
    expect(fn () => $this->action->execute($p, ProductStatus::PendingReview, 99))
        ->toThrow(ConflictException::class);
});
