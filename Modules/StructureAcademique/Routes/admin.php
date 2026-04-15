<?php

use Illuminate\Support\Facades\Route;
use Modules\StructureAcademique\Http\Controllers\Admin\AcademicYearController;
use Modules\StructureAcademique\Http\Controllers\Admin\CycleController;
use Modules\StructureAcademique\Http\Controllers\Admin\LevelController;
use Modules\StructureAcademique\Http\Controllers\Admin\SemesterController;
use Modules\StructureAcademique\Http\Controllers\Admin\ClasseController;
use Modules\StructureAcademique\Http\Controllers\Admin\SeriesController;
use Modules\StructureAcademique\Http\Controllers\Admin\SubjectClassCoefficientController;
use Modules\StructureAcademique\Http\Controllers\Admin\SubjectController;
use Modules\StructureAcademique\Http\Controllers\Admin\AcademicPeriodController;
use Modules\StructureAcademique\Http\Controllers\Admin\EvaluationPeriodController;

/*
|--------------------------------------------------------------------------
| Admin Routes - Structure Académique
|--------------------------------------------------------------------------
| Routes pour les administrateurs du TENANT (collèges/lycées)
*/

Route::prefix('admin')
    ->middleware(['tenant', 'tenant.auth'])
    ->group(function () {
        // Années Scolaires CRUD
        Route::prefix('academic-years')->name('academic-years.')->group(function () {
            Route::get('/active', [AcademicYearController::class, 'active'])->name('active');
            Route::get('/', [AcademicYearController::class, 'index'])->name('index');
            Route::post('/', [AcademicYearController::class, 'store'])->name('store');
            Route::get('/{academicYear}', [AcademicYearController::class, 'show'])->name('show');
            Route::put('/{academicYear}', [AcademicYearController::class, 'update'])->name('update');
            Route::delete('/{academicYear}', [AcademicYearController::class, 'destroy'])->name('destroy');
            Route::post('/{academicYear}/activate', [AcademicYearController::class, 'activate'])->name('activate');
        });

        // Semestres
        Route::prefix('semesters')->name('semesters.')->group(function () {
            Route::get('/current', [SemesterController::class, 'current'])->name('current');
            Route::get('/', [SemesterController::class, 'index'])->name('index');
            Route::post('/', [SemesterController::class, 'store'])->name('store');
            Route::get('/{semester}', [SemesterController::class, 'show'])->name('show');
            Route::put('/{semester}', [SemesterController::class, 'update'])->name('update');
            Route::delete('/{semester}', [SemesterController::class, 'destroy'])->name('destroy');
            Route::post('/{semester}/close', [SemesterController::class, 'close'])->name('close');

            // Périodes d'évaluation par semestre
            Route::prefix('{semester}/evaluation-periods')->name('evaluation-periods.')->group(function () {
                Route::get('/', [EvaluationPeriodController::class, 'index'])->name('index');
                Route::post('/', [EvaluationPeriodController::class, 'store'])->name('store');
                Route::put('/{evaluationPeriod}', [EvaluationPeriodController::class, 'update'])->name('update');
                Route::delete('/{evaluationPeriod}', [EvaluationPeriodController::class, 'destroy'])->name('destroy');
            });
        });

        // Périodes académiques
        Route::prefix('academic-periods')->name('academic-periods.')->group(function () {
            Route::get('/', [AcademicPeriodController::class, 'index'])->name('index');
            Route::post('/', [AcademicPeriodController::class, 'store'])->name('store');
            Route::put('/{academicPeriod}', [AcademicPeriodController::class, 'update'])->name('update');
            Route::delete('/{academicPeriod}', [AcademicPeriodController::class, 'destroy'])->name('destroy');
        });

        // Cycles (pas de store/destroy - seeder uniquement)
        Route::prefix('cycles')->name('cycles.')->group(function () {
            Route::get('/', [CycleController::class, 'index'])->name('index');
            Route::get('/{cycle}', [CycleController::class, 'show'])->name('show');
            Route::put('/{cycle}', [CycleController::class, 'update'])->name('update');
        });

        // Niveaux (lecture seule)
        Route::prefix('levels')->name('levels.')->group(function () {
            Route::get('/', [LevelController::class, 'index'])->name('index');
            Route::get('/{level}', [LevelController::class, 'show'])->name('show');
        });

        // Séries (CRUD complet, destroy retourne 403)
        Route::prefix('series')->name('series.')->group(function () {
            Route::get('/', [SeriesController::class, 'index'])->name('index');
            Route::post('/', [SeriesController::class, 'store'])->name('store');
            Route::get('/{series}', [SeriesController::class, 'show'])->name('show');
            Route::put('/{series}', [SeriesController::class, 'update'])->name('update');
            Route::delete('/{series}', [SeriesController::class, 'destroy'])->name('destroy');
        });

        // Matières (CRUD complet)
        Route::prefix('subjects')->name('subjects.')->group(function () {
            Route::get('/', [SubjectController::class, 'index'])->name('index');
            Route::post('/', [SubjectController::class, 'store'])->name('store');
            Route::get('/{subject}', [SubjectController::class, 'show'])->name('show');
            Route::put('/{subject}', [SubjectController::class, 'update'])->name('update');
            Route::delete('/{subject}', [SubjectController::class, 'destroy'])->name('destroy');
        });

        // Coefficients matière/niveau/série
        Route::prefix('subject-class-coefficients')->name('subject-class-coefficients.')->group(function () {
            Route::get('/compare', [SubjectClassCoefficientController::class, 'compare'])->name('compare');
            Route::get('/compare/export', [SubjectClassCoefficientController::class, 'compareExport'])->name('compare.export');
            Route::post('/duplicate', [SubjectClassCoefficientController::class, 'duplicate'])->name('duplicate');
            Route::get('/', [SubjectClassCoefficientController::class, 'index'])->name('index');
            Route::post('/', [SubjectClassCoefficientController::class, 'store'])->name('store');
            Route::get('/{coefficient}', [SubjectClassCoefficientController::class, 'show'])->name('show');
            Route::put('/{coefficient}', [SubjectClassCoefficientController::class, 'update'])->name('update');
            Route::delete('/{coefficient}', [SubjectClassCoefficientController::class, 'destroy'])->name('destroy');
        });

        // Classes (CRUD complet + endpoints spéciaux)
        Route::prefix('classes')->name('classes.')->group(function () {
            Route::get('/stats', [ClasseController::class, 'stats'])->name('stats');
            Route::get('/available-head-teachers', [ClasseController::class, 'availableHeadTeachers'])->name('available-head-teachers');
            Route::get('/without-head-teacher', [ClasseController::class, 'withoutHeadTeacher'])->name('without-head-teacher');
            Route::get('/', [ClasseController::class, 'index'])->name('index');
            Route::post('/', [ClasseController::class, 'store'])->name('store');
            Route::get('/{classe}', [ClasseController::class, 'show'])->name('show');
            Route::put('/{classe}', [ClasseController::class, 'update'])->name('update');
            Route::delete('/{classe}', [ClasseController::class, 'destroy'])->name('destroy');
        });
    });
