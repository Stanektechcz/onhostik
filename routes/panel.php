<?php

declare(strict_types=1);

use App\Http\Controllers\Admin;
use App\Http\Controllers\Panel;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer portal (Cuba template, layouts.panel) — auth required
|--------------------------------------------------------------------------
| Route names match the references already used by the converted sidebar,
| header and front components.
*/

Route::middleware(['auth'])->prefix('panel')->name('panel.')->group(function (): void {
    Route::get('/', [Panel\DashboardController::class, 'index'])->name('dashboard');

    Route::get('/sluzby', [Panel\ServiceController::class, 'index'])->name('services.index');
    Route::get('/sluzby/{service}', [Panel\ServiceController::class, 'show'])->name('services.show');
    Route::post('/sluzby/{service}/zaloha', [Panel\ServiceController::class, 'requestBackup'])->name('services.backup');
    Route::post('/sluzby/{service}/wordpress', [Panel\ServiceController::class, 'installWordpress'])->name('services.wordpress');

    Route::get('/domeny', [Panel\DomainController::class, 'index'])->name('domains.index');
    Route::get('/domeny/{domain}', [Panel\DomainController::class, 'show'])->name('domains.show');

    Route::get('/objednavky', [Panel\OrderController::class, 'index'])->name('orders.index');
    Route::get('/objednavky/nova', [Panel\OrderController::class, 'create'])->name('orders.create');
    Route::post('/objednavky', [Panel\OrderController::class, 'store'])->name('orders.store');
    Route::get('/objednavky/{order}', [Panel\OrderController::class, 'show'])->name('orders.show');

    Route::get('/fakturace/faktury', [Panel\BillingController::class, 'invoices'])->name('billing.invoices');
    Route::get('/fakturace/faktury/{invoice}', [Panel\BillingController::class, 'invoiceShow'])->name('billing.invoices.show');
    Route::get('/fakturace/faktury/{invoice}/tisk', [Panel\BillingController::class, 'invoicePrint'])->name('billing.invoices.print');
    Route::post('/fakturace/faktury/{invoice}/zaplatit/mock', [Panel\BillingController::class, 'payMock'])->name('billing.invoices.pay-mock');
    Route::post('/fakturace/faktury/{invoice}/zaplatit/kredit', [Panel\BillingController::class, 'payCredit'])->name('billing.invoices.pay-credit');
    Route::get('/fakturace/platby', [Panel\BillingController::class, 'payments'])->name('billing.payments');
    Route::get('/fakturace/kredit', [Panel\BillingController::class, 'credits'])->name('billing.credits');
    Route::post('/fakturace/kredit/dobit', [Panel\BillingController::class, 'topUp'])->name('billing.credits.topup');

    Route::get('/ucet/profil', [Panel\AccountController::class, 'profile'])->name('account.profile');
    Route::get('/ucet/fakturacni-udaje', [Panel\AccountController::class, 'billing'])->name('account.billing');
    Route::put('/ucet/fakturacni-udaje', [Panel\AccountController::class, 'updateBilling'])->name('account.billing.update');
    Route::get('/ucet/zabezpeceni', [Panel\AccountController::class, 'security'])->name('account.security');

    Route::get('/podpora', [Panel\SupportController::class, 'index'])->name('support.index');
    Route::post('/podpora', [Panel\SupportController::class, 'store'])->name('support.store');
    Route::get('/podpora/{ticket}', [Panel\SupportController::class, 'show'])->name('support.show');
    Route::post('/podpora/{ticket}/odpoved', [Panel\SupportController::class, 'reply'])->name('support.reply');

    Route::get('/ai', [Panel\AiController::class, 'index'])->name('ai.index');
    Route::post('/ai', [Panel\AiController::class, 'run'])->name('ai.run');
});

