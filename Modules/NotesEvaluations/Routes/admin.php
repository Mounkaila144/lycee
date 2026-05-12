<?php

use Illuminate\Support\Facades\Route;
use Modules\NotesEvaluations\Http\Controllers\Admin\AbsenceReviewController;
use Modules\NotesEvaluations\Http\Controllers\Admin\AnalyticsController;
use Modules\NotesEvaluations\Http\Controllers\Admin\CoefficientController;
use Modules\NotesEvaluations\Http\Controllers\Admin\CompensationController;
use Modules\NotesEvaluations\Http\Controllers\Admin\CorrectionApprovalController;
use Modules\NotesEvaluations\Http\Controllers\Admin\DeliberationController;
use Modules\NotesEvaluations\Http\Controllers\Admin\EctsController;
use Modules\NotesEvaluations\Http\Controllers\Admin\EliminatoryController;
use Modules\NotesEvaluations\Http\Controllers\Admin\FinalResultsController;
use Modules\NotesEvaluations\Http\Controllers\Admin\GradeValidationController;
use Modules\NotesEvaluations\Http\Controllers\Admin\ModuleAverageController;
use Modules\NotesEvaluations\Http\Controllers\Admin\ModuleResultController;
use Modules\NotesEvaluations\Http\Controllers\Admin\ProcesVerbalController;
use Modules\NotesEvaluations\Http\Controllers\Admin\PublicationController;
use Modules\NotesEvaluations\Http\Controllers\Admin\RankingController;
use Modules\NotesEvaluations\Http\Controllers\Admin\RetakeController;
use Modules\NotesEvaluations\Http\Controllers\Admin\RetakeGradeValidationController;
use Modules\NotesEvaluations\Http\Controllers\Admin\RetakeRecalculationController;
use Modules\NotesEvaluations\Http\Controllers\Admin\SemesterResultController;
use Modules\NotesEvaluations\Http\Controllers\Admin\StatisticsController;

/*
|--------------------------------------------------------------------------
| Admin API Routes - Notes & Evaluations Module
|--------------------------------------------------------------------------
*/

