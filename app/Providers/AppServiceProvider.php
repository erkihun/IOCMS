<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Fail loudly on lazy-loading, missing attributes and bad assignments in
        // non-production so boundary/query issues surface in dev and tests
        // (SDD §15.2 "prevent lazy-loading surprises").
        Model::shouldBeStrict(! $this->app->isProduction());

        // Enforce HTTPS in production (NFR-SE-002).
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
