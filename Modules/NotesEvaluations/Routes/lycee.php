<?php

use Illuminate\Support\Facades\Route;
use Modules\NotesEvaluations\Http\Controllers\Lycee\ClassEvaluationController;
use Modules\NotesEvaluations\Http\Controllers\Lycee\LyceeResultController;
use Modules\NotesEvaluations\Http\Controllers\Lycee\ReportCardController;
use Modules\NotesEvaluations\Http\Controllers\Lycee\TeacherAssignmentController;

/*
|--------------------------------------------------------------------------
| Notes lycée — /api/admin/lycee-grades
|--------------------------------------------------------------------------
| Rôles filtrés par middleware ; le périmètre fin (affectations du professeur,
| professeur principal, élève lui-même, parent de l'élève) est contrôlé par
| LyceeGradeAccessService dans les contrôleurs.
*/

Route::prefix('api/admin/lycee-grades')
    ->middleware(['tenant', 'tenant.auth'])
    ->group(function () {
        // Administration : affectations
        Route::middleware('role:Administrator|Manager,tenant')->group(function () {
            Route::get('/assignments', [TeacherAssignmentController::class, 'index']);
            Route::post('/assignments', [TeacherAssignmentController::class, 'store']);
            Route::post('/assignments/bulk', [TeacherAssignmentController::class, 'bulk']);
            Route::put('/assignments/{id}', [TeacherAssignmentController::class, 'update'])->whereNumber('id');
            Route::delete('/assignments/{id}', [TeacherAssignmentController::class, 'destroy'])->whereNumber('id');
        });

        // Publication des notes : réservée à l'Administrator
        Route::middleware('role:Administrator,tenant')->group(function () {
            Route::post('/evaluations/publish-bulk', [ClassEvaluationController::class, 'publishBulk']);
            Route::post('/evaluations/{id}/publish', [ClassEvaluationController::class, 'publish'])->whereNumber('id');
            Route::post('/evaluations/{id}/unpublish', [ClassEvaluationController::class, 'unpublish'])->whereNumber('id');
        });

        // Équipe pédagogique : professeurs (sur leurs affectations) + Admin/Manager
        Route::middleware('role:Administrator|Manager|Professeur,tenant')->group(function () {
            Route::get('/assignments/mine', [TeacherAssignmentController::class, 'mine']);
            Route::get('/classes/{classe}/subjects', [TeacherAssignmentController::class, 'classSubjects'])->whereNumber('classe');

            Route::get('/evaluations', [ClassEvaluationController::class, 'index']);
            Route::post('/evaluations', [ClassEvaluationController::class, 'store']);
            Route::get('/evaluations/{id}', [ClassEvaluationController::class, 'show'])->whereNumber('id');
            Route::put('/evaluations/{id}', [ClassEvaluationController::class, 'update'])->whereNumber('id');
            Route::delete('/evaluations/{id}', [ClassEvaluationController::class, 'destroy'])->whereNumber('id');
            Route::put('/evaluations/{id}/grades', [ClassEvaluationController::class, 'saveGrades'])->whereNumber('id');
            Route::post('/evaluations/{id}/submit', [ClassEvaluationController::class, 'submit'])->whereNumber('id');
            Route::post('/evaluations/{id}/reopen', [ClassEvaluationController::class, 'reopen'])->whereNumber('id');

            Route::get('/results/classes/{classe}', [LyceeResultController::class, 'classResults'])->whereNumber('classe');
            Route::get('/report-cards/classes/{classe}/pdf', [ReportCardController::class, 'classPdf'])->whereNumber('classe');
            Route::put('/report-cards/comments', [ReportCardController::class, 'saveComment']);
        });

        // Relevé et bulletin d'un élève : équipe + l'élève lui-même + ses parents
        Route::middleware('role:Administrator|Manager|Professeur|Élève|Parent,tenant')->group(function () {
            Route::get('/results/students/{student}', [LyceeResultController::class, 'studentResults'])->whereNumber('student');
            Route::get('/report-cards/students/{student}/pdf', [ReportCardController::class, 'studentPdf'])->whereNumber('student');
        });
    });
