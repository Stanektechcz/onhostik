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

    Route::get('/domeny', [Panel\DomainController::class, 'index'])->name('domains.index');
    Route::get('/domeny/{domain}', [Panel\DomainController::class, 'show'])->name('domains.show');

    Route::get('/objednavky', [Panel\OrderController::class, 'index'])->name('orders.index');
    Route::get('/objednavky/nova', [Panel\OrderController::class, 'create'])->name('orders.create');
    Route::get('/objednavky/{order}', [Panel\OrderController::class, 'show'])->name('orders.show');

    Route::get('/fakturace/faktury', [Panel\BillingController::class, 'invoices'])->name('billing.invoices');
    Route::get('/fakturace/faktury/{invoice}', [Panel\BillingController::class, 'invoiceShow'])->name('billing.invoices.show');
    Route::get('/fakturace/platby', [Panel\BillingController::class, 'payments'])->name('billing.payments');
    Route::get('/fakturace/kredit', [Panel\BillingController::class, 'credits'])->name('billing.credits');

    Route::get('/ucet/profil', [Panel\AccountController::class, 'profile'])->name('account.profile');
    Route::get('/ucet/fakturacni-udaje', [Panel\AccountController::class, 'billing'])->name('account.billing');
    Route::get('/ucet/zabezpeceni', [Panel\AccountController::class, 'security'])->name('account.security');

    Route::get('/podpora', [Panel\SupportController::class, 'index'])->name('support.index');
    Route::get('/ai', [Panel\AiController::class, 'index'])->name('ai.index');
});

/*
|--------------------------------------------------------------------------
| Admin (Cuba template, layouts.panel) — auth + access-admin gate
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'can:access-admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/zakaznici', [Admin\CustomerController::class, 'index'])->name('customers.index');
    Route::get('/objednavky', [Admin\OrderController::class, 'index'])->name('orders.index');
    Route::get('/faktury', [Admin\InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/platby', [Admin\PaymentController::class, 'index'])->name('payments.index');
    Route::get('/produkty', [Admin\ProductController::class, 'index'])->name('products.index');
    Route::get('/sluzby', [Admin\ServiceController::class, 'index'])->name('services.index');
    Route::get('/servery', [Admin\ServerController::class, 'index'])->name('servers.index');
    Route::get('/provisioning', [Admin\ProvisioningController::class, 'index'])->name('provisioning.index');
    Route::get('/audit', [Admin\AuditLogController::class, 'index'])->name('logs.audit');
    Route::get('/nastaveni', [Admin\SettingsController::class, 'index'])->name('settings.index');
});
