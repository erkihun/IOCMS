<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserSession;
use App\Platform\Integration\Models\OutboxEvent;
use App\Support\Uuid\PublicId;

/**
 * FR-IAM-008 — list & revoke active sessions with owner scope (NFR-SE-013 IDOR).
 * Includes the required scope-isolation negative test.
 */
function makeUser(): User
{
    return User::query()->create([
        'public_id' => PublicId::generate(), 'type' => 'customer', 'status' => 'active',
        'email' => 'u'.random_int(1, 99999).'@example.com', 'locale' => 'en', 'password_hash' => bcrypt('x'),
        'email_verified_at' => now(),
    ]);
}

function makeSession(User $user): UserSession
{
    return UserSession::query()->create([
        'public_id' => PublicId::generate(), 'user_id' => $user->id,
        'session_token_hash' => hash('sha256', 'tok-'.random_int(1, 99999)),
        'ip_address' => '10.0.0.1', 'user_agent' => 'Test', 'last_active_at' => now(),
    ]);
}

it('lists only the current user active sessions', function () {
    $user = makeUser();
    makeSession($user);
    makeSession($user);
    makeSession(makeUser()); // another user's session

    $this->actingAs($user->fresh())
        ->get('/account/sessions')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Account/Sessions')->has('sessions.data', 2));
});

it('revokes the user own session and emits an event', function () {
    $user = makeUser();
    $session = makeSession($user);

    $this->actingAs($user->fresh())
        ->delete("/account/sessions/{$session->public_id}")
        ->assertRedirect();

    expect($session->fresh()->revoked_at)->not->toBeNull();
    expect(OutboxEvent::query()->where('event_name', 'SessionRevoked')->exists())->toBeTrue();
});

it('forbids revoking another user session (IDOR / scope isolation — NFR-SE-013)', function () {
    $owner = makeUser();
    $attacker = makeUser();
    $ownersSession = makeSession($owner);

    // The attacker has a valid session public id but is not the owner.
    $this->actingAs($attacker->fresh())
        ->delete("/account/sessions/{$ownersSession->public_id}")
        ->assertForbidden();

    // The owner's session remains active.
    expect($ownersSession->fresh()->revoked_at)->toBeNull();
});

it('requires authentication to view sessions', function () {
    $this->get('/account/sessions')->assertRedirect(); // redirected to login by auth middleware
});

it('is idempotent when revoking an already revoked session', function () {
    $user = makeUser();
    $session = makeSession($user);
    $session->forceFill(['revoked_at' => now()])->save();

    $this->actingAs($user->fresh())
        ->delete("/account/sessions/{$session->public_id}")
        ->assertRedirect();

    expect(OutboxEvent::query()->where('event_name', 'SessionRevoked')->count())->toBe(0);
});
