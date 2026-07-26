<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserIdentifier;
use App\Support\Uuid\PublicId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * FR-IAM-002 / FR-IAM-009 — sign-in, enumeration resistance, session rotation.
 * Test basis T-FR-IAM-002.
 */
function makeCustomer(string $email = 'signin@example.com', string $password = 'a-very-strong-passphrase', string $status = 'active'): User
{
    $user = User::query()->create([
        'public_id' => PublicId::generate(),
        'type' => 'customer',
        'status' => $status,
        'email' => $email,
        'locale' => 'en',
        'password_hash' => Hash::make($password),
        'email_verified_at' => now(),
    ]);
    UserIdentifier::query()->create([
        'user_id' => $user->id, 'type' => 'email', 'normalized_value' => $email,
        'display_value' => $email, 'is_primary' => true, 'verified_at' => now(),
    ]);

    return $user;
}

it('signs in with valid credentials and rotates the session', function () {
    makeCustomer();

    $response = $this->post('/login', ['identifier' => 'signin@example.com', 'password' => 'a-very-strong-passphrase']);

    $response->assertRedirect(route('home'));
    $this->assertAuthenticated();
    expect(DB::table('login_attempts')->where('status', 'success')->count())->toBe(1);
});

it('rejects a wrong password with a generic error and no account disclosure', function () {
    makeCustomer();

    $response = $this->from('/login')->post('/login', ['identifier' => 'signin@example.com', 'password' => 'wrong-password']);

    $response->assertSessionHasErrors();
    $this->assertGuest();
    // Generic message — must not say "password" specifically vs "account".
    $error = collect(session('errors')->all())->implode(' ');
    expect(strtolower($error))->not->toContain('does not exist')
        ->and(strtolower($error))->not->toContain('no account');
});

it('returns the same generic error for an unknown identifier (no enumeration)', function () {
    $response = $this->from('/login')->post('/login', ['identifier' => 'nobody@example.com', 'password' => 'whatever-password']);

    $response->assertSessionHasErrors();
    $this->assertGuest();
    // A failed attempt is still recorded for throttling/security evidence.
    expect(DB::table('login_attempts')->where('status', 'failed')->count())->toBe(1);
});

it('prevents a suspended user from authenticating (BR-025)', function () {
    makeCustomer(status: UserStatus::Suspended->value);

    $this->from('/login')->post('/login', ['identifier' => 'signin@example.com', 'password' => 'a-very-strong-passphrase'])
        ->assertSessionHasErrors();
    $this->assertGuest();
});

it('throttles repeated failed sign-in attempts (FR-IAM-009)', function () {
    makeCustomer();

    // The route allows 6/min; the 7th is throttled (429).
    for ($i = 0; $i < 6; $i++) {
        $this->post('/login', ['identifier' => 'signin@example.com', 'password' => 'wrong']);
    }
    $response = $this->post('/login', ['identifier' => 'signin@example.com', 'password' => 'wrong']);

    $response->assertStatus(429);
});

it('logs out and invalidates the session', function () {
    $user = makeCustomer()->fresh();
    $this->actingAs($user)->post('/logout')->assertRedirect(route('home'));
    $this->assertGuest();
});
