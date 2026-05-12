<?php

use Illuminate\Support\Facades\Route;
use Modules\Messaging\Http\Controllers\Admin\MessagingController;

/*
 * Story Parent 07 — Messages enseignants.
 * Accessible aux Parents et Professeurs (qui échangent entre eux).
 * Admin a accès pour modération éventuelle.
 */
Route::prefix('admin/messages')
    ->middleware(['tenant', 'tenant.auth', 'role:Parent|Professeur|Administrator,tenant'])
    ->group(function () {
        Route::get('/', [MessagingController::class, 'index'])->name('admin.messages.index');
        Route::post('/', [MessagingController::class, 'store'])->name('admin.messages.store');
        Route::get('/{message}', [MessagingController::class, 'show'])->name('admin.messages.show');
    });
