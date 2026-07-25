<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Provider webhook routes
|--------------------------------------------------------------------------
| LLD §8.1/§8.3: raw-body capture, provider identification, signature/replay
| verification, size limit, durable inbox. Never trusts a browser redirect
| as payment evidence (BR-004).
*/

foreach (glob(__DIR__.'/webhooks/*.php') ?: [] as $moduleRoutes) {
    require $moduleRoutes;
}
