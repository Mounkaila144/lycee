<?php

use Illuminate\Support\Facades\Route;
use Modules\Enrollment\Http\Controllers\Admin\EnrollmentController;
use Modules\Enrollment\Http\Controllers\Admin\EnrollmentStatisticsController;
use Modules\Enrollment\Http\Controllers\Admin\EnrollmentValidationController;
use Modules\Enrollment\Http\Controllers\Admin\EquivalenceController;
use Modules\Enrollment\Http\Controllers\Admin\ExemptionController;
use Modules\Enrollment\Http\Controllers\Admin\GroupController;
use Modules\Enrollment\Http\Controllers\Admin\GroupExportController;
use Modules\Enrollment\Http\Controllers\Admin\OptionController;
use Modules\Enrollment\Http\Controllers\Admin\ReenrollmentCampaignController;
use Modules\Enrollment\Http\Controllers\Admin\ReenrollmentController;
use Modules\Enrollment\Http\Controllers\Admin\StudentCardController;
use Modules\Enrollment\Http\Controllers\Admin\StudentController;
use Modules\Enrollment\Http\Controllers\Admin\TransferController;

/*
|--------------------------------------------------------------------------
| Admin API Routes - Enrollment Module
|--------------------------------------------------------------------------
|
| Routes for administrative management of student enrollments
|
*/

