<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Role aggregate (IAM). Grants a set of permissions; assigned to users at a scope.
 *
 * @property string $code
 * @property string $scope_type
 */
final class Role extends Model
{
    protected $table = 'roles';

    protected $fillable = ['code', 'name', 'scope_type', 'status', 'system_owned'];

    protected function casts(): array
    {
        return ['system_owned' => 'boolean'];
    }

    /** @return BelongsToMany<Permission, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }
}
