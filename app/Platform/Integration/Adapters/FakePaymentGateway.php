<?php

declare(strict_types=1);

namespace App\Platform\Integration\Adapters;

use App\Platform\Integration\Ports\PaymentGateway;
use App\Support\Exceptions\BusinessRuleException;
use App\Support\Money\Money;
use App\Support\Uuid\PublicId;

/**
 * Deterministic fake payment gateway for tests and local development
 * (SDD ADR-007, decision D-006). Signs webhooks with HMAC-SHA256 over the raw
 * body using a dev secret and enforces a replay window, mirroring a real
 * provider so the webhook-processing path is exercised authentically. No real
 * money moves and no card data is handled (BR-023).
 */
final class FakePaymentGateway implements PaymentGateway
{
    private const string SECRET = 'fake-provider-dev-secret';

    private const int REPLAY_WINDOW_SECONDS = 300;

    public function name(): string
    {
        return 'fake';
    }

    public function initiate(string $orderNumber, Money $amount, array $context = []): array
    {
        return [
            'provider_reference' => 'fake_'.substr(PublicId::generate(), 0, 12),
            'redirect_url' => null, // hosted flow simulated; no redirect needed in tests
        ];
    }

    public function verifyWebhook(string $rawBody, array $headers): array
    {
        $timestamp = (int) ($headers['x-fake-timestamp'] ?? 0);
        $signature = $headers['x-fake-signature'] ?? '';

        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, self::SECRET);
        $signatureValid = hash_equals($expected, $signature);

        if (! $signatureValid) {
            throw new BusinessRuleException('integration.signature_invalid', 'Webhook signature validation failed.');
        }

        if (abs(time() - $timestamp) > self::REPLAY_WINDOW_SECONDS) {
            throw new BusinessRuleException('integration.signature_invalid', 'Webhook timestamp outside the replay window.');
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);

        return [
            'provider_event_id' => (string) ($data['event_id'] ?? ''),
            'event_type' => (string) ($data['type'] ?? ''),
            'provider_reference' => (string) ($data['provider_reference'] ?? ''),
            'amount' => isset($data['amount']) ? (string) $data['amount'] : null,
            'currency' => isset($data['currency']) ? (string) $data['currency'] : null,
            'signature_valid' => true,
        ];
    }

    /**
     * Test helper: build a signed webhook (raw body + headers) as a provider would.
     *
     * @param  array<string, mixed>  $payload
     * @return array{body: string, headers: array<string, string>}
     */
    public static function signWebhook(array $payload, ?int $timestamp = null): array
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp ??= time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, self::SECRET);

        return [
            'body' => $body,
            'headers' => [
                'x-fake-timestamp' => (string) $timestamp,
                'x-fake-signature' => $signature,
            ],
        ];
    }
}
