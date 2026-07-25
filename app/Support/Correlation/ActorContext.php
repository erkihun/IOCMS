<?php

declare(strict_types=1);

namespace App\Support\Correlation;

/**
 * Immutable description of the actor invoking a business use case (LLD §3.4).
 * Carried in DTOs so Actions can enforce authorization, scope and audit without
 * depending on the HTTP request object.
 */
final readonly class ActorContext
{
    /**
     * @param  array<int, array{type: string, id: string|null}>  $scopes
     */
    public function __construct(
        public ?int $userId,
        public string $type,          // customer|staff|seller|machine|system
        public ?string $userPublicId = null,
        public array $scopes = [],
        public bool $mfaSatisfied = false,
        public bool $recentlyReauthenticated = false,
        public string $correlationId = '',
    ) {}

    public static function system(): self
    {
        return new self(
            userId: null,
            type: 'system',
            correlationId: CorrelationContext::id(),
        );
    }

    public function isAuthenticated(): bool
    {
        return $this->userId !== null;
    }
}
