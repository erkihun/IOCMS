<?php

declare(strict_types=1);

namespace App\Platform\Integration\Services;

use App\Platform\Integration\Models\OutboxEvent;
use App\Support\Clock\Clock;
use App\Support\Correlation\CorrelationContext;
use App\Support\Uuid\PublicId;

/**
 * Records domain events into the transactional outbox (SDD ADR-006, LLD §3.6).
 * MUST be called inside the same DB transaction as the source state change so
 * the event and the change commit atomically. After commit, the outbox
 * dispatcher publishes the event to consumers.
 */
final class OutboxRecorder
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $eventName,
        string $aggregateType,
        ?string $aggregatePublicId,
        array $payload,
        int $aggregateVersion = 1,
        array $metadata = [],
    ): OutboxEvent {
        return OutboxEvent::create([
            'public_id' => PublicId::generate(),
            'event_name' => $eventName,
            'aggregate_type' => $aggregateType,
            'aggregate_public_id' => $aggregatePublicId,
            'aggregate_version' => $aggregateVersion,
            'payload' => $payload,
            'metadata' => $metadata,
            'correlation_id' => CorrelationContext::id(),
            'status' => 'pending',
            'attempts' => 0,
            'available_at' => Clock::now(),
        ]);
    }
}
