<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Administration\Services\FeatureFlagEvaluator;
use App\Support\Exceptions\DomainException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route behind a feature flag (FR-ADM-005). The marketplace phase is
 * gated here (decision D-005 / SDD ADR-020) and returns 403 when disabled.
 */
final class EnsureFeatureEnabled
{
    public function __construct(private readonly FeatureFlagEvaluator $flags) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! $this->flags->isEnabled($feature, $request->user())) {
            throw new DomainException('feature.disabled', 'This capability is not currently available.', 403);
        }

        return $next($request);
    }
}
