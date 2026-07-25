<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Product\Enums\ProductStatus;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Support\Uuid\PublicId;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds obviously-synthetic sample catalog data (prompt §22): categories,
 * brands, published products with variants and prices. Development data only.
 */
final class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $priceListId = DB::table('price_lists')->updateOrInsert(
            ['code' => 'default'],
            ['public_id' => PublicId::generate(), 'currency' => 'ETB', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        );
        $priceListId = DB::table('price_lists')->where('code', 'default')->value('id');

        DB::table('tax_classes')->updateOrInsert(['code' => 'standard'], ['name' => 'Standard (DEMO)', 'is_inclusive' => false, 'created_at' => now(), 'updated_at' => now()]);
        $taxClassId = DB::table('tax_classes')->where('code', 'standard')->value('id');
        DB::table('tax_rules')->updateOrInsert(
            ['tax_class_id' => $taxClassId, 'jurisdiction' => 'ET'],
            ['rate' => '0.150000', 'effective_from' => now()->subYear(), 'created_at' => now(), 'updated_at' => now()], // 15% DEMO VAT (D-008)
        );

        $categories = ['electronics' => 'Electronics', 'home' => 'Home & Kitchen', 'fashion' => 'Fashion'];
        $catIds = [];
        foreach ($categories as $slug => $name) {
            $cat = Category::query()->updateOrCreate(
                ['slug' => $slug],
                ['public_id' => PublicId::generate(), 'status' => 'active', 'depth' => 0, 'sort_order' => 0, 'path' => $slug],
            );
            DB::table('category_translations')->updateOrInsert(
                ['category_id' => $cat->id, 'locale' => 'en'],
                ['name' => $name, 'created_at' => now(), 'updated_at' => now()],
            );
            DB::table('category_translations')->updateOrInsert(
                ['category_id' => $cat->id, 'locale' => 'am'],
                ['name' => $name.' (አማ)', 'created_at' => now(), 'updated_at' => now()],
            );
            $catIds[$slug] = $cat->id;
        }

        $brand = Brand::query()->updateOrCreate(['slug' => 'demo-brand'], ['public_id' => PublicId::generate(), 'status' => 'active']);
        DB::table('brand_translations')->updateOrInsert(['brand_id' => $brand->id, 'locale' => 'en'], ['name' => 'Demo Brand', 'created_at' => now(), 'updated_at' => now()]);

        $samples = [
            ['name' => 'Sample Wireless Earbuds (DEMO)', 'cat' => 'electronics', 'sku' => 'DEMO-EARBUDS-01', 'price' => '2499.00'],
            ['name' => 'Sample Cooking Pot Set (DEMO)', 'cat' => 'home', 'sku' => 'DEMO-POTSET-01', 'price' => '1899.00'],
            ['name' => 'Sample Cotton T-Shirt (DEMO)', 'cat' => 'fashion', 'sku' => 'DEMO-TSHIRT-01', 'price' => '599.00'],
        ];

        foreach ($samples as $i => $s) {
            $product = Product::query()->updateOrCreate(
                ['public_id' => 'seed-product-'.($i + 1)],
                [
                    'category_id' => $catIds[$s['cat']],
                    'brand_id' => $brand->id,
                    'status' => ProductStatus::Published->value,
                    'type' => 'standard',
                ],
            );
            DB::table('product_translations')->updateOrInsert(
                ['product_id' => $product->id, 'locale' => 'en'],
                ['name' => $s['name'], 'slug' => str($s['name'])->slug()->value(), 'short_description' => 'A synthetic demo product.', 'created_at' => now(), 'updated_at' => now()],
            );
            DB::table('product_policy_metadata')->updateOrInsert(
                ['product_id' => $product->id],
                ['return_window_days' => 30, 'is_returnable' => true, 'tax_class' => 'standard', 'policy_version' => 1, 'created_at' => now(), 'updated_at' => now()],
            );

            $variant = ProductVariant::query()->updateOrCreate(
                ['sku' => $s['sku']],
                ['public_id' => 'seed-variant-'.($i + 1), 'product_id' => $product->id, 'status' => 'active', 'is_default' => true],
            );

            DB::table('prices')->updateOrInsert(
                ['price_list_id' => $priceListId, 'product_variant_id' => $variant->id],
                ['amount' => $s['price'], 'currency' => 'ETB', 'status' => 'active', 'effective_from' => now()->subDay(), 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }
}
