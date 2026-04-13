<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Superadmin Routes - Structure Académique
|--------------------------------------------------------------------------
| Ces routes sont pour le SUPER ADMIN GLOBAL (créateur des tenants)
| Elles utilisent la base de données CENTRALE (connexion 'mysql')
*/

Route::prefix('api/superadmin')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        // Routes pour la gestion globale des tenants
        // À implémenter selon les besoins du Super Admin Global
    });
