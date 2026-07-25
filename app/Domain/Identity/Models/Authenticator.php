<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\AuthenticatorStatus;
use App\Domain\Identity\Enums\AuthenticatorType;
use App\Support\Uuid\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AuthenticatorSet member (IAM). Secret is encrypted at rest (NFR-SE-015).
 *
 * @property string $type
 * @property string $status
 */
final class Authenticator extends Model
{
    use HasPublicId;

    protected $table = 'authenticators';

    protected $fillable = ['public_id', 'user_id', 'type', 'status', 'secret', 'label', 'last_used_at'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'type' => AuthenticatorType::class,
            'status' => AuthenticatorStatus::class,
            'secret' => 'encrypted',
            'last_used_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
