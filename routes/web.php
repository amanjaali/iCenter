<?php

use App\Http\Controllers\Accounting\AccountController;
use App\Http\Controllers\Accounting\JournalController;
use App\Http\Controllers\Accounting\PeriodController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Assets\FixedAssetController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Billing\AcademicYearController;
use App\Http\Controllers\Billing\InvoiceController;
use App\Http\Controllers\Billing\PaymentController;
use App\Http\Controllers\Billing\RateCardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Expenses\ExpenseController;
use App\Http\Controllers\Expenses\SupplierController;
use App\Http\Controllers\Partners\DistributionController;
use App\Http\Controllers\Partners\PartnerController;
use App\Http\Controllers\Partners\RevenueShareController;
use App\Http\Controllers\Payroll\EmployeeController;
use App\Http\Controllers\Payroll\PayrollController;
use App\Http\Controllers\Registry\BusCompanyController;
use App\Http\Controllers\Registry\BusController;
use App\Http\Controllers\Registry\StudentController;
use App\Http\Controllers\Reports\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ALLVA Accounting
|--------------------------------------------------------------------------
|
| Every route below the auth middleware is additionally gated by ability
| inside its controller (scope of work §9) — the middleware only establishes
| who is asking, never what they may do.
|
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::redirect('/', '/dashboard');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // -- Registry (§3.1) ----------------------------------------------------
    Route::resource('bus-companies', BusCompanyController::class);
    Route::resource('buses', BusController::class);
    Route::resource('students', StudentController::class);
    Route::post('students/{student}/withdraw', [StudentController::class, 'withdraw'])->name('students.withdraw');
    Route::post('students/{student}/reinstate', [StudentController::class, 'reinstate'])->name('students.reinstate');

    // -- Billing (§4) -------------------------------------------------------
    Route::get('invoices/generate', [InvoiceController::class, 'generate'])->name('invoices.generate');
    Route::post('invoices/generate', [InvoiceController::class, 'runGeneration'])->name('invoices.run-generation');
    Route::post('invoices/issue-all', [InvoiceController::class, 'issueAll'])->name('invoices.issue-all');
    Route::post('invoices/recognise-revenue', [InvoiceController::class, 'recogniseRevenue'])->name('invoices.recognise');
    Route::get('invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
    Route::post('invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->name('invoices.issue');
    Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
    Route::post('invoices/{invoice}/write-off', [PaymentController::class, 'writeOff'])->name('invoices.write-off');
    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');

    Route::get('payments/suggest', [PaymentController::class, 'suggest'])->name('payments.suggest');
    Route::get('payments/{payment}/print', [PaymentController::class, 'print'])->name('payments.print');
    Route::resource('payments', PaymentController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('payments/{payment}/allocate', [PaymentController::class, 'allocate'])->name('payments.allocate');
    Route::post('payments/{payment}/void', [PaymentController::class, 'void'])->name('payments.void');

    // -- Rates (§4.2, §10.3) ------------------------------------------------
    Route::resource('rates', RateCardController::class)->only(['index', 'create', 'store', 'edit', 'update'])
        ->parameters(['rates' => 'rate']);
    Route::post('rates/{rate}/deactivate', [RateCardController::class, 'deactivate'])->name('rates.deactivate');
    Route::resource('academic-years', AcademicYearController::class)->only(['index', 'create', 'store', 'edit', 'update']);

    // -- Ledger -------------------------------------------------------------
    Route::resource('journals', JournalController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
    Route::post('journals/{journal}/post', [JournalController::class, 'post'])->name('journals.post');
    Route::post('journals/{journal}/reverse', [JournalController::class, 'reverse'])->name('journals.reverse');

    Route::resource('accounts', AccountController::class)->only(['index', 'show', 'create', 'store', 'edit', 'update']);

    Route::get('periods', [PeriodController::class, 'index'])->name('periods.index');
    Route::post('periods/fiscal-year', [PeriodController::class, 'createYear'])->name('periods.create-year');
    Route::post('periods/{period}/close', [PeriodController::class, 'close'])->name('periods.close');
    Route::post('periods/{period}/reopen', [PeriodController::class, 'reopen'])->name('periods.reopen');
    Route::post('periods/{period}/lock', [PeriodController::class, 'lock'])->name('periods.lock');

    // -- Costs (§6.5-§6.9, §7) ----------------------------------------------
    Route::resource('expenses', ExpenseController::class);
    Route::post('expenses/{expense}/post', [ExpenseController::class, 'post'])->name('expenses.post');
    Route::post('expenses/{expense}/void', [ExpenseController::class, 'void'])->name('expenses.void');
    Route::post('expenses/{expense}/pay', [ExpenseController::class, 'pay'])->name('expenses.pay');
    Route::post('expenses/{expense}/capitalise', [ExpenseController::class, 'capitalise'])->name('expenses.capitalise');
    Route::resource('suppliers', SupplierController::class)->only(['index', 'create', 'store', 'edit', 'update']);

    // -- Payroll (§6.6) -----------------------------------------------------
    Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::post('payroll/prepare', [PayrollController::class, 'create'])->name('payroll.prepare');
    Route::get('payroll/{payroll}', [PayrollController::class, 'show'])->name('payroll.show');
    Route::post('payroll/{payroll}/approve', [PayrollController::class, 'approve'])->name('payroll.approve');
    Route::post('payroll/{payroll}/post', [PayrollController::class, 'post'])->name('payroll.post');
    Route::post('payroll/{payroll}/pay', [PayrollController::class, 'pay'])->name('payroll.pay');
    Route::patch('payroll/lines/{line}', [PayrollController::class, 'updateLine'])->name('payroll.update-line');
    Route::get('payroll/lines/{line}/payslip', [PayrollController::class, 'payslip'])->name('payroll.payslip');
    Route::resource('employees', EmployeeController::class)->only(['index', 'create', 'store', 'edit', 'update']);

    // -- Fixed assets (§7) --------------------------------------------------
    Route::get('assets', [FixedAssetController::class, 'index'])->name('assets.index');
    Route::post('assets/depreciation', [FixedAssetController::class, 'prepareRun'])->name('assets.prepare-run');
    Route::get('assets/depreciation/{run}', [FixedAssetController::class, 'showRun'])->name('assets.run');
    Route::post('assets/depreciation/{run}/post', [FixedAssetController::class, 'postRun'])->name('assets.post-run');
    Route::get('assets/{asset}', [FixedAssetController::class, 'show'])->name('assets.show');
    Route::post('assets/{asset}/dispose', [FixedAssetController::class, 'dispose'])->name('assets.dispose');

    // -- Partners (§5) ------------------------------------------------------
    Route::resource('partners', PartnerController::class)->except(['destroy']);

    Route::get('revenue-share', [RevenueShareController::class, 'index'])->name('revenue-share.index');
    Route::post('revenue-share/prepare', [RevenueShareController::class, 'prepare'])->name('revenue-share.prepare');
    Route::get('revenue-share/{revenueShare}', [RevenueShareController::class, 'show'])->name('revenue-share.show');
    Route::post('revenue-share/{revenueShare}/post', [RevenueShareController::class, 'post'])->name('revenue-share.post');
    Route::post('revenue-share/{revenueShare}/pay', [RevenueShareController::class, 'pay'])->name('revenue-share.pay');

    Route::resource('distributions', DistributionController::class)
        ->only(['index', 'create', 'store', 'show', 'destroy']);
    Route::post('distributions/{distribution}/post', [DistributionController::class, 'post'])->name('distributions.post');
    Route::post('distributions/lines/{line}/payout', [DistributionController::class, 'payout'])->name('distributions.payout');

    // -- Reports (§8) -------------------------------------------------------
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');

        // §8.1 statutory
        Route::get('income-statement', [ReportController::class, 'incomeStatement'])->name('income-statement');
        Route::get('balance-sheet', [ReportController::class, 'balanceSheet'])->name('balance-sheet');
        Route::get('cash-flow', [ReportController::class, 'cashFlow'])->name('cash-flow');
        Route::get('trial-balance', [ReportController::class, 'trialBalance'])->name('trial-balance');
        Route::get('general-ledger', [ReportController::class, 'generalLedger'])->name('general-ledger');

        // §8.2 project and revenue
        Route::get('project-profitability', [ReportController::class, 'projectProfitability'])->name('project-profitability');
        Route::get('revenue-split', [ReportController::class, 'revenueSplit'])->name('revenue-split');
        Route::get('recurring-revenue', [ReportController::class, 'recurringRevenue'])->name('recurring-revenue');
        Route::get('active-students', [ReportController::class, 'activeStudents'])->name('active-students');
        Route::get('rate-history', [ReportController::class, 'rateHistory'])->name('rate-history');
        Route::get('billing-collection', [ReportController::class, 'billingCollection'])->name('billing-collection');
        Route::get('bus-company-statement/{busCompany}', [ReportController::class, 'busCompanyStatement'])->name('bus-company-statement');
        Route::get('academic-year-comparison', [ReportController::class, 'academicYearComparison'])->name('academic-year-comparison');

        // §8.3 partner and expense
        Route::get('partner-distribution', [ReportController::class, 'partnerDistribution'])->name('partner-distribution');
        Route::get('expenses', [ReportController::class, 'expenses'])->name('expenses');
        Route::get('payroll', [ReportController::class, 'payroll'])->name('payroll');
        Route::get('receivables-ageing', [ReportController::class, 'receivablesAgeing'])->name('receivables-ageing');
    });

    // -- Administration (§9) ------------------------------------------------
    Route::resource('users', UserController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    Route::post('users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');

    Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingController::class, 'update'])->name('settings.update');

    Route::get('audit', [AuditController::class, 'index'])->name('audit.index');
});
