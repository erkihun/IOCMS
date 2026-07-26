<?php

declare(strict_types=1);

use App\Domain\Identity\Actions\CompleteMfaChallengeAction;
use App\Domain\Identity\Actions\EnrollMfaAction;
use App\Domain\Identity\Models\Authenticator;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\TotpService;
use App\Platform\Integration\Models\OutboxEvent;
use App\Support\Exceptions\BusinessRuleException;
use App\Support\Uuid\PublicId;

/**
 * FR-IAM-005 / NFR-SE-009/011 — MFA enrollment, challenge and step-up.
 * Test basis T-FR-IAM-005 (enrollment, recovery, step-up).
 */
function mfaUser(bool $enrolled = false): User
{
    return User::query()->create([
        'public_id' => PublicId::generate(), 'type' => 'staff', 'status' => 'active',
        'email' => 'mfa'.random_int(1, 99999).'@example.com', 'locale' => 'en',
        'password_hash' => bcrypt('x'), 'email_verified_at' => now(), 'mfa_enrolled' => $enrolled,
    ]);
}

it('enrolls TOTP: begin returns a provisioning URI and a pending authenticator', function () {
    $user = mfaUser();

    $result = $this->actingAs($user->fresh())->postJson('/mfa/enroll')->assertOk()->json();

    expect($result['provisioning_uri'])->toStartWith('otpauth://totp/');
    $auth = Authenticator::query()->where('public_id', $result['authenticator_public_id'])->firstOrFail();
    expect($auth->status->value)->toBe('pending')
        ->and($user->fresh()->mfa_enrolled)->toBeFalse(); // not enrolled until confirmed
});

it('confirms enrollment with a valid TOTP code, activating MFA + emitting an event', function () {
    $user = mfaUser();
    $enroll = app(EnrollMfaAction::class);
    $begin = $enroll->begin($user);

    $auth = Authenticator::query()->where('public_id', $begin['authenticator_public_id'])->firstOrFail();
    $code = app(TotpService::class)->codeAt((string) $auth->secret);

    $enroll->confirm($user, $begin['authenticator_public_id'], $code);

    expect($user->fresh()->mfa_enrolled)->toBeTrue()
        ->and($auth->fresh()->status->value)->toBe('active');
    expect(OutboxEvent::query()->where('event_name', 'MfaEnrolled')->exists())->toBeTrue();
});

it('rejects enrollment confirmation with a wrong code (stays pending)', function () {
    $user = mfaUser();
    $begin = app(EnrollMfaAction::class)->begin($user);

    expect(fn () => app(EnrollMfaAction::class)->confirm($user, $begin['authenticator_public_id'], '000000'))
        ->toThrow(BusinessRuleException::class);
    expect($user->fresh()->mfa_enrolled)->toBeFalse();
});

it('completes a challenge with a valid code and marks the session confirmed for step-up', function () {
    $user = mfaUser();
    $begin = app(EnrollMfaAction::class)->begin($user);
    $auth = Authenticator::query()->where('public_id', $begin['authenticator_public_id'])->firstOrFail();
    $totp = app(TotpService::class);
    app(EnrollMfaAction::class)->confirm($user, $begin['authenticator_public_id'], $totp->codeAt((string) $auth->secret));

    $code = $totp->codeAt((string) $auth->fresh()->secret);

    $this->actingAs($user->fresh())
        ->post('/mfa/challenge', ['code' => $code])
        ->assertRedirect();

    expect(session('auth.mfa_confirmed'))->toBeTrue()
        ->and(session('auth.reauth_confirmed_at'))->not->toBeNull(); // step-up recorded (NFR-SE-011)
});

it('rejects a challenge with a wrong code and audits the failure', function () {
    $user = mfaUser();
    $begin = app(EnrollMfaAction::class)->begin($user);
    $auth = Authenticator::query()->where('public_id', $begin['authenticator_public_id'])->firstOrFail();
    app(EnrollMfaAction::class)->confirm($user, $begin['authenticator_public_id'], app(TotpService::class)->codeAt((string) $auth->secret));

    expect(fn () => app(CompleteMfaChallengeAction::class)->execute($user->fresh(), '000000'))
        ->toThrow(BusinessRuleException::class);

    expect(DB::table('audit_events')->where('action', 'identity.mfa_challenge_failed')->exists())->toBeTrue();
});

it('verifies TOTP codes deterministically at a fixed time (RFC 6238)', function () {
    $totp = new TotpService;
    $secret = $totp->generateSecret();
    $ts = 1_700_000_000;
    $code = $totp->codeAt($secret, $ts);

    expect($totp->verify($secret, $code, $ts))->toBeTrue()
        ->and($totp->verify($secret, '999999', $ts))->toBeFalse()
        ->and(strlen($code))->toBe(6);
});
