<?php

declare(strict_types=1);

namespace App\Http\Controllers\IAM;

use App\Domain\Identity\Actions\AuthenticateUserAction;
use App\Domain\Identity\Services\SessionRegistry;
use App\Http\Controllers\Controller;
use App\Http\Requests\IAM\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Coordinates sign-in transport only (LLD §8: no business logic in controllers).
 *
 * On success the session identifier is rotated (NFR-SE-007: rotate after
 * authentication) to prevent session fixation. Login throttling is applied by
 * the route middleware (`throttle`) so credential-stuffing is bounded (FR-IAM-009);
 * the Action additionally records every attempt and resists enumeration.
 */
final class LoginController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(
        LoginRequest $request,
        AuthenticateUserAction $action,
        SessionRegistry $sessions,
    ): RedirectResponse {
        $user = $action->execute($request->toData());

        Auth::login($user, remember: false);

        // Rotate the session id after authentication (NFR-SE-007).
        $request->session()->regenerate();

        // Register the active session for the list/revoke feature (FR-IAM-008).
        $sessions->record($user->id, $request->session()->getId(), $request->ip(), $request->userAgent());

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
