<?php

declare(strict_types=1);

namespace App\Domain\Product\Models;

use Illuminate\Database\Eloquent\Model;

/** Localized product fields (FR-PRD-004, FR-LOC-002). */
final class ProductTranslation extends Model
{
    protected $table = 'product_translations';

    protected $fillable = [
        'product_id', 'locale', 'name', 'slug', 'short_description',
        'description', 'specifications', 'care_instructions',
    ];
}
