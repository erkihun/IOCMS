<?php

declare(strict_types=1);

namespace App\Platform\Integration\Ports;

use App\Support\Money\Money;

/**
 * Provider-neutral payment port (FR-PAY-001, SDD ADR-007/008). Domain code
 * depends on this interface, never on a provider SDK. Concrete adapters
 * (fake/sandbox/licensed) live under Platform\Integration\Adapters and are
 * config-gated. A browser redirect is never authoritative (BR-004) — payment
 * status only advances on {@see verifyWebhook} evidence or server confirmation.
 */
interface PaymentGateway
{
    public function name(): string;

    /**
     * Initiate a payment; returns a redirect/hosted URL or reference. Never
     * returns a "paid" outcome — that arrives asynchronously via webhook.
     *
     * @param  array<string, mixed>  $context
     * @return array{provider_reference: string, redirect_url: string|null}
     */
    public function initiate(string $orderNumber, Money $amount, array $context = []): array;

    /**
     * Verify an inbound webhook's authenticity (signature + timestamp/replay
     * window). Returns the normalized event, or throws on invalid signature.
     *
     * @param  array<string, string>  $headers
     * @return array{provider_event_id: string, event_type: string, provider_reference: string, amount: string|null, currency: string|null, signature_valid: bool}
     */
    public function verifyWebhook(string $rawBody, array $headers): array;
}
