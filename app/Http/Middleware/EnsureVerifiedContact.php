<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Exceptions\DomainException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires a verified contact channel before protected staff/seller capabilities
 * (FR-IAM-003). Customers may browse unverified; privileged channels may not.
 */
final class EnsureVerifiedContact
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && method_exists($user, 'hasVerifiedContact') && ! $user->hasVerifiedContact()) {
            throw new DomainException('identity.contact_unverified', 'Please verify your contact channel first.', 403);
        }

        return $next($request);
    }
}
