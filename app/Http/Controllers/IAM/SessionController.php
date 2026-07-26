<?php

declare(strict_types=1);

namespace App\Http\Controllers\IAM;

use App\Domain\Identity\Actions\RevokeSessionAction;
use App\Domain\Identity\Models\UserSession;
use App\Domain\Identity\Queries\ListActiveSessionsQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\SessionResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Coordinates active-session management transport (FR-IAM-008). Ownership is
 * authorized by SessionPolicy; the query is scoped to the current user; the
 * Action performs the revoke. Route-model binding resolves on the public id
 * (BR-046) — the internal key is never exposed.
 */
final class SessionController extends Controller
{
    public function index(Request $request, ListActiveSessionsQuery $query): Response
    {
        $this->authorize('viewAny', UserSession::class);

        $sessions = $query->forUser((int) $request->user()->getAuthIdentifier());

        return Inertia::render('Account/Sessions', [
            'sessions' => SessionResource::collection($sessions),
        ]);
    }

    public function destroy(Request $request, UserSession $session, RevokeSessionAction $action): RedirectResponse
    {
        // Owner-scope check (NFR-SE-013 IDOR): 403 if not the actor's session.
        $this->authorize('revoke', $session);

        $action->execute($session);

        return back()->with('success', __('Session revoked.'));
    }
}
