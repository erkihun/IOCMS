<?php

declare(strict_types=1);

use App\Http\Middleware\AssignCorrelationId;
use App\Http\Middleware\EnsureAuthenticated;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureScope;
use App\Http\Middleware\EnsureVerifiedContact;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireIdempotencyKey;
use App\Http\Middleware\RequireMfa;
use App\Http\Middleware\RequireRecentAuth;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Support\Correlation\CorrelationContext;
use App\Support\Exceptions\DomainException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // LLD §8.1 channel route files with dedicated middleware groups.
            Route::middleware('admin')
                ->group(base_path('routes/admin.php'));
            Route::middleware('warehouse')
                ->group(base_path('routes/warehouse.php'));
            Route::middleware('seller')
                ->group(base_path('routes/seller.php'));
            Route::prefix('webhooks')
                ->middleware('webhooks')
                ->group(base_path('routes/webhooks.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Correlation + security headers + locale on every web request; Inertia last.
        $middleware->web(append: [
            AssignCorrelationId::class,
            SecurityHeaders::class,
            SetLocale::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->api(prepend: [
            AssignCorrelationId::class,
            SecurityHeaders::class,
        ]);

        // Named middleware groups for the channel route files (LLD §8.1).
        $middleware->appendToGroup('admin', [
            'web',
            EnsureAuthenticated::class,
            EnsureVerifiedContact::class,
        ]);
        $middleware->appendToGroup('warehouse', [
            'web',
            EnsureAuthenticated::class,
            EnsureVerifiedContact::class,
        ]);
        $middleware->appendToGroup('seller', [
            'web',
            EnsureFeatureEnabled::class.':marketplace',
        ]);
        $middleware->appendToGroup('webhooks', [
            AssignCorrelationId::class,
        ]);

        // Route middleware aliases used by module route declarations.
        $middleware->alias([
            'permission' => EnsurePermission::class,
            'scope' => EnsureScope::class,
            'mfa' => RequireMfa::class,
            'reauth' => RequireRecentAuth::class,
            'feature' => EnsureFeatureEnabled::class,
            'verified.contact' => EnsureVerifiedContact::class,
            'idempotency' => RequireIdempotencyKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Render the IOCMS error contract for expected business failures (LLD §3.7).
        $exceptions->render(function (DomainException $e, Request $request): ?Response {
            $localized = trans()->has($e->translationKey())
                ? (string) trans($e->translationKey())
                : $e->getMessage();

            if ($request->expectsJson() || $request->is('api/*', 'webhooks/*')) {
                return response()->json($e->toErrorArray($localized), $e->status);
            }

            return back()->withErrors(
                $e->fields ?: ['error' => $localized]
            )->with('error', $localized);
        });

        // Never leak internals; ensure correlation ID accompanies unexpected errors.
        $exceptions->context(fn (): array => ['correlation_id' => CorrelationContext::id()]);
    })->create();
