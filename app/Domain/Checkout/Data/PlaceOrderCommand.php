<?php

declare(strict_types=1);

namespace App\Domain\Checkout\Data;

use App\Support\Correlation\ActorContext;

/**
 * Validated input for order placement (LLD §3.4). Carries the idempotency key,
 * request fingerprint and actor so the Action can enforce exactly-once semantics
 * and authorization without depending on the HTTP request.
 */
final readonly class PlaceOrderCommand
{
    public function __construct(
        public string $checkoutPublicId,
        public string $idempotencyKey,
        public string $requestFingerprint,
        public ActorContext $actor,
    ) {}
}
