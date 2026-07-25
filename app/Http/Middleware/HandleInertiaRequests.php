<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Administration\Services\FeatureFlagEvaluator;
use App\Support\Correlation\CorrelationContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Shares typed props with every Inertia page (LLD §3.8): app config, the
 * authenticated actor (with permissions/scopes for UI affordance only — never
 * authorization), flash messages, feature flags and the correlation ID.
 *
 * UI visibility derived from these props is a convenience, not a security
 * boundary; every protected server action re-authorizes (NFR-SE-014).
 */
final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
                'locale' => app()->getLocale(),
                'available_locales' => config('iocms.locales.enabled', ['en', 'am']),
                'currency' => config('iocms.currency.default', 'ETB'),
            ],
            'auth' => [
                'user' => $user ? [
                    'public_id' => $user->public_id,
                    'display_name' => $user->display_name ?? $user->email ?? 'User',
                    'type' => $user->type ?? 'customer',
                    'locale' => $user->locale ?? app()->getLocale(),
                    'permissions' => method_exists($user, 'effectivePermissionCodes')
                        ? $user->effectivePermissionCodes()
                        : [],
                    'scopes' => method_exists($user, 'scopeDescriptors')
                        ? $user->scopeDescriptors()
                        : [],
                    'mfa_enrolled' => (bool) ($user->mfa_enrolled ?? false),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
            ],
            'features' => fn () => app(FeatureFlagEvaluator::class)->allForActor($user),
            'correlation_id' => CorrelationContext::id(),
        ];
    }
}
