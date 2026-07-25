<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\IdentifierType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A normalized, uniqueness-constrained contact identifier (FR-IAM-001).
 *
 * @property string $type
 * @property string $normalized_value
 */
final class UserIdentifier extends Model
{
    protected $table = 'user_identifiers';

    protected $fillable = [
        'user_id', 'type', 'normalized_value', 'display_value', 'verified_at', 'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'type' => IdentifierType::class,
            'verified_at' => 'immutable_datetime',
            'is_primary' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
