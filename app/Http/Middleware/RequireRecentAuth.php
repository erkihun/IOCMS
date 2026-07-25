<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Exceptions\DomainException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires recent reauthentication for high-risk actions such as bank changes,
 * large refunds, payout approval, MFA reset and role elevation (NFR-SE-011).
 */
final class RequireRecentAuth
{
    private const int WINDOW_SECONDS = 600; // 10 minutes

    public function handle(Request $request, Closure $next): Response
    {
        $confirmedAt = $request->session()->get('auth.reauth_confirmed_at');
        if (! $confirmedAt || (now()->timestamp - (int) $confirmedAt) > self::WINDOW_SECONDS) {
            throw new DomainException('identity.reauth_required', 'Please re-enter your password to continue.', 403);
        }

        return $next($request);
    }
}
