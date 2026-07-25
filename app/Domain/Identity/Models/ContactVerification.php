<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Support\Uuid\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Time-limited, single-use contact verification token (FR-IAM-003). The raw
 * token is never stored — only its hash. Expiry + single-use prevent replay.
 *
 * @property string $status
 * @property CarbonImmutable $expires_at
 */
final class ContactVerification extends Model
{
    use HasPublicId;

    protected $table = 'contact_verifications';

    protected $fillable = [
        'public_id', 'user_id', 'channel', 'token_hash', 'status', 'expires_at', 'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isConsumable(): bool
    {
        return $this->status === 'pending' && $this->expires_at->isFuture();
    }
}
