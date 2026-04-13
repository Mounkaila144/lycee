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

    // CRUD Utilisateurs Tenant
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/{user}', [UserController::class, 'show']);
        Route::put('/{user}', [UserController::class, 'update']);
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

    // Teachers endpoint (users with "Professeur" role)
    Route::prefix('teachers')->group(function () {
        Route::get('/', [UserController::class, 'teachers']);
    });

    // Students endpoint (users with "Étudiant" role)
    Route::prefix('students')->group(function () {
        Route::get('/', [UserController::class, 'students']);
    });

    // CRUD Permissions
    Route::prefix('permissions')->group(function () {
        Route::get('/', [PermissionController::class, 'index']);
        Route::post('/', [PermissionController::class, 'store']);
        Route::get('/{permission}', [PermissionController::class, 'show']);
        Route::put('/{permission}', [PermissionController::class, 'update']);
        Route::delete('/{permission}', [PermissionController::class, 'destroy']);
    });

    // CRUD Rôles
    Route::prefix('roles')->group(function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::post('/', [RoleController::class, 'store']);
        Route::get('/{role}', [RoleController::class, 'show']);
        Route::put('/{role}', [RoleController::class, 'update']);
        Route::delete('/{role}', [RoleController::class, 'destroy']);
    });
});
