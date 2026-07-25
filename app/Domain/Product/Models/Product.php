<?php

declare(strict_types=1);

namespace App\Domain\Product\Models;

use App\Domain\Product\Enums\ProductStatus;
use App\Support\Uuid\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Product master aggregate (PRD). Separate from purchasable variants (BR-001).
 * Status transitions go through ProductPublicationAction, not direct writes.
 *
 * @property string $public_id
 * @property ProductStatus $status
 * @property int $lock_version
 */
final class Product extends Model
{
    use HasPublicId;

    protected $table = 'products';

    protected $fillable = [
        'public_id', 'category_id', 'brand_id', 'attribute_set_id', 'seller_id',
        'status', 'type', 'lock_version',
    ];

    protected function casts(): array
    {
        return ['status' => ProductStatus::class, 'lock_version' => 'integer'];
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /** @return HasMany<ProductTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(ProductTranslation::class);
    }

    /** @return HasOne<ProductPolicyMetadata, $this> */
    public function policy(): HasOne
    {
        return $this->hasOne(ProductPolicyMetadata::class);
    }
}
