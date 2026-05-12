<?php

use Illuminate\Support\Facades\Route;

// RBAC durcissement (Stories Professeur 08, Admin 09) :
// Admin et Professeur (pour surveillance d'examens) accèdent.
// Mutations destructrices (delete session, cancel) restreintes côté Admin via story Admin 09.
Route::prefix('admin/exams')->middleware(['tenant', 'tenant.auth', 'role:Administrator|Manager|Professeur,tenant'])->group(function () {

    // Epic 1: Exam Planning (Stories 01-04)
    Route::prefix('sessions')->group(function () {
        Route::get('/', 'ExamSessionController@index');
        Route::post('/', 'ExamSessionController@store');
        Route::get('/{session}', 'ExamSessionController@show');
        Route::put('/{session}', 'ExamSessionController@update');
        Route::delete('/{session}', 'ExamSessionController@destroy');
        Route::post('/{session}/publish', 'ExamSessionController@publish');
        Route::post('/{session}/cancel', 'ExamSessionController@cancel');
        Route::post('/{session}/duplicate', 'ExamSessionController@duplicate');
        Route::post('/validate-schedule', 'ExamSessionController@validateSchedule');
        Route::get('/available-rooms', 'ExamSessionController@availableRooms');

        // Room assignments
        Route::post('/{session}/rooms', 'ExamRoomController@assign');
        Route::delete('/{session}/rooms/{assignment}', 'ExamRoomController@remove');
    });

    // Epic 2: Exam Management (Stories 05-07)
    Route::prefix('management')->group(function () {
        Route::put('/sessions/{session}/materials', 'ExamManagementController@updateMaterials');
        Route::put('/sessions/{session}/instructions', 'ExamManagementController@updateInstructions');
        Route::post('/sessions/{session}/students/assign', 'ExamManagementController@assignStudents');
        Route::post('/sessions/{session}/students/auto-assign', 'ExamManagementController@autoAssign');
        Route::put('/attendance-sheets/{sheet}/reassign', 'ExamManagementController@reassignStudent');
        Route::delete('/attendance-sheets/{sheet}', 'ExamManagementController@removeStudent');
        Route::get('/sessions/{session}/eligible-students', 'ExamManagementController@eligibleStudents');
        Route::get('/sessions/{session}/preparation-checklist', 'ExamManagementController@preparationChecklist');
    });

    // Epic 3: Exam Supervision (Stories 08-10)
    Route::prefix('supervision')->group(function () {
        // Supervisors
        Route::post('/sessions/{session}/supervisors', 'ExamSupervisionController@assignSupervisors');
        Route::put('/supervisors/{supervisor}/present', 'ExamSupervisionController@markPresent');
        Route::post('/supervisors/{supervisor}/replace', 'ExamSupervisionController@replaceSupervisor');
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
