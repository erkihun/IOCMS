<?php

declare(strict_types=1);

namespace App\Support\Uuid;

use Illuminate\Database\Eloquent\Model;

/**
 * Adds a UUIDv7 `public_id` to a model and binds routes on it, never on the
 * internal BIGINT key (BR-046 / FR-INT-004 / SDD ADR-009).
 *
 * @mixin Model
 */
trait HasPublicId
{
    public static function bootHasPublicId(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->getAttribute('public_id'))) {
                $model->setAttribute('public_id', PublicId::generate());
            }
        });
    }

    /**
     * Resolve implicit route-model binding on the public identifier by default.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
