<?php

use Illuminate\Support\Facades\Route;
use Modules\NotesEvaluations\Http\Controllers\Teacher\AbsenceManagementController;
use Modules\NotesEvaluations\Http\Controllers\Teacher\BatchGradeController;
use Modules\NotesEvaluations\Http\Controllers\Teacher\GradeCorrectionController;
use Modules\NotesEvaluations\Http\Controllers\Teacher\GradeEntryController;
use Modules\NotesEvaluations\Http\Controllers\Teacher\GradeImportController;
use Modules\NotesEvaluations\Http\Controllers\Teacher\GradeSubmissionController;
use Modules\NotesEvaluations\Http\Controllers\Teacher\RetakeGradeController;

/*
|--------------------------------------------------------------------------
| Teacher API Routes - Notes & Evaluations Module
|--------------------------------------------------------------------------
*/

// RBAC durcissement (Stories Professeur 01-09) : seuls les Professeurs (et Admin pour debug)
// accèdent à ces endpoints. Ownership (teacher_id = auth()->id()) reste à contrôler
// dans chaque controller — cf. DEV-AGENT-PROMPT §C.
Route::prefix('api/frontend/teacher')
    ->middleware(['tenant', 'tenant.auth', 'role:Professeur|Administrator,tenant'])
    ->group(function () {
        // Teacher's Modules
        Route::get('/my-modules', [GradeEntryController::class, 'myModules']);
        Route::get('/modules/{module}/evaluations', [GradeEntryController::class, 'moduleEvaluations']);

        // Grade Entry
        Route::prefix('evaluations/{evaluation}')->group(function () {
            Route::get('/students', [GradeEntryController::class, 'students']);
            Route::get('/statistics', [GradeEntryController::class, 'statistics']);
            Route::get('/export', [GradeEntryController::class, 'export']);
            Route::get('/export-history', [GradeCorrectionController::class, 'exportEvaluationHistory']);
            Route::get('/check-completeness', [GradeEntryController::class, 'checkCompleteness']);
            Route::post('/publish', [GradeEntryController::class, 'publish']);
        });

        // Module History Export
        Route::get('/modules/{module}/export-history', [GradeCorrectionController::class, 'exportModuleHistory']);

        // Grade CRUD
        Route::prefix('grades')->group(function () {
            Route::post('/batch', [GradeEntryController::class, 'storeBatch']);
            Route::post('/auto-save', [GradeEntryController::class, 'autoSave']);
            Route::get('/{grade}/history', [GradeCorrectionController::class, 'history']);
            Route::post('/{grade}/request-correction', [GradeCorrectionController::class, 'requestCorrection']);
        });

        // Batch Grade Entry (copy-paste)
        Route::prefix('evaluations/{evaluation}')->group(function () {
            Route::post('/batch-grades', [BatchGradeController::class, 'store']);
            Route::post('/validate-batch', [BatchGradeController::class, 'validateBatch']);
        });

        // Import/Export
        Route::prefix('grades/import')->group(function () {
            Route::get('/template', [GradeImportController::class, 'template']);
            Route::post('/validate', [GradeImportController::class, 'validateFile']);
            Route::post('/preview', [GradeImportController::class, 'preview']);
            Route::post('/execute', [GradeImportController::class, 'execute']);
            Route::get('/status/{jobId}', [GradeImportController::class, 'status']);
        });

        // Grade Submission for Validation
        Route::post('/grades/submit', [GradeSubmissionController::class, 'submit']);
        Route::get('/grades/submission-status', [GradeSubmissionController::class, 'status']);

        // Absence Management
        Route::prefix('evaluations/{evaluation}/absences')->group(function () {
            Route::get('/', [AbsenceManagementController::class, 'index']);
            Route::post('/mark-absent', [AbsenceManagementController::class, 'markAbsent']);
            Route::get('/policy', [AbsenceManagementController::class, 'policy']);
            Route::get('/statistics', [AbsenceManagementController::class, 'statistics']);
            Route::get('/replacements', [AbsenceManagementController::class, 'replacements']);
            Route::post('/schedule-replacement', [AbsenceManagementController::class, 'scheduleReplacement']);
        });

        // Replacement Evaluation Management
        Route::prefix('replacements')->group(function () {
            Route::post('/{replacement}/cancel', [AbsenceManagementController::class, 'cancelReplacement']);
            Route::post('/{replacement}/record-grade', [AbsenceManagementController::class, 'recordReplacementGrade']);
        });

        // Retake Grades (Story 18)
        Route::get('/retake-modules', [RetakeGradeController::class, 'myRetakeModules']);

        Route::prefix('modules/{module}')->group(function () {
            Route::get('/retake-students', [RetakeGradeController::class, 'retakeStudents']);
            Route::get('/retake-statistics', [RetakeGradeController::class, 'statistics']);
            Route::get('/retake-template', [RetakeGradeController::class, 'exportTemplate']);
            Route::post('/submit-retake-grades', [RetakeGradeController::class, 'submit']);
        });

        Route::prefix('retake-grades')->group(function () {
            Route::post('/', [RetakeGradeController::class, 'store']);
            Route::post('/batch', [RetakeGradeController::class, 'storeBatch']);
        });
    });
