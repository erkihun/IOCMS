<?php

declare(strict_types=1);

namespace App\Platform\Integration\Jobs;

use App\Platform\Integration\Models\OutboxEvent;
use App\Support\Clock\Clock;
use App\Support\Correlation\CorrelationContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Event;

/**
 * Publishes one committed outbox event to in-process listeners after commit
 * (SDD ADR-006, LLD §3.6). Transport is at-least-once; consumers dedupe by
 * event ID + aggregate version so business effects are exactly-once.
 *
 * Queue: transactional-outbox. Idempotent: re-publishing a published event is a
 * no-op guarded by status.
 */
final class PublishOutboxEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 30;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 30, 60, 120, 300];
    }

    public function __construct(public readonly int $outboxEventId) {}

    public function handle(): void
    {
        $event = OutboxEvent::query()->find($this->outboxEventId);

        if ($event === null || $event->status === 'published') {
            return; // idempotent: nothing to do
        }

        if ($event->correlation_id) {
            CorrelationContext::set($event->correlation_id);
        }

        // Broadcast a generic domain-event signal keyed by name; module listeners
        // subscribe by event name and apply their own inbox/version idempotency.
        Event::dispatch('outbox.'.$event->event_name, [$event]);

        $event->forceFill([
            'status' => 'published',
            'published_at' => Clock::now(),
            'attempts' => $event->attempts + 1,
        ])->save();
    }
}
