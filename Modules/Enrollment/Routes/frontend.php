<?php

use Illuminate\Support\Facades\Route;
use Modules\Enrollment\Http\Controllers\Frontend\MyCardController;
use Modules\Enrollment\Http\Controllers\Frontend\StudentGroupController;

/*
|--------------------------------------------------------------------------
| Frontend Routes - Enrollment Module
|--------------------------------------------------------------------------
|
| Routes for student-facing functionality (e.g., viewing their groups)
|
*/

Route::prefix('frontend/enrollment')
    ->middleware(['tenant', 'tenant.auth'])
    ->group(function () {
        // Student Groups - My Groups
        Route::get('/my-groups', [StudentGroupController::class, 'myGroups'])
            ->name('frontend.enrollment.my-groups');

        Route::get('/my-groups/year/{academicYearId}', [StudentGroupController::class, 'myGroupsByYear'])
            ->name('frontend.enrollment.my-groups-by-year');

        // Student Card - My Card
        Route::get('/my-card', [MyCardController::class, 'show'])
            ->name('frontend.enrollment.my-card');

        Route::get('/my-card/history', [MyCardController::class, 'history'])
            ->name('frontend.enrollment.my-card-history');

        Route::get('/my-card/download', [MyCardController::class, 'download'])
            ->name('frontend.enrollment.my-card-download');

        Route::get('/my-card/year/{academicYearId}', [MyCardController::class, 'showByYear'])
            ->name('frontend.enrollment.my-card-by-year');

        Route::get('/my-card/qr-code', [MyCardController::class, 'qrCode'])
            ->name('frontend.enrollment.my-card-qrcode');
    });

/*
 * Story Étudiant 01 — Portail / Home Étudiant.
 * Routes /api/frontend/student/* protégées par role:Étudiant + ownership via auth()->user()->student.
 */
Route::prefix('frontend/student')
    ->middleware(['tenant', 'tenant.auth', 'role:Élève|Administrator,tenant'])
    ->group(function () {
        $controller = \Modules\Enrollment\Http\Controllers\Frontend\StudentDashboardController::class;

        Route::get('/me', [$controller, 'me'])->name('frontend.student.me');
        Route::get('/dashboard', [$controller, 'dashboard'])->name('frontend.student.dashboard');

        // Story Étudiant 02 — Mes notes
        Route::get('/my-grades', [$controller, 'myGrades'])->name('frontend.student.grades');

        // Story Étudiant 04 — Mes présences
        Route::get('/my-attendance', [$controller, 'myAttendance'])->name('frontend.student.attendance');

        // Story Étudiant 05 — Mes factures
        Route::get('/my-invoices', [$controller, 'myInvoices'])->name('frontend.student.invoices');

        // Story Étudiant 06 — Mes documents
        Route::get('/my-documents', [$controller, 'myDocuments'])->name('frontend.student.documents');

        // Story Étudiant 03 — Mon emploi du temps
        Route::get('/my-timetable', [$controller, 'myTimetable'])->name('frontend.student.timetable');

        // Story Étudiant 07 — Ma carte étudiante
        Route::get('/my-card', [$controller, 'myCard'])->name('frontend.student.card');

        // Story Étudiant 08 — Réinscription
        Route::get('/reenrollment', [$controller, 'reenrollment'])->name('frontend.student.reenrollment');
        Route::post('/reenrollment/intention', [$controller, 'storeReenrollmentIntention'])->name('frontend.student.reenrollment-intention');
    });
