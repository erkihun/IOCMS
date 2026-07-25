<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Versioned machine/mobile API routes (api/v1)
|--------------------------------------------------------------------------
| LLD §8.1/§8.2: API auth, scope, rate limit, idempotency, JSON schema,
| correlation. All external references are stable public IDs (FR-INT-004).
*/

foreach (glob(__DIR__.'/api/*.php') ?: [] as $moduleRoutes) {
    require $moduleRoutes;
}
