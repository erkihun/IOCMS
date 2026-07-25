<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Exceptions\BusinessRuleException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires an Idempotency-Key header on retryable material POSTs (FR-INT-006,
 * FR-CHK-008, LLD §3.6). The Action resolves the key against stored fingerprints.
 */
final class RequireIdempotencyKey
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasHeader('Idempotency-Key') && ! $request->filled('idempotency_key')) {
            throw new BusinessRuleException('integration.idempotency_key_required', 'An idempotency key is required for this operation.');
        }

        return $next($request);
    }
}
