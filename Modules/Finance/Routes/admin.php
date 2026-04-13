<?php

use Illuminate\Support\Facades\Route;
use Modules\Finance\Http\Controllers\Admin\CollectionController;
use Modules\Finance\Http\Controllers\Admin\FinanceReportController;
use Modules\Finance\Http\Controllers\Admin\InvoiceController;
use Modules\Finance\Http\Controllers\Admin\PaymentController;

/*
|--------------------------------------------------------------------------
| Admin API Routes - Finance Module
|--------------------------------------------------------------------------
|
| Routes for financial management (billing, payments, collections, reports)
| Covering 23 stories across 4 epics:
|
| Epic 1: Facturation (Stories 01-06) - InvoiceController
| Epic 2: Encaissement (Stories 07-12) - PaymentController
| Epic 3: Recouvrement (Stories 13-16) - CollectionController
| Epic 4: Rapports (Stories 17-23) - FinanceReportController
|
*/

Route::prefix('admin/finance')
    ->middleware(['tenant', 'tenant.auth'])
    ->group(function () {
        /*
        |--------------------------------------------------------------------------
        | Epic 1: Facturation (Stories 01-06)
        |--------------------------------------------------------------------------
        */

        // Fee Types Management (Story 02)
        Route::prefix('fee-types')->group(function () {
            Route::get('/', [InvoiceController::class, 'getFeeTypes'])
                ->name('admin.finance.fee-types.index');
            Route::post('/', [InvoiceController::class, 'storeFeeType'])
                ->name('admin.finance.fee-types.store');
            Route::put('/{id}', [InvoiceController::class, 'updateFeeType'])
                ->name('admin.finance.fee-types.update');
        });

        // Invoices (Stories 01, 03, 04, 05)
        Route::prefix('invoices')->group(function () {
            Route::get('/', [InvoiceController::class, 'index'])
                ->name('admin.finance.invoices.index');

            // Story 01: Generate automated invoice
            Route::post('/generate-automated', [InvoiceController::class, 'generateAutomated'])
                ->name('admin.finance.invoices.generate-automated');

            // Story 03: Create custom invoice
            Route::post('/', [InvoiceController::class, 'store'])
                ->name('admin.finance.invoices.store');

            Route::get('/{id}', [InvoiceController::class, 'show'])
                ->name('admin.finance.invoices.show');
            Route::put('/{id}', [InvoiceController::class, 'update'])
                ->name('admin.finance.invoices.update');
            Route::delete('/{id}', [InvoiceController::class, 'destroy'])
                ->name('admin.finance.invoices.destroy');

            // Story 04: Create payment schedule
            Route::post('/{id}/payment-schedule', [InvoiceController::class, 'createPaymentSchedule'])
                ->name('admin.finance.invoices.create-payment-schedule');

            // Story 05: Calculate late fees
            Route::get('/{id}/late-fees', [InvoiceController::class, 'calculateLateFees'])
                ->name('admin.finance.invoices.calculate-late-fees');
        });

        /*
        |--------------------------------------------------------------------------
        | Epic 2: Encaissement (Stories 07-12)
        |--------------------------------------------------------------------------
        */

        // Payments (Stories 07, 08, 09, 10, 11, 12)
        Route::prefix('payments')->group(function () {
            Route::get('/', [PaymentController::class, 'index'])
                ->name('admin.finance.payments.index');

            // Story 07 & 08: Record payment
            Route::post('/', [PaymentController::class, 'store'])
                ->name('admin.finance.payments.store');

            // Story 10: Record partial payment
            Route::post('/partial', [PaymentController::class, 'recordPartial'])
                ->name('admin.finance.payments.partial');

            Route::get('/{id}', [PaymentController::class, 'show'])
                ->name('admin.finance.payments.show');

            // Story 09: Get receipt for PDF
            Route::get('/{id}/receipt', [PaymentController::class, 'getReceipt'])
                ->name('admin.finance.payments.receipt');

            // Story 11: Process refund
            Route::post('/{id}/refund', [PaymentController::class, 'refund'])
                ->name('admin.finance.payments.refund');

            // Story 12: Bank reconciliation
            Route::get('/reconciliation/data', [PaymentController::class, 'reconciliation'])
                ->name('admin.finance.payments.reconciliation');

            // Daily summary
            Route::get('/summary/daily', [PaymentController::class, 'dailySummary'])
                ->name('admin.finance.payments.daily-summary');
        });

        // Discounts/Scholarships (Story 06)
        Route::prefix('discounts')->group(function () {
            Route::get('/', [PaymentController::class, 'discounts'])
                ->name('admin.finance.discounts.index');
            Route::post('/', [PaymentController::class, 'applyDiscount'])
                ->name('admin.finance.discounts.apply');
            Route::post('/{id}/approve', [PaymentController::class, 'approveDiscount'])
                ->name('admin.finance.discounts.approve');
            Route::delete('/{id}', [PaymentController::class, 'revokeDiscount'])
                ->name('admin.finance.discounts.revoke');
        });

        /*
        |--------------------------------------------------------------------------
        | Epic 3: Recouvrement (Stories 13-16)
        |--------------------------------------------------------------------------
        */

        // Collections & Reminders
        Route::prefix('collection')->group(function () {
            // Story 13: Automatic reminders
            Route::post('/reminders/generate', [CollectionController::class, 'generateReminders'])
                ->name('admin.finance.collection.generate-reminders');
            Route::post('/reminders/send', [CollectionController::class, 'sendReminders'])
                ->name('admin.finance.collection.send-reminders');
            Route::get('/reminders', [CollectionController::class, 'reminders'])
                ->name('admin.finance.collection.reminders');

            // Story 14: Service blocking
            Route::post('/blocks', [CollectionController::class, 'blockServices'])
                ->name('admin.finance.collection.block-services');
            Route::post('/blocks/{id}/unblock', [CollectionController::class, 'unblockServices'])
                ->name('admin.finance.collection.unblock-services');
            Route::get('/blocks', [CollectionController::class, 'blocks'])
                ->name('admin.finance.collection.blocks');
            Route::get('/blocks/check', [CollectionController::class, 'checkBlocks'])
                ->name('admin.finance.collection.check-blocks');
            Route::post('/blocks/auto-process', [CollectionController::class, 'processAutomaticBlocking'])
                ->name('admin.finance.collection.auto-block');

            // Story 15: Payment plans
            Route::post('/payment-plans', [CollectionController::class, 'createPaymentPlan'])
                ->name('admin.finance.collection.create-payment-plan');

            // Story 16: Write off bad debt
            Route::post('/write-off/{id}', [CollectionController::class, 'writeOffDebt'])
                ->name('admin.finance.collection.write-off');

            // Collection statistics
            Route::get('/statistics', [CollectionController::class, 'statistics'])
                ->name('admin.finance.collection.statistics');
        });

        /*
        |--------------------------------------------------------------------------
        | Epic 4: Rapports (Stories 17-23)
        |--------------------------------------------------------------------------
        */

        // Finance Reports
        Route::prefix('reports')->group(function () {
            // Story 17: Treasury dashboard
            Route::get('/dashboard', [FinanceReportController::class, 'dashboard'])
                ->name('admin.finance.reports.dashboard');

            // Story 18: Payment journal
            Route::get('/payment-journal', [FinanceReportController::class, 'paymentJournal'])
                ->name('admin.finance.reports.payment-journal');

            // Story 19: Aging balance
            Route::get('/aging-balance', [FinanceReportController::class, 'agingBalance'])
                ->name('admin.finance.reports.aging-balance');

            // Story 20: Unpaid statements
            Route::get('/unpaid-statements', [FinanceReportController::class, 'unpaidStatements'])
                ->name('admin.finance.reports.unpaid-statements');

            // Story 21: Cash flow forecast
            Route::get('/cash-flow-forecast', [FinanceReportController::class, 'cashFlowForecast'])
                ->name('admin.finance.reports.cash-flow-forecast');

            // Story 22: Collection statistics
            Route::get('/collection-statistics', [FinanceReportController::class, 'collectionStatistics'])
                ->name('admin.finance.reports.collection-statistics');

            // Story 23: Accounting export
            Route::get('/accounting-export', [FinanceReportController::class, 'accountingExport'])
                ->name('admin.finance.reports.accounting-export');

            // Report exports
            Route::get('/export/excel', [FinanceReportController::class, 'exportExcel'])
                ->name('admin.finance.reports.export-excel');
            Route::get('/export/pdf', [FinanceReportController::class, 'exportPdf'])
                ->name('admin.finance.reports.export-pdf');

            // Report summary
            Route::get('/summary', [FinanceReportController::class, 'summary'])
                ->name('admin.finance.reports.summary');

            // Clear cache
            Route::post('/clear-cache', [FinanceReportController::class, 'clearCache'])
                ->name('admin.finance.reports.clear-cache');
        });
    });
