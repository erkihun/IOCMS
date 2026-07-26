<?php

declare(strict_types=1);

namespace App\Http\Controllers\IAM;

use App\Domain\Identity\Actions\VerifyContactAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\IAM\VerifyContactRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Coordinates contact-channel verification transport (LLD §8). Throttled at the
 * route level (FR-IAM-003 / NFR-SE-020); the single-use token check is in the Action.
 */
final class VerificationController extends Controller
{
    public function confirm(VerifyContactRequest $request, VerifyContactAction $action): RedirectResponse
    {
        $action->execute((string) $request->string('token'));

        return redirect()
            ->route('login')
            ->with('success', __('Your contact has been verified. You can now sign in.'));
    }
}
