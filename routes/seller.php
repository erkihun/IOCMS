<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Seller portal routes (Marketplace — feature-gated)
|--------------------------------------------------------------------------
| LLD §8.1: web, seller-auth/membership, seller scope, permission, feature gate.
| The entire seller channel is gated behind feature('marketplace') in
| bootstrap/app.php (SDD ADR-020, decision D-005).
*/

foreach (glob(__DIR__.'/seller/*.php') ?: [] as $moduleRoutes) {
    require $moduleRoutes;
}
