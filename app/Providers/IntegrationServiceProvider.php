<?php

declare(strict_types=1);

namespace App\Providers;

use App\Platform\Integration\Adapters\FakePaymentGateway;
use App\Platform\Integration\Ports\PaymentGateway;
use Illuminate\Support\ServiceProvider;

/**
 * Binds provider-neutral ports to concrete adapters (SDD ADR-007, decision
 * D-006/007). The online payment adapter is config-gated; until a licensed
 * provider is configured, the fake/sandbox adapter is used so the full webhook
 * + reconciliation machinery is exercised without external credentials.
 */
final class IntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, function (): PaymentGateway {
            $driver = (string) config('services.payment.driver', 'fake');

            return match ($driver) {
                // 'licensed' => new LicensedProviderGateway(...),  // requires credentials + contract
                default => new FakePaymentGateway,
            };
        });
    }
}
