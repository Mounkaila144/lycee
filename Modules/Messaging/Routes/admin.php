<?php

use Illuminate\Support\Facades\Route;
use Modules\Messaging\Http\Controllers\Admin\AnnouncementController;
use Modules\Messaging\Http\Controllers\Admin\MessagingController;

/*
 * Story Parent 07 — Messages enseignants.
 * Accessible aux Parents et Professeurs (qui échangent entre eux),
 * ainsi qu'à la direction (Administrator, Manager).
 */
Route::prefix('admin/messages')
    ->middleware(['tenant', 'tenant.auth', 'role:Parent|Professeur|Administrator|Manager,tenant'])
    ->group(function () {
        Route::get('/', [MessagingController::class, 'index'])->name('admin.messages.index');
        Route::get('/recipients', [MessagingController::class, 'recipients'])->name('admin.messages.recipients');
        Route::post('/', [MessagingController::class, 'store'])->name('admin.messages.store');
        Route::get('/{message}', [MessagingController::class, 'show'])->whereNumber('message')->name('admin.messages.show');
    });

/*
 * Annonces de l'établissement.
 * Lecture filtrée par audience pour tous les rôles ; gestion par la direction.
 */
Route::prefix('admin/announcements')
    ->middleware(['tenant', 'tenant.auth'])
    ->group(function () {
        Route::get('/feed', [AnnouncementController::class, 'feed'])
            ->middleware('role:Administrator|Manager|Professeur|Élève|Parent|Caissier|Comptable,tenant')
            ->name('admin.announcements.feed');

        Route::middleware('role:Administrator|Manager,tenant')->group(function () {
            Route::get('/', [AnnouncementController::class, 'index'])->name('admin.announcements.index');
            Route::get('/audience-preview', [AnnouncementController::class, 'audiencePreview'])->name('admin.announcements.audience-preview');
            Route::post('/', [AnnouncementController::class, 'store'])->name('admin.announcements.store');
            Route::get('/{announcement}', [AnnouncementController::class, 'show'])->whereNumber('announcement')->name('admin.announcements.show');
            Route::put('/{announcement}', [AnnouncementController::class, 'update'])->whereNumber('announcement')->name('admin.announcements.update');
            Route::delete('/{announcement}', [AnnouncementController::class, 'destroy'])->whereNumber('announcement')->name('admin.announcements.destroy');
        });
    });
