<?php

use Illuminate\Support\Facades\Route;
use Modules\UsersGuard\Http\Controllers\Superadmin\AuthController;
use Modules\UsersGuard\Http\Controllers\Superadmin\IndexController;
use Modules\UsersGuard\Http\Controllers\Superadmin\TenantController;
use Modules\UsersGuard\Http\Controllers\Superadmin\UserController;

/*
|--------------------------------------------------------------------------
| Superadmin Routes (CENTRAL DATABASE)
|--------------------------------------------------------------------------
| Ces routes utilisent la base de données centrale
*/

// Routes d'authentification (pas de middleware auth)
Route::prefix('superadmin')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
    });
});

// Routes protégées
Route::prefix('superadmin')->middleware(['auth:sanctum'])->group(function () {
    // Auth routes (protégées)
    Route::prefix('auth')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
    });

    // Users guard routes
    Route::prefix('usersguard')->group(function () {
        Route::get('/', [IndexController::class, 'index']);
    });

    // Super Admin Users CRUD
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/{user}', [UserController::class, 'show']);
        Route::put('/{user}', [UserController::class, 'update']);
        Route::delete('/{user}', [UserController::class, 'destroy']);
        Route::post('/{user}/restore', [UserController::class, 'restore']);
        Route::delete('/{user}/force', [UserController::class, 'forceDelete']);
        Route::post('/{user}/toggle-active', [UserController::class, 'toggleActive']);
    });

    // Tenants CRUD
    Route::prefix('tenants')->group(function () {
        Route::get('/', [TenantController::class, 'index']);
        Route::post('/', [TenantController::class, 'store']);
        Route::get('/{tenant}', [TenantController::class, 'show']);
        Route::put('/{tenant}', [TenantController::class, 'update']);
        Route::delete('/{tenant}', [TenantController::class, 'destroy']);
        Route::post('/{tenant}/toggle-active', [TenantController::class, 'toggleActive']);
        Route::post('/{tenant}/domains', [TenantController::class, 'addDomain']);
        Route::delete('/{tenant}/domains/{domain}', [TenantController::class, 'removeDomain']);
    });
});
