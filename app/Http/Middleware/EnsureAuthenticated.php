<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Exceptions\DomainException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Requires an authenticated actor (HTTP 401) — NFR-SE-012, LLD §9.1. */
final class EnsureAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            throw new DomainException('identity.unauthenticated', 'Authentication is required.', 401);
        }

        return $next($request);
    }
}
