<?php

declare(strict_types=1);

namespace App\Platform\Integration\Models;

use App\Platform\Integration\Jobs\PublishOutboxEventJob;
use App\Support\Uuid\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * Transactional outbox record (INT, LLD §5.25). Written in the same DB
 * transaction as the source state change; dispatched after commit by
 * {@see PublishOutboxEventJob}.
 *
 * @property string $public_id
 * @property string $event_name
 * @property string $status
 */
final class OutboxEvent extends Model
{
    use HasPublicId;

    protected $table = 'outbox_events';

    protected $fillable = [
        'public_id', 'event_name', 'aggregate_type', 'aggregate_public_id',
        'aggregate_version', 'payload', 'metadata', 'correlation_id',
        'status', 'attempts', 'available_at', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'metadata' => 'array',
            'aggregate_version' => 'integer',
            'attempts' => 'integer',
            'available_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }
}
