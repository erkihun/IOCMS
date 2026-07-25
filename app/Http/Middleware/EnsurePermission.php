<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Exceptions\AuthorizationException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deny-by-default permission check (NFR-SE-012). UI visibility is never
 * authorization; the effective permission must be present on the actor.
 */
final class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        if (! $user || ! method_exists($user, 'hasPermission') || ! $user->hasPermission($permission)) {
            throw new AuthorizationException('authorization.permission_denied', 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
