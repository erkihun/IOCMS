<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Localized category fields, stored independently of the source record (FR-LOC-002).
 */
final class CategoryTranslation extends Model
{
    protected $table = 'category_translations';

    protected $fillable = ['category_id', 'locale', 'name', 'description', 'meta_title', 'meta_description'];
}
