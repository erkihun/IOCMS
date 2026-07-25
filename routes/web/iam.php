<?php

declare(strict_types=1);

use App\Http\Controllers\IAM\RegisterController;
use Illuminate\Support\Facades\Route;

/*
| IAM customer-facing auth routes (LLD §5.1 / Appendix B).
| Registration is throttled to resist enumeration/abuse (NFR-SE-020).
*/

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('register.store');
});
