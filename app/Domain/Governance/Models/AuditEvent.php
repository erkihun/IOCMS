<?php

declare(strict_types=1);

namespace App\Domain\Governance\Models;

use App\Support\Uuid\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * Tamper-evident audit record (AUD, FR-AUD-001/003, NFR-SE-021). Append-only:
 * there is no update path from ordinary application flows, and each row's hash
 * chains from the previous row so alteration is detectable.
 *
 * @property string $public_id
 * @property string $action
 * @property string $hash
 */
final class AuditEvent extends Model
{
    use HasPublicId;

    public const UPDATED_AT = null;

    protected $table = 'audit_events';

    protected $fillable = [
        'public_id', 'actor_user_id', 'actor_type', 'action', 'resource_type',
        'resource_public_id', 'outcome', 'before', 'after', 'ip_address',
        'correlation_id', 'previous_hash', 'hash', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
