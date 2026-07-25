<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Exceptions\DomainException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires satisfied MFA for privileged roles (NFR-SE-009, FR-IAM-005).
 * Returns `identity.mfa_required` (403) prompting step-up enrollment/challenge.
 */
final class RequireMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && method_exists($user, 'mfaSatisfied') && ! $user->mfaSatisfied($request)) {
            throw new DomainException('identity.mfa_required', 'Multi-factor authentication is required.', 403);
        }

        return $next($request);
    }
}
