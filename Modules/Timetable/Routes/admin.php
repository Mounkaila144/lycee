<?php

use Illuminate\Support\Facades\Route;
use Modules\Timetable\Http\Controllers\Admin\RoomController;
use Modules\Timetable\Http\Controllers\Admin\TimetableController;
use Modules\Timetable\Http\Controllers\Admin\TimetableDuplicationController;
use Modules\Timetable\Http\Controllers\Admin\TimetableExceptionController;
use Modules\Timetable\Http\Controllers\Admin\TimetableGenerationController;
use Modules\Timetable\Http\Controllers\Admin\TimetableNotificationController;
use Modules\Timetable\Http\Controllers\Admin\TimetableReportsController;

/*
|--------------------------------------------------------------------------
| Admin Routes - Timetable
|--------------------------------------------------------------------------
| Ces routes sont pour les administrateurs du TENANT
| Elles utilisent la base de données tenant (connexion 'tenant')
*/

// Protected admin routes (tenant + auth required)
Route::prefix('admin')
    ->middleware(['tenant', 'tenant.auth'])
    ->group(function () {
        // Gestion des salles
        Route::prefix('rooms')->name('rooms.')->group(function () {
            Route::get('/', [RoomController::class, 'index'])->name('index');
            Route::post('/', [RoomController::class, 'store'])->name('store');
            Route::get('/buildings', [RoomController::class, 'buildings'])->name('buildings');
            Route::get('/available', [RoomController::class, 'available'])->name('available');
            Route::get('/suggested', [RoomController::class, 'suggested'])->name('suggested');
            Route::get('/stats', [RoomController::class, 'globalStats'])->name('stats');
            Route::get('/{room}', [RoomController::class, 'show'])->name('show');
            Route::put('/{room}', [RoomController::class, 'update'])->name('update');
            Route::delete('/{room}', [RoomController::class, 'destroy'])->name('destroy');
            Route::get('/{room}/occupation', [RoomController::class, 'occupation'])->name('occupation');
            Route::get('/{room}/occupation-report', [RoomController::class, 'occupationReport'])->name('occupation-report');
            Route::post('/{room}/block', [RoomController::class, 'block'])->name('block');
            Route::post('/{room}/unblock', [RoomController::class, 'unblock'])->name('unblock');
        });

        // Gestion des emplois du temps
        Route::prefix('timetable')->name('timetable.')->group(function () {
            // CRUD Créneaux
            Route::get('/slots', [TimetableController::class, 'index'])->name('slots.index');
            Route::post('/slots', [TimetableController::class, 'store'])->name('slots.store');
            Route::get('/slots/{slot}', [TimetableController::class, 'show'])->name('slots.show');
            Route::put('/slots/{slot}', [TimetableController::class, 'update'])->name('slots.update');
            Route::delete('/slots/{slot}', [TimetableController::class, 'destroy'])->name('slots.destroy');
            Route::get('/slots/{slot}/history', [TimetableController::class, 'history'])->name('slots.history');

            // Vérification des conflits
            Route::post('/check-conflicts', [TimetableController::class, 'checkConflicts'])->name('check-conflicts');

            // Vues par entité
            Route::get('/groups/{groupId}', [TimetableController::class, 'byGroup'])->name('by-group');
            Route::get('/teachers/{teacherId}', [TimetableController::class, 'byTeacher'])->name('by-teacher');
            Route::get('/rooms/{roomId}', [TimetableController::class, 'byRoom'])->name('by-room');
            Route::get('/students/{studentId}', [TimetableController::class, 'byStudent'])->name('by-student');

            // Génération automatique
            Route::post('/generate', [TimetableGenerationController::class, 'generate'])->name('generate');
            Route::get('/generation-result/{groupId}', [TimetableGenerationController::class, 'getResult'])->name('generation-result');
            Route::post('/accept-generated', [TimetableGenerationController::class, 'acceptGenerated'])->name('accept-generated');

            // Duplication
            Route::get('/duplication-preview', [TimetableDuplicationController::class, 'preview'])->name('duplication.preview');
            Route::post('/duplicate', [TimetableDuplicationController::class, 'duplicate'])->name('duplicate');
            Route::get('/slots/{slot}/suggestions', [TimetableDuplicationController::class, 'getSuggestions'])->name('slots.suggestions');
            Route::put('/slots/{slot}/quick-assign', [TimetableDuplicationController::class, 'quickAssign'])->name('slots.quick-assign');

            // Exceptions
            Route::prefix('exceptions')->name('exceptions.')->group(function () {
                Route::get('/', [TimetableExceptionController::class, 'index'])->name('index');
                Route::post('/', [TimetableExceptionController::class, 'store'])->name('store');
                Route::get('/slots/{slotId}/history', [TimetableExceptionController::class, 'getSlotHistory'])->name('slot-history');
                Route::get('/upcoming/{semesterId}', [TimetableExceptionController::class, 'getUpcoming'])->name('upcoming');
                Route::delete('/{exception}', [TimetableExceptionController::class, 'destroy'])->name('destroy');
            });
        });

        // Préférences enseignants
        Route::prefix('teachers')->name('teachers.')->group(function () {
            Route::get('/{teacherId}/preferences', [TimetableGenerationController::class, 'getTeacherPreferences'])->name('preferences.index');
            Route::put('/{teacherId}/preferences', [TimetableGenerationController::class, 'updateTeacherPreferences'])->name('preferences.update');
        });

        // Rapports et statistiques (Stories 14-17)
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/export-pdf', [TimetableReportsController::class, 'exportPdf'])->name('export-pdf');
            Route::get('/occupation-stats', [TimetableReportsController::class, 'occupationStats'])->name('occupation-stats');
            Route::get('/teacher-workload', [TimetableReportsController::class, 'teacherWorkload'])->name('teacher-workload');
            Route::get('/room-utilization', [TimetableReportsController::class, 'roomUtilization'])->name('room-utilization');
        });

        // Notifications (Epic 3 - Stories 10-13)
        Route::prefix('notifications')->name('notifications.')->group(function () {
            // User notifications
            Route::get('/', [TimetableNotificationController::class, 'index'])->name('index');
            Route::get('/unread-count', [TimetableNotificationController::class, 'unreadCount'])->name('unread-count');
            Route::post('/{notification}/read', [TimetableNotificationController::class, 'markAsRead'])->name('mark-read');
            Route::post('/read-all', [TimetableNotificationController::class, 'markAllAsRead'])->name('mark-all-read');

            // Upcoming changes for user
            Route::get('/upcoming-changes', [TimetableNotificationController::class, 'upcomingChanges'])->name('upcoming-changes');

            // Settings
            Route::get('/settings', [TimetableNotificationController::class, 'getSettings'])->name('settings.show');
            Route::put('/settings', [TimetableNotificationController::class, 'updateSettings'])->name('settings.update');

            // Admin actions
            Route::post('/trigger-reminders', [TimetableNotificationController::class, 'triggerReminders'])->name('trigger-reminders');
            Route::get('/statistics', [TimetableNotificationController::class, 'statistics'])->name('statistics');
            Route::delete('/cleanup', [TimetableNotificationController::class, 'cleanup'])->name('cleanup');
        });
    });
