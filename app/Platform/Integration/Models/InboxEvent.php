<?php

declare(strict_types=1);

namespace App\Platform\Integration\Models;

use App\Support\Uuid\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * Idempotent inbox record for inbound provider/webhook events (INT, LLD §5.25).
 * A duplicate (source, external_event_id) is rejected by a unique constraint so
 * business effects stay exactly-once (BR-020, FR-PAY-007). Unknown or not-yet-
 * applicable events are stored as pending for diagnosis (BR-048).
 *
 * @property string $public_id
 * @property string $source
 * @property string $external_event_id
 * @property string $status
 */
final class InboxEvent extends Model
{
    use HasPublicId;

    protected $table = 'inbox_events';

    protected $fillable = [
        'public_id', 'source', 'external_event_id', 'event_type', 'payload',
        'correlation_id', 'status', 'attempts', 'note', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'processed_at' => 'immutable_datetime',
        ];
    }
}
