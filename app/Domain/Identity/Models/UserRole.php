<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RoleAssignment (IAM). Binds a user to a role at a record scope (BR-026:
 * seller/operational users are always restricted by scope in addition to role).
 *
 * @property string $scope_type
 * @property string|null $scope_id
 */
final class UserRole extends Model
{
    protected $table = 'user_roles';

    protected $fillable = [
        'user_id', 'role_id', 'scope_type', 'scope_id', 'starts_at', 'ends_at', 'assigned_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCurrentlyEffective(): bool
    {
        $now = now();

        return (! $this->starts_at || $now->gte($this->starts_at))
            && (! $this->ends_at || $now->lte($this->ends_at));
    }
}
