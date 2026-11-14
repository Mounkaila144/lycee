<?php

use Illuminate\Support\Facades\Route;
use Modules\Settings\Http\Controllers\Admin\SchoolProfileController;
use Modules\Settings\Http\Controllers\Admin\SettingsController;

/*
 * Profil de l'établissement (nom, en-tête officiel, coordonnées, logo, cachet).
 * Lecture : tout utilisateur authentifié du tenant (affichage) ; écriture : Administrator.
 * Déclaré AVANT admin/settings/{key}.
 */
Route::prefix('admin/settings/school-profile')
    ->middleware(['tenant', 'tenant.auth'])
    ->group(function () {
        Route::get('/', [SchoolProfileController::class, 'show'])->name('admin.settings.school-profile.show');
        Route::get('/{image}', [SchoolProfileController::class, 'image'])
            ->whereIn('image', ['logo', 'stamp'])
            ->name('admin.settings.school-profile.image');

        Route::middleware('role:Administrator,tenant')->group(function () {
            Route::put('/', [SchoolProfileController::class, 'update'])->name('admin.settings.school-profile.update');
            Route::post('/{image}', [SchoolProfileController::class, 'uploadImage'])
                ->whereIn('image', ['logo', 'stamp'])
                ->name('admin.settings.school-profile.image.upload');
            Route::delete('/{image}', [SchoolProfileController::class, 'deleteImage'])
                ->whereIn('image', ['logo', 'stamp'])
                ->name('admin.settings.school-profile.image.delete');
        });
    });

/*
 * Story Admin 13 — Réglages de l'établissement.
 * Accès STRICTEMENT Admin (lecture + écriture).
 */
Route::prefix('admin/settings')
    ->middleware(['tenant', 'tenant.auth', 'role:Administrator,tenant'])
    ->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('admin.settings.index');
        Route::post('/', [SettingsController::class, 'upsert'])->name('admin.settings.upsert');
        Route::get('/{key}', [SettingsController::class, 'show'])->name('admin.settings.show');
        Route::delete('/{key}', [SettingsController::class, 'destroy'])->name('admin.settings.destroy');
    });
