<?php

use Illuminate\Routing\Middleware\SubstituteBindings;
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

// RBAC (Stories Professeur 07, Admin 08, Manager 07) :
// - Lecture : Administrator, Manager, Professeur. Le Professeur ne voit que SES créneaux
//   (ownership appliqué dans TimetableController / TimetableGenerationController).
// - Gestion (créneaux, salles, génération, duplication, exceptions, rapports) : Administrator, Manager.
// SubstituteBindings est requis (pas de groupe `api`) pour résoudre {room}, {slot}, {exception}.
$timetableReaders = ['tenant', 'tenant.auth', 'role:Administrator|Manager|Professeur,tenant', SubstituteBindings::class];
$timetableManagers = ['tenant', 'tenant.auth', 'role:Administrator|Manager,tenant', SubstituteBindings::class];

Route::prefix('api/admin')
    ->middleware($timetableReaders)
    ->group(function () {
        Route::prefix('rooms')->name('rooms.')->group(function () {
            Route::get('/', [RoomController::class, 'index'])->name('index');
            Route::get('/buildings', [RoomController::class, 'buildings'])->name('buildings');
            Route::get('/available', [RoomController::class, 'available'])->name('available');
            Route::get('/{room}', [RoomController::class, 'show'])->name('show')->whereNumber('room');
        });

        Route::prefix('timetable')->name('timetable.')->group(function () {
            Route::get('/me', [TimetableController::class, 'myTimetable'])->name('me');
            Route::get('/slots', [TimetableController::class, 'index'])->name('slots.index');
            Route::get('/slots/{slot}', [TimetableController::class, 'show'])->name('slots.show')->whereNumber('slot');
            Route::get('/slots/{slot}/history', [TimetableController::class, 'history'])->name('slots.history')->whereNumber('slot');
            Route::get('/teachers/{teacherId}', [TimetableController::class, 'byTeacher'])->name('by-teacher');
            Route::get('/classes/{classeId}', [TimetableController::class, 'byClasse'])->name('by-classe');
            Route::get('/rooms/{roomId}', [TimetableController::class, 'byRoom'])->name('by-room');
            Route::get('/exceptions', [TimetableExceptionController::class, 'index'])->name('exceptions.index');
        });

        // Préférences enseignants (le Professeur ne gère que les siennes)
        Route::prefix('teachers')->name('teachers.')->group(function () {
            Route::get('/{teacherId}/preferences', [TimetableGenerationController::class, 'getTeacherPreferences'])->name('preferences.index');
            Route::put('/{teacherId}/preferences', [TimetableGenerationController::class, 'updateTeacherPreferences'])->name('preferences.update');
        });

        // Notifications de l'utilisateur connecté (Epic 3 - Stories 10-13)
        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/', [TimetableNotificationController::class, 'index'])->name('index');
            Route::get('/unread-count', [TimetableNotificationController::class, 'unreadCount'])->name('unread-count');
            Route::post('/{notification}/read', [TimetableNotificationController::class, 'markAsRead'])->name('mark-read')->whereNumber('notification');
            Route::post('/read-all', [TimetableNotificationController::class, 'markAllAsRead'])->name('mark-all-read');
            Route::get('/upcoming-changes', [TimetableNotificationController::class, 'upcomingChanges'])->name('upcoming-changes');
            Route::get('/settings', [TimetableNotificationController::class, 'getSettings'])->name('settings.show');
            Route::put('/settings', [TimetableNotificationController::class, 'updateSettings'])->name('settings.update');
        });
    });

Route::prefix('api/admin')
    ->middleware($timetableManagers)
    ->group(function () {
        // Gestion des salles
        Route::prefix('rooms')->name('rooms.')->group(function () {
            Route::post('/', [RoomController::class, 'store'])->name('store');
            Route::get('/suggested', [RoomController::class, 'suggested'])->name('suggested');
            Route::get('/stats', [RoomController::class, 'globalStats'])->name('stats');
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
            Route::post('/slots', [TimetableController::class, 'store'])->name('slots.store');
            Route::put('/slots/{slot}', [TimetableController::class, 'update'])->name('slots.update');
            Route::delete('/slots/{slot}', [TimetableController::class, 'destroy'])->name('slots.destroy');

            // Vérification des conflits
            Route::post('/check-conflicts', [TimetableController::class, 'checkConflicts'])->name('check-conflicts');

            // Vues par entité (gestion)
            Route::get('/groups/{groupId}', [TimetableController::class, 'byGroup'])->name('by-group');
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
                Route::post('/', [TimetableExceptionController::class, 'store'])->name('store');
                Route::get('/slots/{slotId}/history', [TimetableExceptionController::class, 'getSlotHistory'])->name('slot-history');
                Route::get('/upcoming/{semesterId}', [TimetableExceptionController::class, 'getUpcoming'])->name('upcoming');
                Route::delete('/{exception}', [TimetableExceptionController::class, 'destroy'])->name('destroy');
            });
        });

        // Rapports et statistiques (Stories 14-17)
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/export-pdf', [TimetableReportsController::class, 'exportPdf'])->name('export-pdf');
            Route::get('/occupation-stats', [TimetableReportsController::class, 'occupationStats'])->name('occupation-stats');
            Route::get('/teacher-workload', [TimetableReportsController::class, 'teacherWorkload'])->name('teacher-workload');
            Route::get('/room-utilization', [TimetableReportsController::class, 'roomUtilization'])->name('room-utilization');
        });

        // Notifications - actions d'administration
        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::post('/trigger-reminders', [TimetableNotificationController::class, 'triggerReminders'])->name('trigger-reminders');
            Route::get('/statistics', [TimetableNotificationController::class, 'statistics'])->name('statistics');
            Route::delete('/cleanup', [TimetableNotificationController::class, 'cleanup'])->name('cleanup');
        });
    });
