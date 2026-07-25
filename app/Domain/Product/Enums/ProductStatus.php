<?php

declare(strict_types=1);

namespace App\Domain\Product\Enums;

/**
 * Product lifecycle states (FR-PRD-009) with an explicit transition guard
 * (SRS §10.2.1 / prompt state model). Only unpublished/suspended/ineligible
 * products are blocked from new purchase while history is preserved (FR-PRD-010).
 */
enum ProductStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Published = 'published';
    case Suspended = 'suspended';
    case Archived = 'archived';
    case Rejected = 'rejected';

    /** @return ProductStatus[] */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::PendingReview, self::Archived],
            self::PendingReview => [self::Approved, self::Rejected],
            self::Approved => [self::Published, self::Archived],
            self::Published => [self::Suspended, self::Archived],
            self::Suspended => [self::Published, self::Archived],
            self::Rejected => [self::Draft, self::Archived],
            self::Archived => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function isPurchasable(): bool
    {
        return $this === self::Published;
    }
}
