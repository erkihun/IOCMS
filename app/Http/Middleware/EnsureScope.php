<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Exceptions\AuthorizationException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Record-scope guard (FR-IAM-012, BR-026, NFR-SE-013). Ensures the actor's scope
 * contains the target for seller and operational users. Fine-grained per-record
 * checks are additionally enforced by Policies.
 */
final class EnsureScope
{
    public function handle(Request $request, Closure $next, string $scopeType): Response
    {
        $user = $request->user();
        if (! $user || ! method_exists($user, 'hasScopeType') || ! $user->hasScopeType($scopeType)) {
            throw new AuthorizationException('authorization.out_of_scope', 'The record is outside your assigned scope.');
        }

        return $next($request);
    }
}
