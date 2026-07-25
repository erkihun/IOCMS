<?php

declare(strict_types=1);

use App\Domain\Catalog\Queries\StorefrontStatsQuery;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Customer & guest storefront routes
|--------------------------------------------------------------------------
| LLD §8.1 web channel: web, locale, correlation, throttle, optional auth, CSRF
| (applied via the `web` group in bootstrap/app.php). Each module registers its
| customer-facing routes in routes/web/<module>.php.
*/

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'stats' => app(StorefrontStatsQuery::class)->handle(),
    ]);
})->name('home');

foreach (glob(__DIR__.'/web/*.php') ?: [] as $moduleRoutes) {
    require $moduleRoutes;
}
