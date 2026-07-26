<?php

declare(strict_types=1);

use App\Http\Controllers\IAM\AdminUserController;
use Illuminate\Support\Facades\Route;

/*
| IAM admin routes (LLD §5.1 / Appendix B). The `admin` middleware group already
| applies web + auth + verified contact. Permission middleware adds the granular
| capability check; Policies additionally enforce self-action guards and scope.
*/

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::put('/users/{user}/status', [AdminUserController::class, 'changeStatus'])
        ->middleware('permission:users.manage')
        ->name('users.status');

    Route::post('/users/{user}/roles', [AdminUserController::class, 'assignRole'])
        ->middleware('permission:roles.assign')
        ->name('users.roles.assign');
});
