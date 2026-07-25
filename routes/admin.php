<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Platform staff (admin) routes
|--------------------------------------------------------------------------
| LLD §8.1 admin channel: web, auth, verified, MFA by role, permission, scope,
| audit context. The route group + middleware are applied in bootstrap/app.php.
| Each module registers its admin routes in routes/admin/<module>.php.
*/

foreach (glob(__DIR__.'/admin/*.php') ?: [] as $moduleRoutes) {
    require $moduleRoutes;
}
