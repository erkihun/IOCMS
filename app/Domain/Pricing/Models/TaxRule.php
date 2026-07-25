<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Models;

use Illuminate\Database\Eloquent\Model;

/** Configured tax rate for a class/jurisdiction/period (FR-PRI-008). @property string $rate */
final class TaxRule extends Model
{
    protected $table = 'tax_rules';

    protected $fillable = ['tax_class_id', 'jurisdiction', 'rate', 'effective_from', 'effective_to'];

    protected function casts(): array
    {
        return [
            'effective_from' => 'immutable_datetime',
            'effective_to' => 'immutable_datetime',
        ];
    }
}
