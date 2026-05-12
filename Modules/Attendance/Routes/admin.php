<?php

use Illuminate\Support\Facades\Route;
use Modules\Attendance\Http\Controllers\Admin\AttendanceController;
use Modules\Attendance\Http\Controllers\Admin\AttendanceMonitoringController;
use Modules\Attendance\Http\Controllers\Admin\AttendanceReportController;
use Modules\Attendance\Http\Controllers\Admin\JustificationController;

/*
|--------------------------------------------------------------------------
| Admin Routes - Attendance
|--------------------------------------------------------------------------
| Ces routes sont pour les administrateurs du TENANT
| Elles utilisent la base de données tenant (connexion 'tenant')
*/

// RBAC durcissement (Stories Professeur 06, Admin 07, Manager 06) :
// Admin, Manager et Professeur peuvent accéder. Le Professeur ne voit que SES sessions
// (ownership check `teacher_id = auth()->id()` dans AttendanceController — à compléter).
// La validation des justificatifs (POST justifications/{id}/validate) doit rester Admin
// uniquement — restriction fine à appliquer story par story.
// Protected admin routes (tenant + auth required)
Route::prefix('admin')
    ->middleware(['tenant', 'tenant.auth', 'role:Administrator|Manager|Professeur,tenant'])
    ->group(function () {
        // Gestion des présences (Stories 01-04)
        Route::prefix('attendance')->name('attendance.')->group(function () {
            Route::get('/sessions', [AttendanceController::class, 'index'])->name('sessions.index');
            Route::post('/sessions', [AttendanceController::class, 'createSession'])->name('sessions.create');
            Route::get('/sessions/{sessionId}/sheet', [AttendanceController::class, 'getAttendanceSheet'])->name('sessions.sheet');
            Route::post('/sessions/{sessionId}/complete', [AttendanceController::class, 'completeSession'])->name('sessions.complete');

            Route::post('/record', [AttendanceController::class, 'recordAttendance'])->name('record');
            Route::put('/records/{recordId}', [AttendanceController::class, 'modifyRecord'])->name('records.modify');
            Route::post('/record-qr', [AttendanceController::class, 'recordViaQRCode'])->name('record-qr');
        });

        // Gestion des justificatifs (Stories 05-07)
        Route::prefix('justifications')->name('justifications.')->group(function () {
            Route::get('/', [JustificationController::class, 'index'])->name('index');
            Route::post('/', [JustificationController::class, 'submit'])->name('submit');
            Route::get('/pending', [JustificationController::class, 'pending'])->name('pending');
            Route::get('/students/{studentId}', [JustificationController::class, 'getStudentJustifications'])->name('student');
            Route::post('/{justificationId}/validate', [JustificationController::class, 'validate'])->name('validate');
            Route::get('/{justificationId}/download', [JustificationController::class, 'downloadDocument'])->name('download');
        });

        // Suivi et alertes (Stories 08-10)
        Route::prefix('attendance/monitoring')->name('attendance.monitoring.')->group(function () {
            Route::post('/check-thresholds', [AttendanceMonitoringController::class, 'checkThresholds'])->name('check-thresholds');
            Route::post('/trigger-alerts', [AttendanceMonitoringController::class, 'triggerAlerts'])->name('trigger-alerts');
            Route::get('/alerts', [AttendanceMonitoringController::class, 'getActiveAlerts'])->name('alerts');
            Route::get('/students/{studentId}/history', [AttendanceMonitoringController::class, 'getStudentHistory'])->name('student-history');
            Route::get('/students/{studentId}/stats', [AttendanceMonitoringController::class, 'getStudentStats'])->name('student-stats');
        });

        // Rapports et statistiques (Stories 11-13)
        Route::prefix('attendance/reports')->name('attendance.reports.')->group(function () {
            Route::get('/rates', [AttendanceReportController::class, 'getAttendanceRates'])->name('rates');
            Route::get('/absentees', [AttendanceReportController::class, 'getAbsenteesList'])->name('absentees');
            Route::get('/statistics', [AttendanceReportController::class, 'getDetailedStatistics'])->name('statistics');
            Route::get('/export', [AttendanceReportController::class, 'exportData'])->name('export');
        });
    });
