<?php

declare(strict_types=1);

namespace App\Domain\Product\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Product warranty/return/tax/restricted metadata (FR-PRD-013). The return
 * policy version here is snapshotted onto order items so eligibility is judged
 * against the version in force at purchase (BR-038).
 *
 * @property int $return_window_days
 * @property int $policy_version
 */
final class ProductPolicyMetadata extends Model
{
    protected $table = 'product_policy_metadata';

    protected $fillable = [
        'product_id', 'warranty_months', 'return_window_days', 'is_returnable',
        'is_age_restricted', 'is_restricted', 'tax_class', 'policy_version',
    ];

    protected function casts(): array
    {
        return [
            'is_returnable' => 'boolean',
            'is_age_restricted' => 'boolean',
            'is_restricted' => 'boolean',
        ];
    }
}
