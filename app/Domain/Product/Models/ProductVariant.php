<?php

declare(strict_types=1);

namespace App\Domain\Product\Models;

use App\Support\Uuid\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Purchasable variant (BR-001). SKU/barcode uniqueness is enforced at DB level
 * (FR-PRD-003). Optimistic lock_version guards concurrent edits.
 *
 * @property string $public_id
 * @property string $sku
 */
final class ProductVariant extends Model
{
    use HasPublicId;

    protected $table = 'product_variants';

    protected $fillable = [
        'public_id', 'product_id', 'sku', 'barcode', 'status',
        'weight_grams', 'is_default', 'lock_version',
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'lock_version' => 'integer'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
