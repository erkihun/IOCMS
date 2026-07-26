<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserSession;
use App\Domain\Identity\Policies\RolePolicy;
use App\Domain\Identity\Policies\SessionPolicy;
use App\Domain\Identity\Policies\UserPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Explicit policy map. The Domain-layout namespaces are not auto-discovered
     * by Laravel's convention, so policies are registered here (SDD ADR-016).
     *
     * @var array<class-string, class-string>
     */
    private const array POLICIES = [
        User::class => UserPolicy::class,
        UserSession::class => SessionPolicy::class,
        Role::class => RolePolicy::class,
    ];

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

        foreach (self::POLICIES as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // Enforce HTTPS in production (NFR-SE-002).
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
