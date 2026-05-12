<?php

use Illuminate\Support\Facades\Route;
use Modules\Enrollment\Http\Controllers\Frontend\ExemptionRequestController;
use Modules\Enrollment\Http\Controllers\Frontend\MyCardController;
use Modules\Enrollment\Http\Controllers\Frontend\MyEnrollmentController;
use Modules\Enrollment\Http\Controllers\Frontend\ReenrollmentController;
use Modules\Enrollment\Http\Controllers\Frontend\StudentGroupController;
use Modules\Enrollment\Http\Controllers\Frontend\TransferRequestController;

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

        // Pedagogical Enrollment - My Status
        Route::get('/my-enrollment/status', [MyEnrollmentController::class, 'status'])
            ->name('frontend.enrollment.my-enrollment-status');

        Route::get('/my-enrollment/history', [MyEnrollmentController::class, 'history'])
            ->name('frontend.enrollment.my-enrollment-history');

        Route::get('/my-enrollment/contract', [MyEnrollmentController::class, 'downloadContract'])
            ->name('frontend.enrollment.my-enrollment-contract');

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

        // =====================================================================
        // Reenrollment - Réinscription (Story 11)
        // =====================================================================
        Route::prefix('reenrollment')->group(function () {
            // Campagnes ouvertes
            Route::get('/campaigns', [ReenrollmentController::class, 'campaigns'])
                ->name('frontend.enrollment.reenrollment-campaigns');

            // Vérifier éligibilité
            Route::post('/check-eligibility', [ReenrollmentController::class, 'checkEligibility'])
                ->name('frontend.enrollment.reenrollment-check-eligibility');

            // Créer une réinscription
            Route::post('/', [ReenrollmentController::class, 'create'])
                ->name('frontend.enrollment.reenrollment-create');

            // Mettre à jour ma réinscription
            Route::put('/{reenrollment}', [ReenrollmentController::class, 'update'])
                ->name('frontend.enrollment.reenrollment-update');

            // Soumettre ma réinscription
            Route::post('/{reenrollment}/submit', [ReenrollmentController::class, 'submit'])
                ->name('frontend.enrollment.reenrollment-submit');

            // Mon statut de réinscription
            Route::get('/my-status', [ReenrollmentController::class, 'myStatus'])
                ->name('frontend.enrollment.reenrollment-my-status');

            // Détails de ma réinscription
            Route::get('/{reenrollment}', [ReenrollmentController::class, 'show'])
                ->name('frontend.enrollment.reenrollment-show');

            // Télécharger ma confirmation
            Route::get('/{reenrollment}/confirmation', [ReenrollmentController::class, 'downloadConfirmation'])
                ->name('frontend.enrollment.reenrollment-confirmation');
        });

        // =====================================================================
        // Transfer Request - Demande de transfert (Story 12)
        // =====================================================================
        Route::prefix('transfer')->group(function () {
            // Programmes disponibles
            Route::get('/programs', [TransferRequestController::class, 'availablePrograms'])
                ->name('frontend.enrollment.transfer-programs');

            // Année académique active
            Route::get('/academic-year', [TransferRequestController::class, 'activeAcademicYear'])
                ->name('frontend.enrollment.transfer-academic-year');

            // Soumettre une demande
            Route::post('/', [TransferRequestController::class, 'request'])
                ->name('frontend.enrollment.transfer-request');

            // Vérifier statut (public avec numéro + email)
            Route::post('/check-status', [TransferRequestController::class, 'checkStatus'])
                ->name('frontend.enrollment.transfer-check-status');

            // Mes demandes (authentifié)
            Route::get('/my-requests', [TransferRequestController::class, 'myRequests'])
                ->name('frontend.enrollment.transfer-my-requests');
        });

        // =====================================================================
        // Exemption Request - Demande de dispense (Story 13)
        // =====================================================================
        Route::prefix('exemption')->group(function () {
            // Modules disponibles pour demande
            Route::get('/available-modules', [ExemptionRequestController::class, 'availableModules'])
                ->name('frontend.enrollment.exemption-available-modules');

            // Soumettre une demande
            Route::post('/', [ExemptionRequestController::class, 'request'])
                ->name('frontend.enrollment.exemption-request');

            // Mes demandes
            Route::get('/my-requests', [ExemptionRequestController::class, 'myRequests'])
                ->name('frontend.enrollment.exemption-my-requests');

            // Détails d'une demande
            Route::get('/{exemption}', [ExemptionRequestController::class, 'show'])
                ->name('frontend.enrollment.exemption-show');

            // Télécharger attestation
            Route::get('/{exemption}/certificate', [ExemptionRequestController::class, 'downloadCertificate'])
                ->name('frontend.enrollment.exemption-certificate');
        });
    });

/*
 * Story Étudiant 01 — Portail / Home Étudiant.
 * Routes /api/frontend/student/* protégées par role:Étudiant + ownership via auth()->user()->student.
 */
Route::prefix('frontend/student')
    ->middleware(['tenant', 'tenant.auth', 'role:Étudiant|Administrator,tenant'])
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
    });
