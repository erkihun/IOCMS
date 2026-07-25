<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Granular permission (resource + action). Deny-by-default authorization uses
 * effective permissions plus record scope (NFR-SE-012).
 *
 * @property string $code
 */
final class Permission extends Model
{
    protected $table = 'permissions';

    protected $fillable = ['code', 'resource', 'action', 'description', 'status'];
}
