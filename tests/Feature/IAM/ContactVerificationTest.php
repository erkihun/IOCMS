<?php

declare(strict_types=1);

use App\Domain\Identity\Models\ContactVerification;
use App\Domain\Identity\Models\User;
use App\Platform\Integration\Models\OutboxEvent;
use App\Support\Uuid\PublicId;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * FR-IAM-003 — time-limited, single-use contact verification tokens.
 * Test basis T-FR-IAM-003 (token expiry + replay).
 */
function pendingUserWithToken(string $rawToken, ?CarbonInterface $expiresAt = null): User
{
    $user = User::query()->create([
        'public_id' => PublicId::generate(), 'type' => 'customer', 'status' => 'pending',
        'email' => 'v@example.com', 'locale' => 'en', 'password_hash' => bcrypt('x'),
    ]);
    ContactVerification::query()->create([
        'public_id' => PublicId::generate(),
        'user_id' => $user->id,
        'channel' => 'email',
        'token_hash' => hash('sha256', $rawToken),
        'status' => 'pending',
        'expires_at' => $expiresAt ?? now()->addHour(),
    ]);

    return $user;
}

it('verifies a contact with a valid token, activates the user and emits an event', function () {
    $user = pendingUserWithToken('valid-token-1234567890');

    $this->post('/verification/confirm', ['token' => 'valid-token-1234567890'])
        ->assertRedirect(route('login'));

    $user->refresh();
    expect($user->email_verified_at)->not->toBeNull()
        ->and($user->status->value)->toBe('active');
    expect(OutboxEvent::query()->where('event_name', 'ContactVerified')->exists())->toBeTrue();
});

it('rejects an expired token', function () {
    pendingUserWithToken('expired-token-1234567890', now()->subMinute());

    $this->from('/login')->post('/verification/confirm', ['token' => 'expired-token-1234567890'])
        ->assertSessionHas('error');
});

it('rejects replay of an already-used token', function () {
    pendingUserWithToken('single-use-token-1234567890');

    // First use succeeds.
    $this->post('/verification/confirm', ['token' => 'single-use-token-1234567890']);
    // Second use (replay) is rejected.
    $this->from('/login')->post('/verification/confirm', ['token' => 'single-use-token-1234567890'])
        ->assertSessionHas('error');

    expect(ContactVerification::query()->where('status', 'used')->count())->toBe(1);
});

it('rejects an unknown token without disclosing account existence', function () {
    $this->from('/login')->post('/verification/confirm', ['token' => 'unknown-token-1234567890'])
        ->assertSessionHas('error');

    expect(DB::table('users')->whereNotNull('email_verified_at')->count())->toBe(0);
});
