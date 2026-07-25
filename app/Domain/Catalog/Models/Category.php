<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Support\Uuid\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Category aggregate (CAT). Multi-level tree with a materialized `path` used to
 * detect cycles (FR-CAT-002). Localized names live in category_translations.
 *
 * @property string $slug
 * @property string $status
 * @property string|null $path
 */
final class Category extends Model
{
    use HasPublicId;

    protected $table = 'categories';

    protected $fillable = ['public_id', 'parent_id', 'slug', 'status', 'depth', 'sort_order', 'path'];

    /** @return BelongsTo<Category, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Category, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<CategoryTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(CategoryTranslation::class);
    }
}
