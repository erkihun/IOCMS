<?php

declare(strict_types=1);

use App\Http\Controllers\IAM\LoginController;
use App\Http\Controllers\IAM\MfaController;
use App\Http\Controllers\IAM\RegisterController;
use App\Http\Controllers\IAM\SessionController;
use App\Http\Controllers\IAM\VerificationController;
use Illuminate\Support\Facades\Route;

/*
| IAM customer-facing auth routes (LLD §5.1 / Appendix B).
| Registration and login are throttled to resist enumeration/abuse (NFR-SE-020,
| FR-IAM-009). Sessions are rotated on authentication in the controller (NFR-SE-007).
*/

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('register.store');

    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('login.store');
});

// Contact verification (signed + throttled — FR-IAM-003).
Route::post('/verification/confirm', [VerificationController::class, 'confirm'])
    ->middleware('throttle:10,1')
    ->name('verification.confirm');

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Active-session management (owner-scoped — FR-IAM-008).
Route::middleware('auth')->group(function (): void {
    Route::get('/account/sessions', [SessionController::class, 'index'])->name('account.sessions');
    Route::delete('/account/sessions/{session}', [SessionController::class, 'destroy'])
        ->name('account.sessions.destroy');

    // MFA enrollment + challenge (FR-IAM-005). Challenge is throttled (abuse).
    Route::post('/mfa/enroll', [MfaController::class, 'begin'])->name('mfa.enroll');
    Route::post('/mfa/enroll/confirm', [MfaController::class, 'confirmEnrollment'])->name('mfa.enroll.confirm');
    Route::post('/mfa/challenge', [MfaController::class, 'challenge'])
        ->middleware('throttle:6,1')
        ->name('mfa.challenge');
});
