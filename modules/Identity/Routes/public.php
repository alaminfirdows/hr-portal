<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Http\Controllers\AcceptInvitationController;

Route::middleware('guest')->group(function () {
    Route::get('invitations/{token}', [AcceptInvitationController::class, 'show'])->name('invitations.accept');
    Route::post('invitations/{token}', [AcceptInvitationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('invitations.store');
});
