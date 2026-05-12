<?php

use Illuminate\Support\Facades\Route;
use Modules\UsersGuard\Http\Controllers\Admin\AuthController;
use Modules\UsersGuard\Http\Controllers\Admin\IndexController;
use Modules\UsersGuard\Http\Controllers\Admin\PermissionController;
use Modules\UsersGuard\Http\Controllers\Admin\RoleController;
use Modules\UsersGuard\Http\Controllers\Admin\UserController;

// Public admin routes (tenant context required)
Route::prefix('admin')->middleware(['tenant'])->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->name('login');
});

// Protected admin routes (tenant + auth required)
Route::prefix('admin')->middleware(['tenant', 'tenant.auth'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);

    Route::prefix('usersguard')->group(function () {
        Route::get('/', [IndexController::class, 'index']);
    });

    // CRUD Utilisateurs Tenant — Admin / Manager only (delete: Admin only)
    Route::prefix('users')->middleware('role:Administrator|Manager,tenant')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/{user}', [UserController::class, 'show']);
        Route::put('/{user}', [UserController::class, 'update']);

        Route::middleware('role:Administrator,tenant')->group(function () {
            Route::delete('/{user}', [UserController::class, 'destroy']);

            // Restore & Force Delete
            Route::post('/{user}/restore', [UserController::class, 'restore']);
            Route::delete('/{user}/force', [UserController::class, 'forceDelete']);

            // Gestion des Permissions
            Route::post('/{user}/permissions/add', [UserController::class, 'addPermissions']);
            Route::post('/{user}/permissions/remove', [UserController::class, 'removePermissions']);
            Route::post('/{user}/permissions/sync', [UserController::class, 'syncPermissions']);

            // Gestion des Rôles
            Route::post('/{user}/roles/add', [UserController::class, 'addRoles']);
            Route::post('/{user}/roles/remove', [UserController::class, 'removeRoles']);
            Route::post('/{user}/roles/sync', [UserController::class, 'syncRoles']);
        });
    });

    // Teachers endpoint — readable by Admin, Manager, and Professeur themselves
    Route::prefix('teachers')->middleware('role:Administrator|Manager|Professeur,tenant')->group(function () {
        Route::get('/', [UserController::class, 'teachers']);
    });

    // Students endpoint — readable by Admin, Manager, financial roles, Professeur
    Route::prefix('students')->middleware('role:Administrator|Manager|Caissier|Comptable|Agent Comptable|Professeur,tenant')->group(function () {
        Route::get('/', [UserController::class, 'students']);
    });

    // CRUD Permissions — Admin only
    Route::prefix('permissions')->middleware('role:Administrator,tenant')->group(function () {
        Route::get('/', [PermissionController::class, 'index']);
        Route::post('/', [PermissionController::class, 'store']);
        Route::get('/{permission}', [PermissionController::class, 'show']);
        Route::put('/{permission}', [PermissionController::class, 'update']);
        Route::delete('/{permission}', [PermissionController::class, 'destroy']);
    });

    // CRUD Rôles — Admin only
    Route::prefix('roles')->middleware('role:Administrator,tenant')->group(function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::post('/', [RoleController::class, 'store']);
        Route::get('/{role}', [RoleController::class, 'show']);
        Route::put('/{role}', [RoleController::class, 'update']);
        Route::delete('/{role}', [RoleController::class, 'destroy']);
    });
});