/*
|--------------------------------------------------------------------------
| Admin (Cuba template, layouts.panel) — auth + access-admin gate
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'can:access-admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

    Route::get('/zakaznici', [Admin\CustomerController::class, 'index'])->name('customers.index');
    Route::get('/zakaznici/{customer}', [Admin\CustomerController::class, 'show'])->name('customers.show');
    Route::post('/zakaznici/{customer}/kredit', [Admin\CustomerController::class, 'adjustCredit'])->name('customers.credit');

    Route::get('/objednavky', [Admin\OrderController::class, 'index'])->name('orders.index');

    Route::get('/faktury', [Admin\InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/faktury/{invoice}', [Admin\InvoiceController::class, 'show'])->name('invoices.show');
    Route::post('/faktury/{invoice}/oznacit-zaplacenou', [Admin\InvoiceController::class, 'markPaid'])->name('invoices.mark-paid');
    Route::post('/faktury/{invoice}/danovy-doklad', [Admin\InvoiceController::class, 'issueTaxDocument'])->name('invoices.tax-document');

    Route::get('/platby', [Admin\PaymentController::class, 'index'])->name('payments.index');

    Route::get('/produkty', [Admin\ProductController::class, 'index'])->name('products.index');
    Route::put('/plany/{plan}', [Admin\ProductController::class, 'updatePlan'])->name('products.plans.update');

    Route::get('/sluzby', [Admin\ServiceController::class, 'index'])->name('services.index');
    Route::post('/sluzby/{service}/pozastavit', [Admin\ServiceController::class, 'suspend'])->name('services.suspend');
    Route::post('/sluzby/{service}/obnovit', [Admin\ServiceController::class, 'unsuspend'])->name('services.unsuspend');

    Route::get('/domeny', [Admin\DomainController::class, 'index'])->name('domains.index');

    Route::get('/servery', [Admin\ServerController::class, 'index'])->name('servers.index');
    Route::post('/servery/{server}/test', [Admin\ServerController::class, 'test'])->name('servers.test');

    Route::get('/provisioning', [Admin\ProvisioningController::class, 'index'])->name('provisioning.index');
    Route::post('/provisioning/{task}/retry', [Admin\ProvisioningController::class, 'retry'])->name('provisioning.retry');

    Route::get('/monitoring', [Admin\MonitoringController::class, 'index'])->name('monitoring.index');
    Route::get('/zalohy', [Admin\BackupController::class, 'index'])->name('backups.index');

    Route::get('/integrace', [Admin\IntegrationController::class, 'index'])->name('integrations.index');
    Route::get('/integrace/{integration}', [Admin\IntegrationController::class, 'edit'])->name('integrations.edit');
    Route::put('/integrace/{integration}', [Admin\IntegrationController::class, 'update'])->name('integrations.update');
    Route::post('/integrace/{integration}/test', [Admin\IntegrationController::class, 'test'])->name('integrations.test');

    Route::get('/podpora', [Admin\SupportController::class, 'index'])->name('support.index');
    Route::get('/podpora/{ticket}', [Admin\SupportController::class, 'show'])->name('support.show');
    Route::post('/podpora/{ticket}/odpoved', [Admin\SupportController::class, 'reply'])->name('support.reply');
    Route::put('/podpora/{ticket}', [Admin\SupportController::class, 'update'])->name('support.update');

    Route::get('/ai', [Admin\AiController::class, 'index'])->name('ai.index');
    Route::post('/ai', [Admin\AiController::class, 'run'])->name('ai.run');
    Route::post('/ai/schvaleni/{approval}', [Admin\AiController::class, 'review'])->name('ai.review');

    Route::get('/system', [Admin\SystemHealthController::class, 'index'])->name('system.index');

    Route::get('/audit', [Admin\AuditLogController::class, 'index'])->name('logs.audit');
    Route::get('/nastaveni', [Admin\SettingsController::class, 'index'])->name('settings.index');
    Route::post('/nastaveni', [Admin\SettingsController::class, 'update'])->name('settings.update');
});