// RBAC durcissement (Stories Admin 06, Manager 05) : seuls Admin et Manager
// accèdent au pilotage des notes/évaluations admin. Les Profs passent par teacher.php.
Route::prefix('api/admin')
    ->middleware(['tenant', 'tenant.auth', 'role:Administrator|Manager,tenant'])
    ->group(function () {
        // Grade Validations
        Route::prefix('grade-validations')->group(function () {
            Route::get('/', [GradeValidationController::class, 'index']);
            Route::get('/statistics', [GradeValidationController::class, 'statistics']);
            Route::get('/{validation}', [GradeValidationController::class, 'show']);
            Route::post('/{validation}/validate', [GradeValidationController::class, 'validate']);
            Route::post('/{validation}/reject', [GradeValidationController::class, 'reject']);
            Route::post('/{validation}/publish', [GradeValidationController::class, 'publish']);
            Route::post('/bulk-publish', [GradeValidationController::class, 'bulkPublish']);
        });

        // Correction Requests
        Route::prefix('correction-requests')->group(function () {
            Route::get('/', [CorrectionApprovalController::class, 'index']);
            Route::get('/{request}', [CorrectionApprovalController::class, 'show']);
            Route::post('/{request}/approve', [CorrectionApprovalController::class, 'approve']);
            Route::post('/{request}/reject', [CorrectionApprovalController::class, 'reject']);
        });

        // Absence Justification Review
        Route::prefix('absence-justifications')->group(function () {
            Route::get('/', [AbsenceReviewController::class, 'index']);
            Route::get('/statistics', [AbsenceReviewController::class, 'statistics']);
            Route::get('/{justification}', [AbsenceReviewController::class, 'show']);
            Route::get('/{justification}/download', [AbsenceReviewController::class, 'download']);
            Route::post('/{justification}/approve', [AbsenceReviewController::class, 'approve']);
            Route::post('/{justification}/reject', [AbsenceReviewController::class, 'reject']);
            Route::post('/bulk-approve', [AbsenceReviewController::class, 'bulkApprove']);
            Route::post('/bulk-reject', [AbsenceReviewController::class, 'bulkReject']);
        });

        // Module Audit Trail
        Route::get('/modules/{module}/audit-trail', [GradeValidationController::class, 'auditTrail']);

        // Module Averages
        Route::prefix('modules/{module}/averages')->group(function () {
            Route::get('/', [ModuleAverageController::class, 'index']);
            Route::get('/statistics', [ModuleAverageController::class, 'statistics']);
            Route::post('/recalculate', [ModuleAverageController::class, 'recalculate']);
        });

        // Module Results (Statistics & Rankings)
        Route::prefix('modules/{module}/semesters/{semester}/results')->group(function () {
            Route::get('/', [ModuleResultController::class, 'show']);
            Route::post('/generate', [ModuleResultController::class, 'generate']);
            Route::post('/publish', [ModuleResultController::class, 'publish']);
            Route::get('/students-by-status', [ModuleResultController::class, 'studentsByStatus']);
            Route::get('/export', [ModuleResultController::class, 'export']);
        });

        // Student Module Grades
        Route::get('/students/{student}/semesters/{semester}/module-grades', [ModuleAverageController::class, 'studentGrades']);

        // Semester Results
        Route::prefix('semesters/{semester}/results')->group(function () {
            Route::get('/', [SemesterResultController::class, 'index']);
            Route::get('/statistics', [SemesterResultController::class, 'statistics']);
            Route::get('/students-by-status', [SemesterResultController::class, 'studentsByStatus']);
            Route::post('/recalculate', [SemesterResultController::class, 'recalculate']);
            Route::post('/publish', [SemesterResultController::class, 'publish']);
            Route::get('/blocked-by-eliminatory', [SemesterResultController::class, 'blockedByEliminatory']);
        });

        // Student Semester Result
        Route::get('/students/{student}/semesters/{semester}/result', [SemesterResultController::class, 'show']);

        // Coefficients
        Route::prefix('modules/{module}/coefficients')->group(function () {
            Route::get('/', [CoefficientController::class, 'index']);
            Route::put('/credits', [CoefficientController::class, 'updateCredits']);
            Route::get('/credits-history', [CoefficientController::class, 'creditsHistory']);
            Route::post('/apply-template', [CoefficientController::class, 'applyTemplate']);
        });

        Route::prefix('evaluations/{evaluation}')->group(function () {
            Route::put('/coefficient', [CoefficientController::class, 'updateCoefficient']);
            Route::post('/simulate-impact', [CoefficientController::class, 'simulateImpact']);
            Route::get('/coefficient-history', [CoefficientController::class, 'coefficientHistory']);
        });

        // Coefficient Templates
        Route::prefix('coefficient-templates')->group(function () {
            Route::get('/', [CoefficientController::class, 'templates']);
            Route::post('/', [CoefficientController::class, 'storeTemplate']);
        });

        // Eliminatory Modules
        Route::prefix('semesters/{semester}/eliminatory')->group(function () {
            Route::get('/modules', [EliminatoryController::class, 'index']);
            Route::get('/blocked-students', [EliminatoryController::class, 'blockedStudents']);
            Route::get('/statistics', [EliminatoryController::class, 'statistics']);
        });

        Route::prefix('modules/{module}/eliminatory')->group(function () {
            Route::post('/toggle', [EliminatoryController::class, 'toggle']);
            Route::put('/threshold', [EliminatoryController::class, 'updateThreshold']);
        });

        Route::get('/students/{student}/modules/{module}/semesters/{semester}/eliminatory-status', [EliminatoryController::class, 'studentStatus']);
        Route::get('/students/{student}/semesters/{semester}/failed-eliminatory', [EliminatoryController::class, 'studentFailedModules']);

        // ECTS
        Route::prefix('students/{student}/ects')->group(function () {
            Route::get('/summary', [EctsController::class, 'studentSummary']);
            Route::get('/equivalences', [EctsController::class, 'studentEquivalences']);
            Route::post('/equivalence', [EctsController::class, 'allocateEquivalence']);
            Route::get('/progression/{level}', [EctsController::class, 'checkProgression']);
        });

        Route::get('/students/{student}/semesters/{semester}/ects', [EctsController::class, 'studentSemesterAllocations']);
        Route::post('/students/{student}/semesters/{semester}/ects/recalculate', [EctsController::class, 'recalculate']);
        Route::get('/semesters/{semester}/ects/statistics', [EctsController::class, 'semesterStatistics']);

        // Compensation Rules
        Route::prefix('compensation-rules')->group(function () {
            Route::get('/', [CompensationController::class, 'getRules']);
            Route::put('/', [CompensationController::class, 'updateRules']);
        });

        // Compensation Operations
        Route::prefix('semesters/{semester}/compensation')->group(function () {
            Route::post('/simulate', [CompensationController::class, 'simulate']);
            Route::post('/apply', [CompensationController::class, 'apply']);
            Route::get('/statistics', [CompensationController::class, 'getSemesterStatistics']);
        });

        // Student Compensation
        Route::prefix('students/{student}')->group(function () {
            Route::get('/compensation-history', [CompensationController::class, 'getStudentHistory']);
            Route::get('/semesters/{semester}/compensable-modules', [CompensationController::class, 'getCompensableModules']);
            Route::post('/semesters/{semester}/compensation/apply', [CompensationController::class, 'applyForStudent']);
            Route::get('/modules/{module}/semesters/{semester}/can-compensate', [CompensationController::class, 'canCompensate']);
            Route::delete('/modules/{module}/semesters/{semester}/compensation', [CompensationController::class, 'revoke']);
        });

        // Deliberations (Story 15)
        Route::prefix('deliberations')->group(function () {
            Route::get('/', [DeliberationController::class, 'index']);
            Route::post('/', [DeliberationController::class, 'store']);
            Route::get('/decisions-requiring-review', [DeliberationController::class, 'decisionsRequiringReview']);

            Route::prefix('sessions/{session}')->group(function () {
                Route::get('/', [DeliberationController::class, 'show']);
                Route::post('/start', [DeliberationController::class, 'start']);
                Route::post('/complete', [DeliberationController::class, 'complete']);
                Route::post('/cancel', [DeliberationController::class, 'cancel']);
                Route::get('/pending-students', [DeliberationController::class, 'pendingStudents']);
                Route::get('/deliberated-students', [DeliberationController::class, 'deliberatedStudents']);
                Route::post('/decisions', [DeliberationController::class, 'recordDecision']);
                Route::post('/bulk-decisions', [DeliberationController::class, 'recordBulkDecisions']);
            });

            Route::post('/decisions/{decision}/review', [DeliberationController::class, 'reviewDecision']);
        });

        // Student Deliberation History
        Route::get('/students/{student}/deliberation-history', [DeliberationController::class, 'studentHistory']);

        // Publication (Story 16)
        Route::prefix('publications')->group(function () {
            Route::get('/', [PublicationController::class, 'index']);
            Route::get('/{publication}', [PublicationController::class, 'show']);
            Route::delete('/{publication}', [PublicationController::class, 'unpublish']);
            Route::get('/{publication}/export', [PublicationController::class, 'export']);
        });

        Route::prefix('semesters/{semester}/publication')->group(function () {
            Route::get('/status', [PublicationController::class, 'status']);
            Route::get('/history', [PublicationController::class, 'history']);
            Route::get('/can-publish', [PublicationController::class, 'canPublish']);
            Route::post('/publish', [PublicationController::class, 'publish']);
        });

        // Student Published Results
        Route::get('/students/{student}/published-results', [PublicationController::class, 'studentResults']);

        // Retakes / Rattrapages (Story 17)
        Route::prefix('semesters/{semester}/retakes')->group(function () {
            Route::post('/identify', [RetakeController::class, 'identify']);
            Route::get('/statistics', [RetakeController::class, 'statistics']);
            Route::get('/students', [RetakeController::class, 'studentsList']);
            Route::get('/students/export', [RetakeController::class, 'export']);
            Route::get('/modules', [RetakeController::class, 'modulesList']);
            Route::get('/eligible', [RetakeController::class, 'eligibleStudents']);
        });

        // Module Retake Students
        Route::get('/modules/{module}/retake-students', [RetakeController::class, 'moduleRetakeStudents']);

        // Student Retake Modules
        Route::get('/students/{student}/retake-modules', [RetakeController::class, 'studentRetakeModules']);

        // Retake Enrollment Management
        Route::prefix('retake-enrollments/{retakeEnrollment}')->group(function () {
            Route::get('/', [RetakeController::class, 'show']);
            Route::post('/schedule', [RetakeController::class, 'schedule']);
            Route::post('/cancel', [RetakeController::class, 'cancel']);
        });

        // Retake Grades Validation (Story 18)
        Route::prefix('semesters/{semester}/retake-grades')->group(function () {
            Route::get('/pending', [RetakeGradeValidationController::class, 'pending']);
            Route::get('/modules-pending', [RetakeGradeValidationController::class, 'modulesPending']);
            Route::post('/bulk-validate', [RetakeGradeValidationController::class, 'bulkValidate']);
        });

        Route::prefix('modules/{module}/semesters/{semester}/retake-grades')->group(function () {
            Route::get('/statistics', [RetakeGradeValidationController::class, 'statistics']);
            Route::post('/validate', [RetakeGradeValidationController::class, 'validateGrades']);
            Route::post('/publish', [RetakeGradeValidationController::class, 'publishGrades']);
        });

        Route::post('/retake-grades/{retakeGrade}/reject', [RetakeGradeValidationController::class, 'reject']);

        // Retake Recalculation (Story 19)
        Route::post('/semesters/{semester}/recalculate-after-retake', [RetakeRecalculationController::class, 'recalculateAll']);
        Route::get('/semesters/{semester}/recalculation-logs', [RetakeRecalculationController::class, 'logs']);
        Route::post('/students/{student}/recalculate-retake', [RetakeRecalculationController::class, 'recalculateStudent']);

        // Final Results (Story 20)
        Route::prefix('semesters/{semester}/final-results')->group(function () {
            Route::get('/', [FinalResultsController::class, 'index']);
            Route::get('/can-publish', [FinalResultsController::class, 'canPublish']);
            Route::get('/statistics', [FinalResultsController::class, 'statistics']);
        });

        Route::post('/semesters/{semester}/publish-final-results', [FinalResultsController::class, 'publish']);
        Route::post('/semesters/{semester}/lock-year', [FinalResultsController::class, 'lockYear']);
        Route::get('/semesters/{semester}/is-locked', [FinalResultsController::class, 'isLocked']);

        // Student Final Results
        Route::get('/students/{student}/semesters/{semester}/final-result', [FinalResultsController::class, 'studentResult']);
        Route::get('/students/{student}/semesters/{semester}/debts', [FinalResultsController::class, 'studentDebts']);
        Route::get('/students/{student}/semesters/{semester}/attestation', [FinalResultsController::class, 'downloadAttestation']);

        // Statistics / Rapports (Story 21)
        Route::prefix('statistics')->group(function () {
            // Semester Statistics
            Route::prefix('semesters/{semester}')->group(function () {
                Route::get('/global', [StatisticsController::class, 'global']);
                Route::get('/modules', [StatisticsController::class, 'modules']);
                Route::get('/programmes', [StatisticsController::class, 'programmes']);
                Route::get('/distribution', [StatisticsController::class, 'distribution']);
                Route::get('/top-performers', [StatisticsController::class, 'topPerformers']);
                Route::get('/dashboard', [StatisticsController::class, 'dashboard']);
                Route::get('/export', [StatisticsController::class, 'export']);
            });

            // Academic Year Comparison
            Route::get('/academic-years/{academicYear}/comparison', [StatisticsController::class, 'semesterComparison']);

            // Programme Historical Comparison
            Route::get('/programmes/{programme}/historical', [StatisticsController::class, 'historical']);
        });

        // Ranking / Classements (Story 22)
        Route::prefix('semesters/{semester}/ranking')->group(function () {
            Route::get('/', [RankingController::class, 'index']);
            Route::post('/calculate', [RankingController::class, 'calculate']);
            Route::get('/top', [RankingController::class, 'top']);
            Route::get('/mention-distribution', [RankingController::class, 'mentionDistribution']);
            Route::get('/palmares', [RankingController::class, 'palmares']);
            Route::get('/improving-students', [RankingController::class, 'improvingStudents']);
            Route::get('/export', [RankingController::class, 'export']);
        });

        // Student Ranking Position
        Route::get('/students/{student}/semesters/{semester}/position', [RankingController::class, 'studentPosition']);
        Route::get('/students/{student}/ranking-evolution', [RankingController::class, 'studentEvolution']);

        // PVs Délibération (Story 23)
        Route::prefix('deliberation-sessions/{session}')->group(function () {
            Route::post('/generate-pv', [ProcesVerbalController::class, 'generate']);
            Route::post('/regenerate-pv', [ProcesVerbalController::class, 'regenerate']);
            Route::get('/pv-preview', [ProcesVerbalController::class, 'preview']);
        });

        Route::prefix('pv')->group(function () {
            Route::get('/search', [ProcesVerbalController::class, 'search']);
            Route::get('/{pvLog}', [ProcesVerbalController::class, 'show']);
            Route::get('/{pvLog}/download', [ProcesVerbalController::class, 'download']);
            Route::delete('/{pvLog}', [ProcesVerbalController::class, 'destroy']);
        });

        Route::get('/semesters/{semester}/pv-history', [ProcesVerbalController::class, 'semesterHistory']);
        Route::get('/academic-years/{academicYear}/summary-report', [ProcesVerbalController::class, 'summaryReport']);

        // Performance Analytics (Story 24)
        Route::prefix('analytics')->group(function () {
            // Semester Analytics
            Route::prefix('semesters/{semester}')->group(function () {
                Route::get('/kpis', [AnalyticsController::class, 'kpis']);
                Route::get('/weak-modules', [AnalyticsController::class, 'weakModules']);
                Route::get('/cohort-analysis', [AnalyticsController::class, 'cohortAnalysis']);
                Route::get('/at-risk-students', [AnalyticsController::class, 'atRiskStudents']);
                Route::get('/correlation-matrix', [AnalyticsController::class, 'correlationMatrix']);
                Route::get('/dashboard', [AnalyticsController::class, 'dashboard']);
            });

            // Academic Year Analytics
            Route::get('/academic-years/{academicYear}/historical-comparison', [AnalyticsController::class, 'historicalComparison']);
        });
    });
