<?php

use Illuminate\Support\Facades\Route;
use Modules\Settings\Http\Controllers\Admin\SettingsController;

/*
 * Story Admin 13 — Réglages de l'établissement.
 * Accès STRICTEMENT Admin (lecture + écriture).
 */
Route::prefix('admin/settings')
    ->middleware(['tenant', 'tenant.auth', 'role:Administrator,tenant'])
    ->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('admin.settings.index');
        Route::post('/', [SettingsController::class, 'upsert'])->name('admin.settings.upsert');

        // School identity profile (logo, name, NIF…) — declared before /{key}.
        Route::get('/school-profile', [SettingsController::class, 'schoolProfile'])->name('admin.settings.school-profile');
        Route::post('/school-profile', [SettingsController::class, 'updateSchoolProfile'])->name('admin.settings.school-profile.update');

        Route::get('/{key}', [SettingsController::class, 'show'])->name('admin.settings.show');
        Route::delete('/{key}', [SettingsController::class, 'destroy'])->name('admin.settings.destroy');
    });
