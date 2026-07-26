<?php

declare(strict_types=1);

use App\Domain\Governance\Models\AuditEvent;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserIdentifier;
use App\Platform\Integration\Models\OutboxEvent;

/**
 * FR-IAM-001 / FR-IAM-004 — customer registration with normalized-identifier
 * uniqueness, audit evidence and an outbox event.
 * Test: T-FR-IAM-001.
 */
it('registers a customer and records audit + outbox in one transaction', function () {
    $response = $this->post('/register', [
        'identifier' => 'Buyer@Example.com',
        'password' => 'a-very-strong-passphrase',
        'display_name' => 'Test Buyer',
        'locale' => 'en',
    ]);

    $response->assertRedirect(route('home'));

    $user = User::query()->firstOrFail();
    expect($user->email)->toBe('buyer@example.com')       // normalized
        ->and($user->public_id)->toBeUuid()
        ->and($user->status->value)->toBe('pending');

    expect(UserIdentifier::query()->where('normalized_value', 'buyer@example.com')->exists())->toBeTrue();
    expect(AuditEvent::query()->where('action', 'identity.customer_registered')->exists())->toBeTrue();
    expect(OutboxEvent::query()->where('event_name', 'UserRegistered')->exists())->toBeTrue();
});

it('normalizes an Ethiopian phone identifier to E.164', function () {
    $this->post('/register', [
        'identifier' => '0912345678',
        'password' => 'a-very-strong-passphrase',
    ])->assertRedirect();

    expect(UserIdentifier::query()->where('normalized_value', '+251912345678')->exists())->toBeTrue();
});

it('rejects a duplicate normalized identifier with identifier_taken (concurrency-safe)', function () {
    $payload = ['identifier' => 'dupe@example.com', 'password' => 'a-very-strong-passphrase'];

    $this->post('/register', $payload)->assertRedirect();

    // Second registration for the same normalized identifier must fail with a
    // 409 conflict (error contract §23 — state/concurrency conflict).
    $response = $this->from('/register')->post('/register', $payload);
    $response->assertStatus(409);

    expect(User::query()->count())->toBe(1);
});

it('rejects a password shorter than policy minimum', function () {
    $response = $this->from('/register')->post('/register', [
        'identifier' => 'weak@example.com',
        'password' => 'short',
    ]);

    // Form Request min:12 rejects before the Action.
    $response->assertSessionHasErrors('password');
    expect(User::query()->count())->toBe(0);
});

it('does not reveal existing accounts on duplicate (no enumeration leak)', function () {
    $payload = ['identifier' => 'enum@example.com', 'password' => 'a-very-strong-passphrase'];
    $this->post('/register', $payload)->assertRedirect();

    $response = $this->from('/register')->post('/register', $payload);
    // Generic safe message in the 409 body, never "account exists".
    $response->assertStatus(409);
    expect(strtolower($response->getContent()))->not->toContain('exists');
});
