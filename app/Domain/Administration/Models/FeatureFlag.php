<?php

declare(strict_types=1);

namespace App\Domain\Administration\Models;

use App\Domain\Administration\Services\FeatureFlagEvaluator;
use App\Support\Uuid\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * Feature flag with owner, scope, activation window and rollback (FR-ADM-005).
 * Evaluated by {@see FeatureFlagEvaluator}.
 *
 * @property string $key
 * @property bool $is_enabled
 * @property string $status
 */
final class FeatureFlag extends Model
{
    use HasPublicId;

    protected $table = 'feature_flags';

    protected $fillable = [
        'public_id', 'key', 'name', 'is_enabled', 'status', 'owner',
        'scope_type', 'starts_at', 'ends_at', 'metadata', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }
}
