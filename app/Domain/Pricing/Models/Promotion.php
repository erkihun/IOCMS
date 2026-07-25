<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Models;

use App\Support\Uuid\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * Promotion with precedence/exclusion (FR-PRI-004/006). Versioned so the version
 * effective at checkout is what applies (BR-014).
 *
 * @property string $type
 * @property int $priority
 * @property bool $is_exclusive
 */
final class Promotion extends Model
{
    use HasPublicId;

    protected $table = 'promotions';

    protected $fillable = [
        'public_id', 'code', 'name', 'type', 'status', 'priority',
        'is_exclusive', 'version', 'starts_at', 'ends_at', 'created_by', 'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'is_exclusive' => 'boolean',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }
}
