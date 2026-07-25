<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Queries;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only storefront summary counts for the home page. Defensive against
 * tables that do not yet exist so the home route is safe before Catalog seeding.
 * A Query builds read models only and never mutates state (LLD §3.2).
 */
final class StorefrontStatsQuery
{
    /**
     * @return array{products: int, categories: int}
     */
    public function handle(): array
    {
        return [
            'products' => $this->countWhere('products', 'status', 'published'),
            'categories' => $this->countWhere('categories', 'status', 'active'),
        ];
    }

    private function countWhere(string $table, string $column, string $value): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return (int) DB::table($table)->where($column, $value)->count();
    }
}
