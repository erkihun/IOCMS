<?php

declare(strict_types=1);

namespace App\Http\Controllers\IAM;

use App\Domain\Identity\Actions\RegisterCustomerAction;
use App\Domain\Identity\Services\IdentifierNormalizer;
use App\Http\Controllers\Controller;
use App\Http\Requests\IAM\RegisterCustomerRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Coordinates the registration HTTP transport only (LLD §8: controllers do no
 * business logic). Validation is in the Form Request; the use case is in the
 * Action; uniqueness/audit/outbox are enforced there.
 */
final class RegisterController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(
        RegisterCustomerRequest $request,
        RegisterCustomerAction $action,
        IdentifierNormalizer $normalizer,
    ): RedirectResponse {
        $user = $action->execute($request->toData($normalizer));

        return redirect()
            ->route('home')
            ->with('success', __('We sent a verification message. Please confirm your contact to continue.'))
            ->with('registered_user', $user->public_id);
    }
}
