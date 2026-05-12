<?php

use Illuminate\Support\Facades\Route;
use Modules\Payroll\Http\Controllers\Admin\EmployeeController;
use Modules\Payroll\Http\Controllers\Admin\PayrollComponentController;
use Modules\Payroll\Http\Controllers\Admin\PayrollController;
use Modules\Payroll\Http\Controllers\Admin\PayrollReportController;
use Modules\Payroll\Http\Controllers\Admin\SocialDeclarationController;

// RBAC durcissement (Story Admin 12) : seuls Administrator et Manager accèdent
// à la paie. Comptable a accès en lecture seule (cf. Story Comptable 06 — restriction
// fine à appliquer story par story). Caissier/Agent Comptable/Professeur exclus.
Route::prefix('admin/payroll')->middleware(['tenant', 'tenant.auth', 'role:Administrator|Manager|Comptable,tenant'])->group(function () {
    // Epic 1: Employee Management (Stories 01-04)
    Route::apiResource('employees', EmployeeController::class);
    Route::post('employees/{employeeId}/contracts', [EmployeeController::class, 'createContract']);
    Route::post('employees/{employeeId}/contracts/{contractId}/activate', [EmployeeController::class, 'activateContract']);
    Route::post('employees/{employeeId}/contracts/{contractId}/amendments', [EmployeeController::class, 'createAmendment']);
    Route::post('employees/{employeeId}/contracts/{contractId}/amendments/{amendmentId}/approve', [EmployeeController::class, 'approveAmendment']);
    Route::post('employees/{employeeId}/contracts/{contractId}/terminate', [EmployeeController::class, 'terminateContract']);

    // Epic 2: Payroll Components (Stories 05-09)
    Route::get('salary-scales', [PayrollComponentController::class, 'indexSalaryScales']);
    Route::post('salary-scales', [PayrollComponentController::class, 'storeSalaryScale']);

    Route::get('components', [PayrollComponentController::class, 'indexComponents']);
    Route::post('components', [PayrollComponentController::class, 'storeComponent']);

    Route::get('advances', [PayrollComponentController::class, 'indexAdvances']);
    Route::post('advances', [PayrollComponentController::class, 'requestAdvance']);
    Route::post('advances/{id}/approve', [PayrollComponentController::class, 'approveAdvance']);
    Route::post('advances/{id}/disburse', [PayrollComponentController::class, 'disburseAdvance']);

    // Epic 3: Payroll Processing (Stories 10-13)
    Route::apiResource('payroll-periods', PayrollController::class);
    Route::post('payroll-periods/{id}/calculate', [PayrollController::class, 'calculate']);
    Route::post('payroll-periods/{id}/validate', [PayrollController::class, 'validate']);
    Route::post('payroll-periods/{id}/generate-payslips', [PayrollController::class, 'generatePayslips']);
    Route::get('payroll-periods/{id}/bank-transfers', [PayrollController::class, 'generateBankTransfers']);
    Route::post('payroll-periods/{id}/mark-as-paid', [PayrollController::class, 'markAsPaid']);

    // Epic 4: Social Declarations (Stories 14-17)
    Route::apiResource('declarations', SocialDeclarationController::class)->only(['index', 'show']);
    Route::post('payroll-periods/{periodId}/declarations/cnss', [SocialDeclarationController::class, 'generateCNSS']);
    Route::post('payroll-periods/{periodId}/declarations/income-tax', [SocialDeclarationController::class, 'generateIncomeTax']);
    Route::post('payroll-periods/{periodId}/declarations/amo', [SocialDeclarationController::class, 'generateAMO']);
    Route::post('declarations/annual-summary', [SocialDeclarationController::class, 'generateAnnualSummary']);
    Route::post('declarations/{id}/validate', [SocialDeclarationController::class, 'validateDeclaration']);
    Route::post('declarations/{id}/submit', [SocialDeclarationController::class, 'submit']);
    Route::post('declarations/{id}/payment', [SocialDeclarationController::class, 'recordPayment']);

    // Epic 5: HR Reports (Stories 18-20)
    Route::get('reports/dashboard', [PayrollReportController::class, 'dashboard']);
    Route::get('reports/payroll-journal/{periodId}', [PayrollReportController::class, 'payrollJournal']);
    Route::get('reports/social-charges/{periodId}', [PayrollReportController::class, 'socialCharges']);
    Route::get('reports/salary-statistics', [PayrollReportController::class, 'salaryStatistics']);
});
