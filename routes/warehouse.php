<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Warehouse & delivery operations routes
|--------------------------------------------------------------------------
| LLD §8.1: web, auth, verified, operation scope, device/session controls.
*/

foreach (glob(__DIR__.'/warehouse/*.php') ?: [] as $moduleRoutes) {
    require $moduleRoutes;
}
