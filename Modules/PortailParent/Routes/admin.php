<?php

use Illuminate\Support\Facades\Route;
use Modules\PortailParent\Http\Controllers\Admin\ChildDataController;
use Modules\PortailParent\Http\Controllers\Admin\ParentChildrenController;

/*
 * Routes Portail Parent (admin namespace).
 *
 * Toutes les routes sont protégées par :
 *   - tenant (résolution du tenant courant)
 *   - tenant.auth (Bearer token TenantSanctumAuth)
 *   - role:Parent (Spatie tenant guard)
 *
 * L'ownership Parent ↔ Enfant est appliqué via ChildPolicy (cf. Policies/ChildPolicy.php).
 */
Route::prefix('admin/parent')
    ->middleware(['tenant', 'tenant.auth', 'role:Parent,tenant'])
    ->group(function () {
        // Story Parent 01 — Home & Mes Enfants
        Route::get('/me', [ParentChildrenController::class, 'me'])
            ->name('admin.parent.me');
        Route::get('/me/children', [ParentChildrenController::class, 'children'])
            ->name('admin.parent.me.children');
        Route::get('/children/{student}', [ParentChildrenController::class, 'show'])
            ->name('admin.parent.children.show');

        // Story Parent 02 — Notes de l'enfant
        Route::get('/children/{student}/grades', [ChildDataController::class, 'grades'])
            ->name('admin.parent.children.grades');

        // Story Parent 03 — Présences de l'enfant
        Route::get('/children/{student}/attendance', [ChildDataController::class, 'attendance'])
            ->name('admin.parent.children.attendance');

        // Story Parent 05 — Factures de l'enfant
        Route::get('/children/{student}/invoices', [ChildDataController::class, 'invoices'])
            ->name('admin.parent.children.invoices');

        // Story Parent 04 — Emploi du temps de l'enfant
        Route::get('/children/{student}/timetable', [ChildDataController::class, 'timetable'])
            ->name('admin.parent.children.timetable');

        // Story Parent 09 — Documents de l'enfant
        Route::get('/children/{student}/documents', [ChildDataController::class, 'documents'])
            ->name('admin.parent.children.documents');

        // Story Parent 08 — Annonces générales (pas d'ownership enfant-spécifique)
        Route::get('/announcements', [ChildDataController::class, 'announcements'])
            ->name('admin.parent.announcements');
    });
