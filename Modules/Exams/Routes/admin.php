<?php

use Illuminate\Support\Facades\Route;

// RBAC durcissement (Stories Professeur 08, Admin 09) :
// Admin et Professeur (pour surveillance d'examens) accèdent.
// Planification, gestion des épreuves et affectation des surveillants : Administrator|Manager uniquement.
// Professeur : lecture + surveillance (présence, remises, incidents).
Route::prefix('admin/exams')->middleware(['tenant', 'tenant.auth', 'role:Administrator|Manager|Professeur,tenant'])->group(function () {

    // Epic 1: Exam Planning (Stories 01-04)
    Route::prefix('sessions')->group(function () {
        Route::get('/', 'ExamSessionController@index');
        Route::get('/available-rooms', 'ExamSessionController@availableRooms');
        Route::post('/validate-schedule', 'ExamSessionController@validateSchedule');
        Route::get('/{session}', 'ExamSessionController@show')->whereNumber('session');

        Route::middleware('role:Administrator|Manager,tenant')->group(function () {
            Route::post('/', 'ExamSessionController@store');
            Route::put('/{session}', 'ExamSessionController@update')->whereNumber('session');
            Route::delete('/{session}', 'ExamSessionController@destroy')->whereNumber('session');
            Route::post('/{session}/publish', 'ExamSessionController@publish')->whereNumber('session');
            Route::post('/{session}/cancel', 'ExamSessionController@cancel')->whereNumber('session');
            Route::post('/{session}/duplicate', 'ExamSessionController@duplicate')->whereNumber('session');

            // Room assignments
            Route::post('/{session}/rooms', 'ExamRoomController@assign')->whereNumber('session');
            Route::delete('/{session}/rooms/{assignment}', 'ExamRoomController@remove')->whereNumber(['session', 'assignment']);
        });
    });

    // Epic 2: Exam Management (Stories 05-07)
    Route::prefix('management')->group(function () {
        Route::get('/sessions/{session}/eligible-students', 'ExamManagementController@eligibleStudents');
        Route::get('/sessions/{session}/preparation-checklist', 'ExamManagementController@preparationChecklist');

        Route::middleware('role:Administrator|Manager,tenant')->group(function () {
            Route::put('/sessions/{session}/materials', 'ExamManagementController@updateMaterials');
            Route::put('/sessions/{session}/instructions', 'ExamManagementController@updateInstructions');
            Route::post('/sessions/{session}/students/assign', 'ExamManagementController@assignStudents');
            Route::post('/sessions/{session}/students/auto-assign', 'ExamManagementController@autoAssign');
            Route::put('/attendance-sheets/{sheet}/reassign', 'ExamManagementController@reassignStudent');
            Route::delete('/attendance-sheets/{sheet}', 'ExamManagementController@removeStudent');
        });
    });

    // Epic 3: Exam Supervision (Stories 08-10)
    Route::prefix('supervision')->group(function () {
        // Supervisors
        Route::middleware('role:Administrator|Manager,tenant')->group(function () {
            Route::post('/sessions/{session}/supervisors', 'ExamSupervisionController@assignSupervisors');
            Route::post('/supervisors/{supervisor}/replace', 'ExamSupervisionController@replaceSupervisor');
        });
        Route::put('/supervisors/{supervisor}/present', 'ExamSupervisionController@markPresent');
        Route::get('/teachers/{teacher}/schedule', 'ExamSupervisionController@teacherSchedule');

        // Attendance tracking
        Route::put('/attendance-sheets/{sheet}/status', 'ExamSupervisionController@recordAttendance');
        Route::post('/attendance-sheets/{sheet}/submit', 'ExamSupervisionController@recordSubmission');
        Route::post('/attendance-sheets/{sheet}/verify', 'ExamSupervisionController@verifySheet');
        Route::get('/sessions/{session}/attendance-stats', 'ExamSupervisionController@attendanceStats');

        // Incidents
        Route::post('/incidents', 'ExamIncidentController@store');
        Route::put('/incidents/{incident}', 'ExamIncidentController@update');
        Route::put('/incidents/{incident}/status', 'ExamIncidentController@updateStatus');
        Route::post('/incidents/{incident}/evidence', 'ExamIncidentController@addEvidence');
        Route::post('/incidents/{incident}/escalate', 'ExamIncidentController@escalate');
        Route::get('/sessions/{session}/incidents', 'ExamIncidentController@sessionIncidents');
        Route::get('/sessions/{session}/incidents/summary', 'ExamIncidentController@summary');
    });

    // Epic 4: Reports (Stories 11-13)
    Route::prefix('reports')->group(function () {
        Route::get('/sessions/{session}/attendance', 'ExamReportController@attendanceReport');
        Route::get('/sessions/{session}/attendance/export', 'ExamReportController@exportAttendance');
        Route::get('/sessions/{session}/incidents', 'ExamReportController@incidentReport');
        Route::get('/sessions/{session}/incidents/export', 'ExamReportController@exportIncidents');
        Route::get('/statistics', 'ExamReportController@statistics');
        Route::get('/supervisor-workload', 'ExamReportController@supervisorWorkload');
        Route::get('/room-utilization', 'ExamReportController@roomUtilization');
    });
});
