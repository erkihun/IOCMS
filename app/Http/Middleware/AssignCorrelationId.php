<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Correlation\CorrelationContext;
use App\Support\Uuid\PublicId;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Establishes a correlation ID for every request and echoes it on the response
 * (BR-050, FR-AUD-009, FR-INT-009, SDD ADR-019). An inbound `X-Correlation-Id`
 * from a trusted client is honored when it is a valid UUID; otherwise a UUIDv7
 * is generated. The ID is shared with logs, jobs, outbox events and audit.
 */
final class AssignCorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->header('X-Correlation-Id');
        $correlationId = ($incoming && PublicId::isValid($incoming))
            ? $incoming
            : PublicId::generate();

        CorrelationContext::set($correlationId);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Correlation-Id', $correlationId);

        return $response;
    }
}
