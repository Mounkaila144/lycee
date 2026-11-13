<?php

use Illuminate\Support\Facades\Route;
use Modules\StructureAcademique\Http\Controllers\Teacher\MyClassesController;

/*
|--------------------------------------------------------------------------
| Teacher Routes - Structure Académique (Espace Professeur « Mes classes »)
|--------------------------------------------------------------------------
| Même convention que NotesEvaluations/Routes/teacher.php : Professeur +
| Administrator (debug). Ownership vérifié dans le controller.
*/

Route::prefix('frontend/teacher')
    ->middleware(['tenant', 'tenant.auth', 'role:Professeur|Administrator,tenant'])
    ->group(function () {
        Route::get('/my-classes', [MyClassesController::class, 'index'])
            ->name('frontend.teacher.my-classes.index');
        Route::get('/my-classes/{classe}/students', [MyClassesController::class, 'students'])
            ->whereNumber('classe')
            ->name('frontend.teacher.my-classes.students');
    });
