<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Support\Uuid\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Active-session registry entry for the FR-IAM-008 "list & revoke sessions"
 * feature. Distinct from the framework session store (decision D-013). Ownership
 * is enforced by SessionPolicy — a user may only revoke their own sessions.
 *
 * @property string $public_id
 * @property int $user_id
 * @property CarbonImmutable|null $revoked_at
 */
final class UserSession extends Model
{
    use HasPublicId;

    protected $table = 'user_sessions';

    protected $fillable = [
        'public_id', 'user_id', 'session_token_hash', 'ip_address',
        'user_agent', 'last_active_at', 'revoked_at',
    ];

    protected $hidden = ['session_token_hash'];

    protected function casts(): array
    {
        return [
            'last_active_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
