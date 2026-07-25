<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the request locale from (1) authenticated user preference,
 * (2) session, (3) Accept-Language, falling back to the configured primary
 * language (FR-LOC-003). Switching locale never mutates stored timestamps or
 * historical evidence (BR-045); it only affects presentation.
 */
final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $enabled = config('iocms.locales.enabled', ['en', 'am']);
        $default = config('iocms.locales.default', 'en');

        $user = $request->user();
        $locale = $user->locale
            ?? $request->session()->get('locale')
            ?? $request->getPreferredLanguage($enabled)
            ?? $default;

        if (! in_array($locale, $enabled, true)) {
            $locale = $default;
        }

        app()->setLocale($locale);
        $request->session()->put('locale', $locale);

        return $next($request);
    }
}
