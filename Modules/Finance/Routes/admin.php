<?php

use Illuminate\Support\Facades\Route;
use Modules\Finance\Http\Controllers\Admin\BankReconciliationController;
use Modules\Finance\Http\Controllers\Admin\CashierCloseController;
use Modules\Finance\Http\Controllers\Admin\CollectionController;
use Modules\Finance\Http\Controllers\Admin\FinanceReportController;
use Modules\Finance\Http\Controllers\Admin\InvoiceController;
use Modules\Finance\Http\Controllers\Admin\PaymentController;
use Modules\Finance\Http\Controllers\CinetPayWebhookController;

/*
 * Story Parent 06 — Webhook public CinetPay.
 *
 * Route NON authentifiée, hors du groupe tenant : CinetPay POST directement.
 * La sécurité est assurée par la vérification HMAC dans le controller.
 * Throttle pour protéger contre les flood.
 */
Route::post('/webhooks/cinetpay', CinetPayWebhookController::class)
    ->middleware(['throttle:120,1'])
    ->name('webhooks.cinetpay');

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

// RBAC durcissement transverse (Stories Caissier/Agent Comptable/Comptable) :
// limite l'accès aux rôles finance. Fine-grained restrictions (refund, write-off)
// à appliquer story par story — cf. DEV-AGENT-PROMPT §C / §D.4-D.6.
Route::prefix('admin/finance')
    ->middleware(['tenant', 'tenant.auth', 'role:Administrator|Manager|Comptable|Agent Comptable|Caissier,tenant'])
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

        // Invoices (Stories Agent Comptable 02, 03, 04 + Comptable read)
        Route::prefix('invoices')->group(function () {
            // Lectures : tous les rôles finance (Caissier inclus pour vérif au guichet)
            Route::get('/', [InvoiceController::class, 'index'])
                ->name('admin.finance.invoices.index');
            Route::get('/{id}', [InvoiceController::class, 'show'])
                ->name('admin.finance.invoices.show');

            // Calcul pénalités (Story Agent Comptable 04) — lecture
            Route::get('/{id}/late-fees', [InvoiceController::class, 'calculateLateFees'])
                ->name('admin.finance.invoices.calculate-late-fees');

            // Mutations : Caissier EXCLU (Stories Agent Comptable 02 / Comptable)
            Route::middleware('role:Administrator|Comptable|Agent Comptable,tenant')->group(function () {
                // Story Agent Comptable 01 : Generate automated invoice
                Route::post('/generate-automated', [InvoiceController::class, 'generateAutomated'])
                    ->name('admin.finance.invoices.generate-automated');

                // Story Agent Comptable 02 : Create custom invoice
                Route::post('/', [InvoiceController::class, 'store'])
                    ->name('admin.finance.invoices.store');

                Route::put('/{id}', [InvoiceController::class, 'update'])
                    ->name('admin.finance.invoices.update');
                Route::delete('/{id}', [InvoiceController::class, 'destroy'])
                    ->name('admin.finance.invoices.destroy');

                // Story Agent Comptable 03 : Échéancier
                Route::post('/{id}/payment-schedule', [InvoiceController::class, 'createPaymentSchedule'])
                    ->name('admin.finance.invoices.create-payment-schedule');
            });
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

            // Story 11: Process refund — RESTREINT (Caissier EXCLU)
            // Stories Caissier 02 / Comptable 04 : seuls Administrator et Comptable
            // peuvent rembourser. Agent Comptable et Caissier sont exclus.
            Route::post('/{id}/refund', [PaymentController::class, 'refund'])
                ->middleware('role:Administrator|Comptable,tenant')
                ->name('admin.finance.payments.refund');

            // Story 12: Bank reconciliation
            Route::get('/reconciliation/data', [PaymentController::class, 'reconciliation'])
                ->name('admin.finance.payments.reconciliation');

            // Daily summary
            Route::get('/summary/daily', [PaymentController::class, 'dailySummary'])
                ->name('admin.finance.payments.daily-summary');
        });

        // Story Caissier 05 — Clôture journalière de caisse
        Route::prefix('cashier-close')->group(function () {
            Route::get('/', [CashierCloseController::class, 'index'])
                ->name('admin.finance.cashier-close.index');
            Route::post('/', [CashierCloseController::class, 'store'])
                ->name('admin.finance.cashier-close.store');
        });

        // Story Comptable 03 — Rapprochement bancaire (Administrator + Comptable seulement)
        Route::prefix('bank-reconciliation')
            ->middleware('role:Administrator|Comptable,tenant')
            ->group(function () {
                Route::get('/accounts', [BankReconciliationController::class, 'listAccounts'])
                    ->name('admin.finance.bank.accounts');
                Route::post('/accounts', [BankReconciliationController::class, 'createAccount'])
                    ->name('admin.finance.bank.create-account');
                Route::get('/transactions', [BankReconciliationController::class, 'listTransactions'])
                    ->name('admin.finance.bank.transactions');
                Route::get('/periods', [BankReconciliationController::class, 'listPeriods'])
                    ->name('admin.finance.bank.periods');
            });

        // Discounts/Scholarships (Story 06) — Caissier EXCLU (Story Caissier 04 actions interdites)
        // Création/approval réservé Admin/Comptable/Agent Comptable.
        Route::prefix('discounts')
            ->middleware('role:Administrator|Comptable|Agent Comptable,tenant')
            ->group(function () {
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

        // Collections & Reminders (Stories Agent Comptable 05, 06 + Comptable)
        // Caissier EXCLU des mutations (recouvrement = Agent Comptable / Comptable / Admin).
        // GET /blocks/check : ouvert à tous les modules pour vérification cross-module
        // (cf. Story Agent Comptable 06 — Documents/Exams/Reenrollment consultent).
        Route::prefix('collection')->group(function () {
            // Lecture publique aux rôles finance (déjà filtrée par middleware parent)
            Route::get('/reminders', [CollectionController::class, 'reminders'])
                ->name('admin.finance.collection.reminders');
            Route::get('/blocks', [CollectionController::class, 'blocks'])
                ->name('admin.finance.collection.blocks');
            Route::get('/blocks/check', [CollectionController::class, 'checkBlocks'])
                ->name('admin.finance.collection.check-blocks');
            Route::get('/statistics', [CollectionController::class, 'statistics'])
                ->name('admin.finance.collection.statistics');

            // Mutations recouvrement : Caissier EXCLU
            Route::middleware('role:Administrator|Comptable|Agent Comptable,tenant')->group(function () {
                // Story Agent Comptable 05 : Reminders
                Route::post('/reminders/generate', [CollectionController::class, 'generateReminders'])
                    ->name('admin.finance.collection.generate-reminders');
                Route::post('/reminders/send', [CollectionController::class, 'sendReminders'])
                    ->name('admin.finance.collection.send-reminders');

                // Story Agent Comptable 06 : Service blocking
                Route::post('/blocks', [CollectionController::class, 'blockServices'])
                    ->name('admin.finance.collection.block-services');
                Route::post('/blocks/{id}/unblock', [CollectionController::class, 'unblockServices'])
                    ->name('admin.finance.collection.unblock-services');
                Route::post('/blocks/auto-process', [CollectionController::class, 'processAutomaticBlocking'])
                    ->name('admin.finance.collection.auto-block');

                // Story Agent Comptable 03 : Payment plans (échéanciers)
                Route::post('/payment-plans', [CollectionController::class, 'createPaymentPlan'])
                    ->name('admin.finance.collection.create-payment-plan');
            });

            // Write-off : Admin / Comptable seulement (Agent Comptable EXCLU)
            Route::post('/write-off/{id}', [CollectionController::class, 'writeOffDebt'])
                ->middleware('role:Administrator|Comptable,tenant')
                ->name('admin.finance.collection.write-off');
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