Route::prefix('admin/enrollment')
    ->middleware(['tenant', 'tenant.auth'])
    ->group(function () {
        // Students CRUD (dossiers étudiants - different from UsersGuard /admin/students user accounts)
        Route::prefix('students')->group(function () {
            Route::get('/', [StudentController::class, 'index'])->name('admin.enrollment.students.index');
            Route::post('/', [StudentController::class, 'store'])->name('admin.enrollment.students.store');

            // Search and Export (must be before /{student} to avoid conflict)
            Route::get('/search/autocomplete', [StudentController::class, 'autocomplete'])
                ->name('admin.enrollment.students.autocomplete');
            Route::get('/export', [StudentController::class, 'export'])
                ->name('admin.enrollment.students.export');

            // Statistics (must be before /{student} to avoid conflict)
            Route::get('/statistics/summary', [StudentController::class, 'statistics'])
                ->name('admin.enrollment.students.statistics');

            // Status statistics (must be before /{student} to avoid conflict)
            Route::get('/status/statistics', [StudentController::class, 'statusStats'])
                ->name('admin.enrollment.students.status-stats');

            // CSV Import (must be before /{student} to avoid conflict)
            Route::get('/import/template', [StudentController::class, 'downloadImportTemplate'])
                ->name('admin.enrollment.students.import-template');
            Route::post('/import/preview', [StudentController::class, 'previewImport'])
                ->name('admin.enrollment.students.import-preview');
            Route::post('/import/revalidate-row', [StudentController::class, 'revalidateImportRow'])
                ->name('admin.enrollment.students.import-revalidate-row');
            Route::post('/import/confirm', [StudentController::class, 'confirmImport'])
                ->name('admin.enrollment.students.import-confirm');

            Route::get('/{student}', [StudentController::class, 'show'])->name('admin.enrollment.students.show');
            Route::put('/{student}', [StudentController::class, 'update'])->name('admin.enrollment.students.update');
            Route::delete('/{student}', [StudentController::class, 'destroy'])->name('admin.enrollment.students.destroy');

            // Document completeness check
            Route::get('/{student}/check-completeness', [StudentController::class, 'checkCompleteness'])
                ->name('admin.enrollment.students.check-completeness');

            // Audit log
            Route::get('/{student}/audit-log', [StudentController::class, 'auditLog'])
                ->name('admin.enrollment.students.audit-log');

            // Document management
            Route::post('/{student}/documents', [StudentController::class, 'uploadDocument'])
                ->name('admin.enrollment.students.upload-document');
            Route::get('/{student}/documents/{document}', [StudentController::class, 'downloadDocument'])
                ->name('admin.enrollment.students.download-document');

            // Status management
            Route::post('/{student}/status', [StudentController::class, 'changeStatus'])
                ->name('admin.enrollment.students.change-status');
            Route::get('/{student}/status/history', [StudentController::class, 'statusHistory'])
                ->name('admin.enrollment.students.status-history');
            Route::get('/{student}/status/transitions', [StudentController::class, 'availableTransitions'])
                ->name('admin.enrollment.students.available-transitions');
        });

        // Check for duplicates
        Route::post('students/check-duplicates', [StudentController::class, 'checkDuplicates'])
            ->name('admin.enrollment.students.check-duplicates');

        // Enrollments CRUD (inscriptions pédagogiques)
        Route::prefix('enrollments')->group(function () {
            Route::get('/', [EnrollmentController::class, 'index'])->name('admin.enrollment.enrollments.index');
            Route::post('/', [EnrollmentController::class, 'store'])->name('admin.enrollment.enrollments.store');

            // Available modules for enrollment (must be before /{enrollment})
            Route::get('/available-modules', [EnrollmentController::class, 'availableModules'])
                ->name('admin.enrollment.enrollments.available-modules');

            // Module enrollments query
            Route::get('/module-enrollments', [EnrollmentController::class, 'moduleEnrollments'])
                ->name('admin.enrollment.enrollments.module-enrollments');

            // Students in module
            Route::get('/students-in-module', [EnrollmentController::class, 'studentsInModule'])
                ->name('admin.enrollment.enrollments.students-in-module');

            // Statistics
            Route::get('/statistics', [EnrollmentController::class, 'statistics'])
                ->name('admin.enrollment.enrollments.statistics');

            // Check module prerequisites
            Route::post('/check-prerequisites', [EnrollmentController::class, 'checkModulePrerequisites'])
                ->name('admin.enrollment.enrollments.check-prerequisites');

            Route::get('/{enrollment}', [EnrollmentController::class, 'show'])->name('admin.enrollment.enrollments.show');
            Route::put('/{enrollment}', [EnrollmentController::class, 'update'])->name('admin.enrollment.enrollments.update');
            Route::delete('/{enrollment}', [EnrollmentController::class, 'destroy'])->name('admin.enrollment.enrollments.destroy');

            // Module management for an enrollment
            Route::post('/{enrollment}/modules', [EnrollmentController::class, 'addModules'])
                ->name('admin.enrollment.enrollments.add-modules');
            Route::delete('/{enrollment}/modules', [EnrollmentController::class, 'removeModules'])
                ->name('admin.enrollment.enrollments.remove-modules');

            // Download enrollment sheet PDF
            Route::get('/{enrollment}/sheet', [EnrollmentController::class, 'downloadEnrollmentSheet'])
                ->name('admin.enrollment.enrollments.download-sheet');
        });

        // Module enrollment status update
        Route::put('/module-enrollments/{moduleEnrollment}', [EnrollmentController::class, 'updateModuleEnrollment'])
            ->name('admin.enrollment.module-enrollments.update');

        // Options CRUD (choix d'options et spécialités)
        Route::prefix('options')->group(function () {
            Route::get('/', [OptionController::class, 'index'])->name('admin.enrollment.options.index');
            Route::post('/', [OptionController::class, 'store'])->name('admin.enrollment.options.store');

            // Assignment operations (must be before /{option})
            Route::post('/assign', [OptionController::class, 'assign'])
                ->name('admin.enrollment.options.assign');
            Route::post('/assign-manual', [OptionController::class, 'assignManual'])
                ->name('admin.enrollment.options.assign-manual');

            // Global statistics (must be before /{option})
            Route::get('/statistics/global', [OptionController::class, 'globalStatistics'])
                ->name('admin.enrollment.options.global-statistics');

            // Student choices management (must be before /{option})
            Route::post('/choices', [OptionController::class, 'storeChoice'])
                ->name('admin.enrollment.options.store-choice');
            Route::get('/student-choices', [OptionController::class, 'studentChoices'])
                ->name('admin.enrollment.options.student-choices');

            // Check prerequisites (must be before /{option})
            Route::post('/check-prerequisites', [OptionController::class, 'checkPrerequisites'])
                ->name('admin.enrollment.options.check-prerequisites');

            Route::get('/{option}', [OptionController::class, 'show'])->name('admin.enrollment.options.show');
            Route::put('/{option}', [OptionController::class, 'update'])->name('admin.enrollment.options.update');
            Route::delete('/{option}', [OptionController::class, 'destroy'])->name('admin.enrollment.options.destroy');

            // Option choices and assignments
            Route::get('/{option}/choices', [OptionController::class, 'choices'])
                ->name('admin.enrollment.options.choices');
            Route::get('/{option}/assignments', [OptionController::class, 'assignments'])
                ->name('admin.enrollment.options.assignments');
            Route::get('/{option}/statistics', [OptionController::class, 'statistics'])
                ->name('admin.enrollment.options.statistics');
        });

        // Option assignment deletion
        Route::delete('/option-assignments/{assignment}', [OptionController::class, 'removeAssignment'])
            ->name('admin.enrollment.option-assignments.destroy');

        // Pedagogical Enrollment Validation
        Route::prefix('validation')->group(function () {
            Route::get('/pending', [EnrollmentValidationController::class, 'pending'])
                ->name('admin.enrollment.validation.pending');
            Route::get('/stats', [EnrollmentValidationController::class, 'stats'])
                ->name('admin.enrollment.validation.stats');
            Route::post('/batch-validate', [EnrollmentValidationController::class, 'batchValidate'])
                ->name('admin.enrollment.validation.batch-validate');

            Route::get('/{id}', [EnrollmentValidationController::class, 'show'])
                ->name('admin.enrollment.validation.show');
            Route::get('/{id}/check', [EnrollmentValidationController::class, 'check'])
                ->name('admin.enrollment.validation.check');
            Route::post('/{id}/validate', [EnrollmentValidationController::class, 'validate'])
                ->name('admin.enrollment.validation.validate');
            Route::post('/{id}/reject', [EnrollmentValidationController::class, 'reject'])
                ->name('admin.enrollment.validation.reject');
            Route::get('/{id}/contract', [EnrollmentValidationController::class, 'downloadContract'])
                ->name('admin.enrollment.validation.contract');
        });

        // Groups CRUD (TD/TP group management)
        Route::prefix('groups')->group(function () {
            Route::get('/', [GroupController::class, 'index'])->name('admin.enrollment.groups.index');
            Route::post('/', [GroupController::class, 'store'])->name('admin.enrollment.groups.store');

            // Unassigned students (must be before /{group} to avoid conflict)
            Route::get('/unassigned-students', [GroupController::class, 'unassignedStudents'])
                ->name('admin.enrollment.groups.unassigned-students');

            // Auto-assignment preview and execution (must be before /{group})
            Route::post('/auto-assign/preview', [GroupController::class, 'previewAutoAssign'])
                ->name('admin.enrollment.groups.auto-assign-preview');
            Route::post('/auto-assign', [GroupController::class, 'autoAssign'])
                ->name('admin.enrollment.groups.auto-assign');

            Route::get('/{group}', [GroupController::class, 'show'])->name('admin.enrollment.groups.show');
            Route::put('/{group}', [GroupController::class, 'update'])->name('admin.enrollment.groups.update');
            Route::delete('/{group}', [GroupController::class, 'destroy'])->name('admin.enrollment.groups.destroy');

            // Student assignment
            Route::post('/{group}/assign-student', [GroupController::class, 'assignStudent'])
                ->name('admin.enrollment.groups.assign-student');
            Route::post('/{group}/move-student', [GroupController::class, 'moveStudent'])
                ->name('admin.enrollment.groups.move-student');
            Route::get('/{group}/students', [GroupController::class, 'students'])
                ->name('admin.enrollment.groups.students');
            Route::get('/{group}/students/export', [GroupController::class, 'exportStudents'])
                ->name('admin.enrollment.groups.export-students');
            Route::get('/{group}/statistics', [GroupController::class, 'statistics'])
                ->name('admin.enrollment.groups.statistics');
        });

        // Group assignment deletion
        Route::delete('/group-assignments/{assignment}', [GroupController::class, 'removeAssignment'])
            ->name('admin.enrollment.group-assignments.destroy');

        // Student Cards (cartes étudiants)
        Route::prefix('student-cards')->group(function () {
            Route::get('/', [StudentCardController::class, 'index'])
                ->name('admin.enrollment.student-cards.index');

            // Batch operations (must be before /{id})
            Route::post('/batch-generate', [StudentCardController::class, 'batchGenerate'])
                ->name('admin.enrollment.student-cards.batch-generate');
            Route::post('/verify', [StudentCardController::class, 'verify'])
                ->name('admin.enrollment.student-cards.verify');
            Route::get('/statistics', [StudentCardController::class, 'statistics'])
                ->name('admin.enrollment.student-cards.statistics');

            // Generate card for specific student
            Route::post('/generate/{studentId}', [StudentCardController::class, 'generate'])
                ->name('admin.enrollment.student-cards.generate');

            Route::get('/{id}', [StudentCardController::class, 'show'])
                ->name('admin.enrollment.student-cards.show');
            Route::post('/{id}/duplicate', [StudentCardController::class, 'duplicate'])
                ->name('admin.enrollment.student-cards.duplicate');
            Route::patch('/{id}/status', [StudentCardController::class, 'updateStatus'])
                ->name('admin.enrollment.student-cards.update-status');
            Route::patch('/{id}/print-status', [StudentCardController::class, 'updatePrintStatus'])
                ->name('admin.enrollment.student-cards.update-print-status');
            Route::get('/{id}/download', [StudentCardController::class, 'download'])
                ->name('admin.enrollment.student-cards.download');
            Route::delete('/{id}', [StudentCardController::class, 'destroy'])
                ->name('admin.enrollment.student-cards.destroy');
        });

        // Reenrollment Campaigns (Campagnes de réinscription)
        Route::prefix('reenrollment-campaigns')->group(function () {
            Route::get('/', [ReenrollmentCampaignController::class, 'index'])
                ->name('admin.enrollment.reenrollment-campaigns.index');
            Route::post('/', [ReenrollmentCampaignController::class, 'store'])
                ->name('admin.enrollment.reenrollment-campaigns.store');

            Route::get('/{campaign}', [ReenrollmentCampaignController::class, 'show'])
                ->name('admin.enrollment.reenrollment-campaigns.show');
            Route::put('/{campaign}', [ReenrollmentCampaignController::class, 'update'])
                ->name('admin.enrollment.reenrollment-campaigns.update');
            Route::delete('/{campaign}', [ReenrollmentCampaignController::class, 'destroy'])
                ->name('admin.enrollment.reenrollment-campaigns.destroy');

            Route::post('/{campaign}/activate', [ReenrollmentCampaignController::class, 'activate'])
                ->name('admin.enrollment.reenrollment-campaigns.activate');
            Route::post('/{campaign}/close', [ReenrollmentCampaignController::class, 'close'])
                ->name('admin.enrollment.reenrollment-campaigns.close');
            Route::get('/{campaign}/statistics', [ReenrollmentCampaignController::class, 'statistics'])
                ->name('admin.enrollment.reenrollment-campaigns.statistics');
            Route::get('/{campaign}/eligible-students', [ReenrollmentCampaignController::class, 'eligibleStudents'])
                ->name('admin.enrollment.reenrollment-campaigns.eligible-students');
        });

        // Reenrollments (Réinscriptions)
        Route::prefix('reenrollments')->group(function () {
            Route::get('/', [ReenrollmentController::class, 'index'])
                ->name('admin.enrollment.reenrollments.index');
            Route::post('/', [ReenrollmentController::class, 'store'])
                ->name('admin.enrollment.reenrollments.store');

            // Must be before /{reenrollment}
            Route::post('/check-eligibility', [ReenrollmentController::class, 'checkEligibility'])
                ->name('admin.enrollment.reenrollments.check-eligibility');
            Route::post('/batch-validate', [ReenrollmentController::class, 'batchValidate'])
                ->name('admin.enrollment.reenrollments.batch-validate');
            Route::get('/statistics', [ReenrollmentController::class, 'statistics'])
                ->name('admin.enrollment.reenrollments.statistics');

            Route::get('/{reenrollment}', [ReenrollmentController::class, 'show'])
                ->name('admin.enrollment.reenrollments.show');
            Route::post('/{reenrollment}/validate', [ReenrollmentController::class, 'validate'])
                ->name('admin.enrollment.reenrollments.validate');
            Route::post('/{reenrollment}/reject', [ReenrollmentController::class, 'reject'])
                ->name('admin.enrollment.reenrollments.reject');
            Route::get('/{reenrollment}/confirmation', [ReenrollmentController::class, 'downloadConfirmation'])
                ->name('admin.enrollment.reenrollments.download-confirmation');
        });

        // Transfers (Transferts)
        Route::prefix('transfers')->group(function () {
            Route::get('/', [TransferController::class, 'index'])
                ->name('admin.enrollment.transfers.index');
            Route::post('/', [TransferController::class, 'store'])
                ->name('admin.enrollment.transfers.store');

            // Must be before /{transfer}
            Route::get('/statistics', [TransferController::class, 'statistics'])
                ->name('admin.enrollment.transfers.statistics');

            Route::get('/{transfer}', [TransferController::class, 'show'])
                ->name('admin.enrollment.transfers.show');
            Route::post('/{transfer}/start-review', [TransferController::class, 'startReview'])
                ->name('admin.enrollment.transfers.start-review');
            Route::post('/{transfer}/analyze', [TransferController::class, 'analyzeEquivalences'])
                ->name('admin.enrollment.transfers.analyze');
            Route::post('/{transfer}/validate', [TransferController::class, 'validate'])
                ->name('admin.enrollment.transfers.validate');
            Route::post('/{transfer}/integrate', [TransferController::class, 'integrate'])
                ->name('admin.enrollment.transfers.integrate');
            Route::post('/{transfer}/reject', [TransferController::class, 'reject'])
                ->name('admin.enrollment.transfers.reject');
            Route::get('/{transfer}/certificate', [TransferController::class, 'downloadCertificate'])
                ->name('admin.enrollment.transfers.download-certificate');

            // Equivalences for a transfer
            Route::get('/{transfer}/equivalences', [EquivalenceController::class, 'index'])
                ->name('admin.enrollment.transfers.equivalences.index');
            Route::post('/{transfer}/equivalences', [EquivalenceController::class, 'store'])
                ->name('admin.enrollment.transfers.equivalences.store');
            Route::post('/{transfer}/equivalences/batch-validate', [EquivalenceController::class, 'batchValidate'])
                ->name('admin.enrollment.transfers.equivalences.batch-validate');
        });

        // Equivalences (standalone operations)
        Route::prefix('equivalences')->group(function () {
            Route::get('/{equivalence}', [EquivalenceController::class, 'show'])
                ->name('admin.enrollment.equivalences.show');
            Route::put('/{equivalence}', [EquivalenceController::class, 'update'])
                ->name('admin.enrollment.equivalences.update');
            Route::delete('/{equivalence}', [EquivalenceController::class, 'destroy'])
                ->name('admin.enrollment.equivalences.destroy');
            Route::post('/{equivalence}/validate', [EquivalenceController::class, 'validate'])
                ->name('admin.enrollment.equivalences.validate');
            Route::post('/{equivalence}/reject', [EquivalenceController::class, 'reject'])
                ->name('admin.enrollment.equivalences.reject');
        });

        // Module Exemptions (Dispenses de modules)
        Route::prefix('exemptions')->group(function () {
            Route::get('/', [ExemptionController::class, 'index'])
                ->name('admin.enrollment.exemptions.index');
            Route::post('/', [ExemptionController::class, 'store'])
                ->name('admin.enrollment.exemptions.store');

            // Must be before /{exemption}
            Route::get('/pending', [ExemptionController::class, 'pending'])
                ->name('admin.enrollment.exemptions.pending');
            Route::get('/statistics', [ExemptionController::class, 'statistics'])
                ->name('admin.enrollment.exemptions.statistics');

            Route::get('/{exemption}', [ExemptionController::class, 'show'])
                ->name('admin.enrollment.exemptions.show');
            Route::post('/{exemption}/teacher-review', [ExemptionController::class, 'teacherReview'])
                ->name('admin.enrollment.exemptions.teacher-review');
            Route::post('/{exemption}/validate', [ExemptionController::class, 'validate'])
                ->name('admin.enrollment.exemptions.validate');
            Route::post('/{exemption}/revoke', [ExemptionController::class, 'revoke'])
                ->name('admin.enrollment.exemptions.revoke');
            Route::get('/{exemption}/certificate', [ExemptionController::class, 'downloadCertificate'])
                ->name('admin.enrollment.exemptions.download-certificate');
        });

        // Statistics & Reports (Statistiques et Rapports)
        Route::prefix('statistics')->group(function () {
            Route::get('/kpis', [EnrollmentStatisticsController::class, 'kpis'])
                ->name('admin.enrollment.statistics.kpis');
            Route::get('/by-program', [EnrollmentStatisticsController::class, 'byProgram'])
                ->name('admin.enrollment.statistics.by-program');
            Route::get('/trends', [EnrollmentStatisticsController::class, 'trends'])
                ->name('admin.enrollment.statistics.trends');
            Route::get('/demographics', [EnrollmentStatisticsController::class, 'demographics'])
                ->name('admin.enrollment.statistics.demographics');
            Route::get('/pedagogical', [EnrollmentStatisticsController::class, 'pedagogical'])
                ->name('admin.enrollment.statistics.pedagogical');
            Route::get('/monthly-trends', [EnrollmentStatisticsController::class, 'monthlyTrends'])
                ->name('admin.enrollment.statistics.monthly-trends');
            Route::get('/status', [EnrollmentStatisticsController::class, 'statusStatistics'])
                ->name('admin.enrollment.statistics.status');
            Route::get('/comparison', [EnrollmentStatisticsController::class, 'comparison'])
                ->name('admin.enrollment.statistics.comparison');
            Route::get('/alerts', [EnrollmentStatisticsController::class, 'alerts'])
                ->name('admin.enrollment.statistics.alerts');
            Route::post('/clear-cache', [EnrollmentStatisticsController::class, 'clearCache'])
                ->name('admin.enrollment.statistics.clear-cache');
        });

        // Reports (Rapports)
        Route::prefix('reports')->group(function () {
            Route::post('/executive-summary', [EnrollmentStatisticsController::class, 'generateExecutiveSummary'])
                ->name('admin.enrollment.reports.executive-summary');
            Route::post('/dashboard', [EnrollmentStatisticsController::class, 'generateDashboard'])
                ->name('admin.enrollment.reports.dashboard');
            Route::get('/export/excel', [EnrollmentStatisticsController::class, 'exportExcel'])
                ->name('admin.enrollment.reports.export-excel');
            Route::get('/download', [EnrollmentStatisticsController::class, 'downloadReport'])
                ->name('admin.enrollment.reports.download');
        });

        // Group Exports (Export listes groupes)
        Route::prefix('group-exports')->group(function () {
            Route::get('/templates', [GroupExportController::class, 'templates'])
                ->name('admin.enrollment.group-exports.templates');
            Route::post('/batch', [GroupExportController::class, 'batchExport'])
                ->name('admin.enrollment.group-exports.batch');
            Route::get('/{group}/pdf', [GroupExportController::class, 'exportPdf'])
                ->name('admin.enrollment.group-exports.pdf');
            Route::get('/{group}/excel', [GroupExportController::class, 'exportExcel'])
                ->name('admin.enrollment.group-exports.excel');
            Route::get('/{group}/csv', [GroupExportController::class, 'exportCsv'])
                ->name('admin.enrollment.group-exports.csv');
            Route::get('/{group}/attendance-sheet', [GroupExportController::class, 'attendanceSheet'])
                ->name('admin.enrollment.group-exports.attendance-sheet');
        });
    });
