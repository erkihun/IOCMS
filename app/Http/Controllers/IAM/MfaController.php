<?php

declare(strict_types=1);

namespace App\Http\Controllers\IAM;

use App\Domain\Identity\Actions\CompleteMfaChallengeAction;
use App\Domain\Identity\Actions\EnrollMfaAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\IAM\CompleteMfaChallengeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * MFA enrollment + challenge transport (FR-IAM-005, NFR-SE-009/011). Enrollment
 * is a two-step begin/confirm. A successful challenge marks the session as MFA-
 * confirmed and records the recent-reauthentication timestamp used by the
 * step-up guard (RequireRecentAuth). Codes/secrets are never returned to logs.
 */
final class MfaController extends Controller
{
    public function begin(Request $request, EnrollMfaAction $action): JsonResponse
    {
        $result = $action->begin($request->user());

        // The provisioning URI is shown once to the enrolling user only.
        return response()->json($result);
    }

    public function confirmEnrollment(Request $request, EnrollMfaAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'authenticator_public_id' => ['required', 'string', 'max:36'],
            'code' => ['required', 'string', 'digits:6'],
        ]);

        $action->confirm($request->user(), $validated['authenticator_public_id'], $validated['code']);

        return back()->with('success', __('Multi-factor authentication is now enabled.'));
    }

    public function challenge(CompleteMfaChallengeRequest $request, CompleteMfaChallengeAction $action): RedirectResponse
    {
        $action->execute($request->user(), (string) $request->string('code'));

        // Mark the session as MFA-confirmed and record recent reauthentication
        // for step-up-protected actions (NFR-SE-011).
        $request->session()->put('auth.mfa_confirmed', true);
        $request->session()->put('auth.reauth_confirmed_at', now()->timestamp);

        return redirect()->intended(route('home'))->with('success', __('Identity confirmed.'));
    }
}
