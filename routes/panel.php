<?php

declare(strict_types=1);

use App\Http\Controllers\Admin;
use App\Http\Controllers\Panel;
use App\Http\Controllers\Partner;
use App\Http\Controllers\Reseller;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\BlogController as AdminBlogController;
use App\Http\Controllers\Admin\KbController as AdminKbController;
use App\Http\Controllers\Admin\SiteContentController;

/*
|--------------------------------------------------------------------------
| Customer portal (Cuba template, layouts.panel) — auth required
|--------------------------------------------------------------------------
| Route names match the references already used by the converted sidebar,
| header and front components.
*/

Route::middleware(['auth'])->prefix('panel')->name('panel.')->group(function (): void {
    Route::get('/', [Panel\DashboardController::class, 'index'])->name('dashboard');
    Route::post('/onboarding/dismiss', [Panel\OnboardingController::class, 'dismiss'])->name('onboarding.dismiss');

    Route::get('/sluzby', [Panel\ServiceController::class, 'index'])->name('services.index');
    Route::get('/sluzby/{service}', [Panel\ServiceController::class, 'show'])->name('services.show');
    Route::post('/sluzby/{service}/zaloha', [Panel\ServiceController::class, 'requestBackup'])->name('services.backup');
    Route::post('/sluzby/{service}/wordpress', [Panel\ServiceController::class, 'installWordpress'])->name('services.wordpress');
    Route::get('/sluzby/{service}/zmenit-plan', [Panel\ServiceController::class, 'changePlan'])->name('services.change-plan');
    Route::get('/sluzby/{service}/zmenit-plan/nahled', [Panel\ServiceController::class, 'changePlanPreview'])->name('services.change-plan-preview');
    Route::post('/sluzby/{service}/zmenit-plan', [Panel\ServiceController::class, 'applyChangePlan'])->name('services.apply-change-plan');
    Route::post('/sluzby/{service}/zrusit', [Panel\ServiceController::class, 'requestCancellation'])->name('services.request-cancel');
    Route::post('/sluzby/{service}/pozastavit', [Panel\ServiceController::class, 'pause'])->name('services.pause');
    Route::post('/sluzby/{service}/obnovit', [Panel\ServiceController::class, 'resume'])->name('services.resume');
    Route::post('/sluzby/{service}/zrusit-na-konci', [Panel\ServiceController::class, 'cancelAtPeriodEnd'])->name('services.cancel-at-period-end');
    Route::put('/sluzby/{service}/poznamka', [Panel\ServiceController::class, 'updateNote'])->name('services.update-note');
    Route::post('/sluzby/{service}/auto-obnova', [Panel\ServiceController::class, 'toggleAutoRenew'])->name('services.toggle-auto-renew');

    // Service add-ons
    Route::get('/sluzby/{service}/doplnky',                  [Panel\ServiceAddonController::class, 'index'])->name('services.addons.index');
    Route::post('/sluzby/{service}/doplnky/aktivovat',       [Panel\ServiceAddonController::class, 'activate'])->name('services.addons.activate');
    Route::delete('/sluzby/{service}/doplnky/{subscription}',[Panel\ServiceAddonController::class, 'cancel'])->name('services.addons.cancel');

    Route::get('/sluzby/{service}/marketplace', [Panel\MarketplaceController::class, 'index'])->name('marketplace.index');
    Route::post('/sluzby/{service}/marketplace/{app}', [Panel\MarketplaceController::class, 'install'])->name('marketplace.install');
    Route::delete('/sluzby/{service}/marketplace/{app}', [Panel\MarketplaceController::class, 'remove'])->name('marketplace.remove');

    Route::prefix('/gdpr')->name('compliance.')->group(function (): void {
        Route::get('/', [Panel\ComplianceController::class, 'index'])->name('index');
        Route::post('/export', [Panel\ComplianceController::class, 'requestExport'])->name('export');
        Route::post('/deletion', [Panel\ComplianceController::class, 'requestDeletion'])->name('deletion');
        Route::get('/download/{customer}', [Panel\ComplianceController::class, 'downloadExport'])->name('download');
    });

    Route::get('/developer', [Panel\DeveloperPortalController::class, 'index'])->name('developer.index');
    Route::prefix('/developer/oauth')->name('developer.oauth-apps.')->group(function (): void {
        Route::post('/', [Panel\DeveloperPortalController::class, 'storeOAuthApp'])->name('store');
        Route::patch('/{oauthApp}', [Panel\DeveloperPortalController::class, 'regenSecret'])->name('regen');
        Route::delete('/{oauthApp}', [Panel\DeveloperPortalController::class, 'destroyOAuthApp'])->name('destroy');
    });

    Route::get('/sluzby/{service}/waf', [Panel\WafController::class, 'index'])->name('waf.index');
    Route::post('/sluzby/{service}/waf', [Panel\WafController::class, 'store'])->name('waf.store');
    Route::delete('/sluzby/{service}/waf/{rule}', [Panel\WafController::class, 'destroy'])->name('waf.destroy');
    Route::patch('/sluzby/{service}/waf/{rule}', [Panel\WafController::class, 'toggle'])->name('waf.toggle');

    Route::get('/domeny', [Panel\DomainController::class, 'index'])->name('domains.index');
    Route::get('/domeny/{domain}', [Panel\DomainController::class, 'show'])->name('domains.show');
    Route::post('/domeny/{domain}/auto-renew', [Panel\DomainController::class, 'toggleAutoRenew'])->name('domains.auto-renew');
    Route::put('/domeny/{domain}/nameservery', [Panel\DomainController::class, 'updateNameservers'])->name('domains.nameservers');
    Route::get('/domeny/{domain}/dns', [Panel\DnsController::class, 'show'])->name('domains.dns');
    Route::post('/domeny/{domain}/dns', [Panel\DnsController::class, 'store'])->name('domains.dns.store');

    Route::get('/objednavky', [Panel\OrderController::class, 'index'])->name('orders.index');
    Route::get('/objednavky/nova', [Panel\OrderController::class, 'create'])->name('orders.create');
    Route::post('/objednavky', [Panel\OrderController::class, 'store'])->name('orders.store');
    Route::get('/objednavky/{order}', [Panel\OrderController::class, 'show'])->name('orders.show');

    Route::get('/pokladna', [Panel\CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/sleva/validovat', [Panel\DiscountCodeController::class, 'validate'])->name('discount.validate');

    Route::get('/kosik', [Panel\CartController::class, 'index'])->name('cart.index');
    Route::post('/kosik/pridat/{plan}', [Panel\CartController::class, 'add'])->name('cart.add');
    Route::delete('/kosik/odebrat/{plan}', [Panel\CartController::class, 'remove'])->name('cart.remove');
    Route::delete('/kosik', [Panel\CartController::class, 'clear'])->name('cart.clear');

    Route::get('/vernostni-program', [Panel\LoyaltyController::class, 'index'])->name('loyalty.index');
    Route::get('/udrzba', [Panel\ServiceMaintenanceController::class, 'index'])->name('maintenance.index');
    Route::get('/referral', [Panel\ReferralController::class, 'index'])->name('referral.index');

    Route::get('/oblibene', [Panel\WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('/oblibene/pridat/{plan}', [Panel\WishlistController::class, 'add'])->name('wishlist.add');
    Route::delete('/oblibene/odebrat/{plan}', [Panel\WishlistController::class, 'remove'])->name('wishlist.remove');

    Route::get('/fakturace/faktury', [Panel\BillingController::class, 'invoices'])->name('billing.invoices');
    Route::get('/fakturace/faktury/{invoice}', [Panel\BillingController::class, 'invoiceShow'])->name('billing.invoices.show');
    Route::get('/fakturace/faktury/{invoice}/tisk', [Panel\BillingController::class, 'invoicePrint'])->name('billing.invoices.print');
    Route::post('/fakturace/faktury/{invoice}/zaplatit/mock', [Panel\BillingController::class, 'payMock'])->name('billing.invoices.pay-mock');
    Route::post('/fakturace/faktury/{invoice}/zaplatit/kredit', [Panel\BillingController::class, 'payCredit'])->name('billing.invoices.pay-credit');
    Route::post('/fakturace/faktury/{invoice}/zaplatit/comgate', [Panel\BillingController::class, 'payComgate'])->name('billing.invoices.pay-comgate');
    Route::get('/fakturace/faktury/{invoice}/zaplatit/comgate/navrat', [Panel\BillingController::class, 'comgateReturn'])->name('billing.invoices.comgate-return');
    Route::post('/fakturace/faktury/{invoice}/zaplatit/stripe', [Panel\BillingController::class, 'payStripe'])->name('billing.invoices.pay-stripe');
    Route::get('/fakturace/faktury/{invoice}/zaplatit/stripe/navrat', [Panel\BillingController::class, 'stripeReturn'])->name('billing.invoices.stripe-return');
    Route::post('/fakturace/faktury/{invoice}/zaplatit/gopay', [Panel\BillingController::class, 'payGopay'])->name('billing.invoices.pay-gopay');
    Route::get('/fakturace/faktury/{invoice}/zaplatit/gopay/navrat', [Panel\BillingController::class, 'gopayReturn'])->name('billing.invoices.gopay-return');
    Route::get('/fakturace/platby', [Panel\BillingController::class, 'payments'])->name('billing.payments');
    Route::get('/fakturace/kredit', [Panel\BillingController::class, 'credits'])->name('billing.credits');
    Route::post('/fakturace/kredit/dobit', [Panel\BillingController::class, 'topUp'])->name('billing.credits.topup');

    Route::get('/dns-manager', [Panel\DnsZoneController::class, 'index'])->name('dns-manager.index');
    Route::post('/dns-manager', [Panel\DnsZoneController::class, 'store'])->name('dns-manager.store');
    Route::get('/dns-manager/{dnsZone}', [Panel\DnsZoneController::class, 'show'])->name('dns-manager.show');
    Route::delete('/dns-manager/{dnsZone}', [Panel\DnsZoneController::class, 'destroy'])->name('dns-manager.destroy');
    Route::post('/dns-manager/{dnsZone}/zaznamy', [Panel\DnsRecordController::class, 'store'])->name('dns-manager.records.store');
    Route::put('/dns-manager/{dnsZone}/zaznamy/{dnsRecord}', [Panel\DnsRecordController::class, 'update'])->name('dns-manager.records.update');
    Route::delete('/dns-manager/{dnsZone}/zaznamy/{dnsRecord}', [Panel\DnsRecordController::class, 'destroy'])->name('dns-manager.records.destroy');

    Route::get('/ucet/api-tokeny', [Panel\ApiTokenController::class, 'index'])->name('account.api-tokens');
    Route::get('/ucet/ssh-klice', [Panel\SshKeyController::class, 'index'])->name('account.ssh-keys.index');
    Route::post('/ucet/ssh-klice', [Panel\SshKeyController::class, 'store'])->name('account.ssh-keys.store');
    Route::delete('/ucet/ssh-klice/{sshKey}', [Panel\SshKeyController::class, 'destroy'])->name('account.ssh-keys.destroy');
    Route::post('/ucet/api-tokeny', [Panel\ApiTokenController::class, 'store'])->name('account.api-tokens.store');
    Route::delete('/ucet/api-tokeny/{token}', [Panel\ApiTokenController::class, 'destroy'])->name('account.api-tokens.destroy');

    Route::get('/ucet/profil', [Panel\AccountController::class, 'profile'])->name('account.profile');
    Route::put('/ucet/profil', [Panel\AccountController::class, 'updateProfile'])->name('account.profile.update');
    Route::post('/ucet/smazat', [Panel\AccountController::class, 'requestDeletion'])->name('account.delete-request');
    Route::get('/ucet/export-dat', [Panel\AccountController::class, 'exportData'])->name('account.data-export');
    Route::get('/ucet/fakturacni-udaje', [Panel\AccountController::class, 'billing'])->name('account.billing');
    Route::put('/ucet/fakturacni-udaje', [Panel\AccountController::class, 'updateBilling'])->name('account.billing.update');
    Route::get('/ucet/zabezpeceni', [Panel\AccountController::class, 'security'])->name('account.security');
    Route::put('/ucet/zmena-hesla', [Panel\AccountController::class, 'updatePassword'])->name('account.password.update');
    Route::get('/ucet/notifikace', [Panel\AccountController::class, 'notificationPreferences'])->name('account.notification-preferences');
    Route::put('/ucet/notifikace', [Panel\AccountController::class, 'updateNotificationPreferences'])->name('account.notification-preferences.update');

    Route::get('/faq', [Panel\FaqController::class, 'index'])->name('faq.index');

    Route::get('/blog', [Panel\BlogController::class, 'index'])->name('blog.index');
    Route::get('/blog/{slug}', [Panel\BlogController::class, 'show'])->name('blog.show');

    Route::get('/znalostni-baze', [Panel\KbController::class, 'index'])->name('kb.index');
    Route::get('/znalostni-baze/{slug}', [Panel\KbController::class, 'show'])->name('kb.show');
    Route::post('/znalostni-baze/{article}/hlasovat', [Panel\KbVoteController::class, 'vote'])->name('kb.vote');
    Route::post('/znalostni-baze/{article}/recenze', [Panel\KbVoteController::class, 'review'])->name('kb.review');

    Route::get('/podpora', [Panel\SupportController::class, 'index'])->name('support.index');
    Route::post('/podpora', [Panel\SupportController::class, 'store'])->name('support.store');
    Route::get('/podpora/{ticket}', [Panel\SupportController::class, 'show'])->name('support.show');
    Route::post('/podpora/{ticket}/odpoved', [Panel\SupportController::class, 'reply'])->name('support.reply');
    Route::post('/podpora/{ticket}/ai-navrh', [Panel\SupportController::class, 'aiSuggest'])->name('support.ai-suggest');
    Route::post('/podpora/{ticket}/uzavrit', [Panel\SupportController::class, 'close'])->name('support.close');
    Route::post('/podpora/{ticket}/hodnotit', [Panel\SupportController::class, 'rate'])->name('support.rate');

    Route::get('/ai', [Panel\AiController::class, 'index'])->name('ai.index');
    Route::post('/ai', [Panel\AiController::class, 'run'])->name('ai.run');
    Route::post('/ai/chat', [Panel\AiController::class, 'chat'])->name('ai.chat');

    Route::get('/notifikace', [Panel\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifikace/{id}/precist', [Panel\NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifikace/precist-vse', [Panel\NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    Route::get('/reseller-program', [Panel\ResellerController::class, 'index'])->name('reseller-program');
    Route::post('/reseller-program', [Panel\ResellerController::class, 'store'])->name('reseller-program.apply');
});

// Impersonation stop — auth only (must work even while impersonating as customer)
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/impersonate/stop', [Admin\ImpersonateController::class, 'stop'])->name('impersonate.stop');
});

/*
|--------------------------------------------------------------------------
| Partner portal — auth + access-partner permission
|--------------------------------------------------------------------------
*/

// Partner registration — any authenticated user can apply
Route::middleware(['auth'])->prefix('partner')->name('partner.')->group(function (): void {
    Route::get('/prihlaska', [Panel\PartnerRegistrationController::class, 'apply'])->name('apply');
    Route::post('/prihlaska', [Panel\PartnerRegistrationController::class, 'store'])->name('apply.store');
});

Route::middleware(['auth', 'can:access-partner'])->prefix('partner')->name('partner.')->group(function (): void {
    Route::get('/', [Partner\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/referraly', [Partner\PartnerController::class, 'referrals'])->name('referrals');
    Route::get('/provize', [Partner\PartnerController::class, 'commissions'])->name('commissions');
    Route::get('/vyplaty', [Partner\PartnerController::class, 'payouts'])->name('payouts');
    Route::get('/materialy', [Partner\PartnerController::class, 'assets'])->name('assets');
    Route::get('/nastaveni', [Partner\PartnerController::class, 'profile'])->name('profile');
    Route::post('/vyplata/zadost', [Panel\PartnerRegistrationController::class, 'requestPayout'])->name('payout.request');
});

/*
|--------------------------------------------------------------------------
| Reseller portal — auth + access-reseller permission
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'can:access-reseller'])->prefix('reseller')->name('reseller.')->group(function (): void {
    Route::get('/', [Reseller\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/zakaznici', [Reseller\CustomerController::class, 'index'])->name('customers.index');
    Route::get('/zakaznici/{customer}', [Reseller\CustomerController::class, 'show'])->name('customers.show');
    Route::get('/branding', [Reseller\BrandingController::class, 'show'])->name('branding.show');
    Route::put('/branding', [Reseller\BrandingController::class, 'update'])->name('branding.update');
});

/*
|--------------------------------------------------------------------------
| Admin (Cuba template, layouts.panel) — auth + access-admin gate
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'can:access-admin', 'require-admin-2fa'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

    Route::get('/zakaznici', [Admin\CustomerController::class, 'index'])->name('customers.index');
    Route::get('/zakaznici/export', [Admin\CustomerController::class, 'export'])->name('customers.export');
    Route::get('/zakaznici/{customer}', [Admin\CustomerController::class, 'show'])->name('customers.show');
    Route::post('/zakaznici/{customer}/kredit', [Admin\CustomerController::class, 'adjustCredit'])->name('customers.credit');
    Route::put('/zakaznici/{customer}/poznamky', [Admin\CustomerController::class, 'updateNotes'])->name('customers.notes');

    Route::get('/objednavky', [Admin\OrderController::class, 'index'])->name('orders.index');
    Route::get('/objednavky/export', [Admin\OrderController::class, 'export'])->name('orders.export');
    Route::get('/objednavky/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');

    Route::get('/faktury', [Admin\InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/faktury/export', [Admin\InvoiceController::class, 'export'])->name('invoices.export');
    Route::post('/faktury/hromadna-upominka', [Admin\InvoiceController::class, 'sendBulkPaymentReminders'])->name('invoices.bulk-payment-reminder');
    Route::post('/faktury/hromadne-zaplaceni', [Admin\InvoiceController::class, 'batchMarkPaid'])->name('invoices.batch-mark-paid');
    Route::get('/faktury/{invoice}', [Admin\InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/faktury/{invoice}/pdf', [Admin\InvoiceController::class, 'downloadPdf'])->name('invoices.pdf');
    Route::post('/faktury/{invoice}/oznacit-zaplacenou', [Admin\InvoiceController::class, 'markPaid'])->name('invoices.mark-paid');
    Route::post('/faktury/{invoice}/danovy-doklad', [Admin\InvoiceController::class, 'issueTaxDocument'])->name('invoices.tax-document');
    Route::post('/faktury/{invoice}/dobropis', [Admin\InvoiceController::class, 'issueCreditNote'])->name('invoices.credit-note');
    Route::post('/faktury/{invoice}/zrusit', [Admin\InvoiceController::class, 'cancel'])->name('invoices.cancel');
    Route::post('/faktury/{invoice}/odeslat-email', [Admin\InvoiceController::class, 'resendEmail'])->name('invoices.resend-email');
    Route::post('/faktury/{invoice}/upominka-platby', [Admin\InvoiceController::class, 'sendPaymentReminder'])->name('invoices.payment-reminder');

    Route::get('/platby', [Admin\PaymentController::class, 'index'])->name('payments.index');
    Route::get('/platby/export', [Admin\PaymentController::class, 'export'])->name('payments.export');
    Route::post('/platby/{payment}/vraceni', [Admin\PaymentController::class, 'refund'])->name('payments.refund');

    Route::get('/webhook-logy', [Admin\WebhookLogController::class, 'index'])->name('webhook-logs.index');

    Route::get('/kredit', [Admin\CreditController::class, 'index'])->name('credits.index');
    Route::get('/kredit/transakce', [Admin\CreditController::class, 'transactions'])->name('credits.transactions');

    Route::get('/produkty', [Admin\ProductController::class, 'index'])->name('products.index');
    Route::get('/produkty/novy', [Admin\ProductController::class, 'create'])->name('products.create');
    Route::post('/produkty', [Admin\ProductController::class, 'store'])->name('products.store');
    Route::get('/produkty/{product}/upravit', [Admin\ProductController::class, 'edit'])->name('products.edit');
    Route::put('/produkty/{product}', [Admin\ProductController::class, 'update'])->name('products.update');
    Route::delete('/produkty/{product}', [Admin\ProductController::class, 'destroy'])->name('products.destroy');
    Route::post('/produkty/{product}/plan', [Admin\ProductController::class, 'addPlan'])->name('products.plans.add');
    Route::put('/plany/{plan}', [Admin\ProductController::class, 'updatePlan'])->name('products.plans.update');
    Route::delete('/plany/{plan}', [Admin\ProductController::class, 'deletePlan'])->name('products.plans.delete');

    Route::get('/sluzby', [Admin\ServiceController::class, 'index'])->name('services.index');
    Route::get('/sluzby/export', [Admin\ServiceController::class, 'export'])->name('services.export');
    Route::post('/sluzby/hromadne-pozastavit', [Admin\ServiceController::class, 'batchSuspend'])->name('services.batch-suspend');
    Route::post('/sluzby/hromadne-reaktivovat', [Admin\ServiceController::class, 'batchUnsuspend'])->name('services.batch-unsuspend');
    Route::get('/sluzby/{service}', [Admin\ServiceController::class, 'show'])->name('services.show');
    Route::post('/sluzby/{service}/pozastavit', [Admin\ServiceController::class, 'suspend'])->name('services.suspend');
    Route::post('/sluzby/{service}/obnovit', [Admin\ServiceController::class, 'unsuspend'])->name('services.unsuspend');
    Route::put('/sluzby/{service}/popis', [Admin\ServiceController::class, 'updateLabel'])->name('services.update-label');
    Route::put('/sluzby/{service}/splatnost', [Admin\ServiceController::class, 'adjustDueDate'])->name('services.adjust-due-date');
    Route::post('/sluzby/{service}/obnova', [Admin\ServiceController::class, 'manualRenewal'])->name('services.manual-renewal');

    Route::resource('/service-addons', Admin\ServiceAddonController::class)->names('service-addons');

    Route::get('/domeny', [Admin\DomainController::class, 'index'])->name('domains.index');
    Route::get('/domeny/export', [Admin\DomainController::class, 'export'])->name('domains.export');
    Route::get('/domeny/{domain}', [Admin\DomainController::class, 'show'])->name('domains.show');
    Route::post('/domeny/{domain}/auto-renew', [Admin\DomainController::class, 'toggleAutoRenew'])->name('domains.toggle-auto-renew');

    Route::get('/dns', [Admin\DnsController::class, 'index'])->name('dns.index');
    Route::get('/dns/{dnsZone}', [Admin\DnsController::class, 'show'])->name('dns.show');

    Route::prefix('marketplace')->name('marketplace.')->group(function (): void {
        Route::get('/', [Admin\MarketplaceController::class, 'index'])->name('index');
        Route::post('/', [Admin\MarketplaceController::class, 'store'])->name('store');
        Route::put('/{app}', [Admin\MarketplaceController::class, 'update'])->name('update');
        Route::delete('/{app}', [Admin\MarketplaceController::class, 'destroy'])->name('destroy');
        Route::post('/{app}/toggle', [Admin\MarketplaceController::class, 'toggle'])->name('toggle');
    });

    Route::prefix('waf')->name('waf.')->group(function (): void {
        Route::get('/', [Admin\WafController::class, 'index'])->name('index');
        Route::post('/', [Admin\WafController::class, 'store'])->name('store');
        Route::delete('/{rule}', [Admin\WafController::class, 'destroy'])->name('destroy');
        Route::patch('/{rule}', [Admin\WafController::class, 'toggle'])->name('toggle');
    });

    Route::prefix('developer')->name('developer.')->group(function (): void {
        Route::get('/', [Admin\DeveloperPortalController::class, 'index'])->name('index');
        Route::prefix('oauth-apps')->name('oauth-apps.')->group(function (): void {
            Route::delete('/{oauthApp}', [Admin\DeveloperPortalController::class, 'destroy'])->name('destroy');
        });
    });

    Route::prefix('compliance')->name('compliance.')->group(function (): void {
        Route::get('/', [Admin\ComplianceController::class, 'index'])->name('index');
        Route::patch('/{gdprRequest}/approve', [Admin\ComplianceController::class, 'approve'])->name('approve');
        Route::patch('/{gdprRequest}/reject', [Admin\ComplianceController::class, 'reject'])->name('reject');
    });

    Route::get('/servery', [Admin\ServerController::class, 'index'])->name('servers.index');
    Route::get('/servery/novy', [Admin\ServerController::class, 'create'])->name('servers.create');
    Route::post('/servery', [Admin\ServerController::class, 'store'])->name('servers.store');
    Route::get('/servery/{server}/upravit', [Admin\ServerController::class, 'edit'])->name('servers.edit');
    Route::put('/servery/{server}', [Admin\ServerController::class, 'update'])->name('servers.update');
    Route::delete('/servery/{server}', [Admin\ServerController::class, 'destroy'])->name('servers.destroy');
    Route::post('/servery/{server}/test', [Admin\ServerController::class, 'test'])->name('servers.test');

    /* ── Game server presets (Pterodactyl) ── */
    Route::get('/game-presety', [Admin\GameServerPresetController::class, 'index'])->name('game-presets.index');
    Route::get('/game-presety/novy', [Admin\GameServerPresetController::class, 'create'])->name('game-presets.create');
    Route::post('/game-presety', [Admin\GameServerPresetController::class, 'store'])->name('game-presets.store');
    Route::get('/game-presety/{gamePreset}/upravit', [Admin\GameServerPresetController::class, 'edit'])->name('game-presets.edit');
    Route::put('/game-presety/{gamePreset}', [Admin\GameServerPresetController::class, 'update'])->name('game-presets.update');
    Route::delete('/game-presety/{gamePreset}', [Admin\GameServerPresetController::class, 'destroy'])->name('game-presets.destroy');

    Route::get('/provisioning', [Admin\ProvisioningController::class, 'index'])->name('provisioning.index');
    Route::get('/provisioning/{task}', [Admin\ProvisioningController::class, 'show'])->name('provisioning.show');
    Route::post('/provisioning/{task}/retry', [Admin\ProvisioningController::class, 'retry'])->name('provisioning.retry');

    Route::prefix('dunning')->name('dunning.')->group(function (): void {
        Route::get('/', [Admin\DunningController::class, 'index'])->name('index');
        Route::post('/{invoice}/pause', [Admin\DunningController::class, 'pause'])->name('pause');
        Route::post('/{invoice}/resume', [Admin\DunningController::class, 'resume'])->name('resume');
    });

    Route::prefix('outgoing-webhooky')->name('outgoing-webhooks.')->group(function (): void {
        Route::get('/', [Admin\OutgoingWebhookController::class, 'index'])->name('index');
        Route::get('/novy', [Admin\OutgoingWebhookController::class, 'create'])->name('create');
        Route::post('/', [Admin\OutgoingWebhookController::class, 'store'])->name('store');
        Route::delete('/{outgoingWebhook}', [Admin\OutgoingWebhookController::class, 'destroy'])->name('destroy');
        Route::get('/{outgoingWebhook}/doruceni', [Admin\OutgoingWebhookController::class, 'deliveries'])->name('deliveries');
    });

    // Inbound webhooks & endpoints
    Route::prefix('/prichozi-webhooky')->name('webhooks.inbound.')->group(function (): void {
        Route::get('/',                              [Admin\WebhookController::class, 'index'])->name('index');
        Route::get('/{log}',                         [Admin\WebhookController::class, 'show'])->name('show');
    });
    Route::prefix('/webhook-endpoints')->name('webhooks.endpoint.')->group(function (): void {
        Route::get('/novy',                          [Admin\WebhookController::class, 'endpointCreate'])->name('create');
        Route::post('/',                             [Admin\WebhookController::class, 'endpointStore'])->name('store');
        Route::get('/{endpoint}/upravit',            [Admin\WebhookController::class, 'endpointEdit'])->name('edit');
        Route::put('/{endpoint}',                    [Admin\WebhookController::class, 'endpointUpdate'])->name('update');
        Route::delete('/{endpoint}',                 [Admin\WebhookController::class, 'endpointDestroy'])->name('destroy');
        Route::post('/{endpoint}/prepnout',          [Admin\WebhookController::class, 'endpointToggle'])->name('toggle');
    });

    Route::get('/monitoring', [Admin\MonitoringController::class, 'index'])->name('monitoring.index');
    Route::put('/monitoring/{monitor}/prahy', [Admin\MonitoringController::class, 'updateThresholds'])->name('monitoring.thresholds');
    Route::get('/sla', [Admin\SlaController::class, 'index'])->name('sla.index');

    Route::prefix('status-page')->name('status-page.')->group(function (): void {
        Route::get('/', [Admin\StatusPageController::class, 'index'])->name('index');
        Route::post('/komponenty', [Admin\StatusPageController::class, 'storeComponent'])->name('components.store');
        Route::put('/komponenty/{component}', [Admin\StatusPageController::class, 'updateComponent'])->name('components.update');
        Route::delete('/komponenty/{component}', [Admin\StatusPageController::class, 'destroyComponent'])->name('components.destroy');
        Route::post('/udrzba', [Admin\StatusPageController::class, 'storeMaintenance'])->name('maintenances.store');
        Route::put('/udrzba/{maintenance}', [Admin\StatusPageController::class, 'updateMaintenance'])->name('maintenances.update');
        Route::delete('/udrzba/{maintenance}', [Admin\StatusPageController::class, 'destroyMaintenance'])->name('maintenances.destroy');
    });
    Route::get('/zalohy', [Admin\BackupController::class, 'index'])->name('backups.index');

    Route::get('/integrace', [Admin\IntegrationController::class, 'index'])->name('integrations.index');
    Route::get('/integrace/{integration}', [Admin\IntegrationController::class, 'edit'])->name('integrations.edit');
    Route::put('/integrace/{integration}', [Admin\IntegrationController::class, 'update'])->name('integrations.update');
    Route::post('/integrace/{integration}/test', [Admin\IntegrationController::class, 'test'])->name('integrations.test');

    Route::get('/podpora', [Admin\SupportController::class, 'index'])->name('support.index');
    Route::get('/podpora/sla-monitor', [Admin\SupportController::class, 'slaMonitor'])->name('support.sla-monitor');
    Route::get('/podpora/csat', [Admin\CsatController::class, 'index'])->name('support.csat');
    Route::get('/podpora/{ticket}', [Admin\SupportController::class, 'show'])->name('support.show');
    Route::post('/podpora/{ticket}/odpoved', [Admin\SupportController::class, 'reply'])->name('support.reply');
    Route::put('/podpora/{ticket}', [Admin\SupportController::class, 'update'])->name('support.update');
    Route::post('/podpora/{ticket}/sla', [Admin\SupportController::class, 'setSla'])->name('support.sla');
    Route::post('/podpora/{ticket}/kb-navrh', [Admin\SupportController::class, 'generateKbDraft'])->name('support.kb-draft');
    Route::post('/podpora/{ticket}/ai-analyse', [Admin\SupportController::class, 'analyseTicket'])->name('support.ai-analyse');

    Route::get('/ai', [Admin\AiController::class, 'index'])->name('ai.index');
    Route::post('/ai', [Admin\AiController::class, 'run'])->name('ai.run');
    Route::post('/ai/schvaleni/{approval}', [Admin\AiController::class, 'review'])->name('ai.review');

    Route::get('/metriky', [Admin\MetricsController::class, 'index'])->name('metrics.index');
    Route::get('/bi', [Admin\BiController::class, 'index'])->name('bi.index');
    Route::get('/bi-v2', [Admin\BiV2Controller::class, 'index'])->name('bi-v2.index');
    Route::get('/api-usage', [Admin\ApiUsageController::class, 'index'])->name('api-usage.index');

    // Bulk operations
    Route::prefix('/hromadne')->name('bulk.')->group(function (): void {
        Route::get('/',                                  [Admin\BulkController::class, 'index'])->name('index');
        Route::post('/sluzby/prodlouzit',               [Admin\BulkController::class, 'serviceExtendDueDate'])->name('service-extend');
        Route::post('/sluzby/ukoncit',                  [Admin\BulkController::class, 'serviceTerminate'])->name('service-terminate');
        Route::post('/sluzby/export',                   [Admin\BulkController::class, 'serviceExport'])->name('service-export');
        Route::post('/faktury/storno',                  [Admin\BulkController::class, 'invoiceVoid'])->name('invoice-void');
        Route::post('/zakaznici/export',                [Admin\BulkController::class, 'customerExport'])->name('customer-export');
    });

    // Service maintenance windows
    Route::prefix('/udrzba')->name('maintenance.')->group(function (): void {
        Route::get('/',                          [Admin\ServiceMaintenanceController::class, 'index'])->name('index');
        Route::get('/nove',                      [Admin\ServiceMaintenanceController::class, 'create'])->name('create');
        Route::post('/',                         [Admin\ServiceMaintenanceController::class, 'store'])->name('store');
        Route::get('/{maintenance}/upravit',     [Admin\ServiceMaintenanceController::class, 'edit'])->name('edit');
        Route::put('/{maintenance}',             [Admin\ServiceMaintenanceController::class, 'update'])->name('update');
        Route::delete('/{maintenance}',          [Admin\ServiceMaintenanceController::class, 'destroy'])->name('destroy');
        Route::post('/{maintenance}/zahajit',    [Admin\ServiceMaintenanceController::class, 'start'])->name('start');
        Route::post('/{maintenance}/dokoncit',   [Admin\ServiceMaintenanceController::class, 'complete'])->name('complete');
        Route::post('/{maintenance}/zrusit',     [Admin\ServiceMaintenanceController::class, 'cancel'])->name('cancel');
    });

    // Loyalty milestones & rewards (admin)
    Route::prefix('/vernostni-program')->name('loyalty.')->group(function (): void {
        Route::get('/',                              [Admin\LoyaltyController::class, 'index'])->name('index');
        Route::get('/novy',                          [Admin\LoyaltyController::class, 'create'])->name('create');
        Route::post('/',                             [Admin\LoyaltyController::class, 'store'])->name('store');
        Route::get('/{milestone}/upravit',           [Admin\LoyaltyController::class, 'edit'])->name('edit');
        Route::put('/{milestone}',                   [Admin\LoyaltyController::class, 'update'])->name('update');
        Route::delete('/{milestone}',                [Admin\LoyaltyController::class, 'destroy'])->name('destroy');
        Route::post('/zkontrolovat',                 [Admin\LoyaltyController::class, 'checkCustomer'])->name('check');
    });

    // Automation rules
    Route::prefix('/automatizace')->name('automation.')->group(function (): void {
        Route::get('/',                                           [Admin\AutomationController::class, 'index'])->name('index');
        Route::get('/nova',                                       [Admin\AutomationController::class, 'create'])->name('create');
        Route::post('/',                                          [Admin\AutomationController::class, 'store'])->name('store');
        Route::get('/{rule}/upravit',                            [Admin\AutomationController::class, 'edit'])->name('edit');
        Route::put('/{rule}',                                    [Admin\AutomationController::class, 'update'])->name('update');
        Route::delete('/{rule}',                                 [Admin\AutomationController::class, 'destroy'])->name('destroy');
        Route::post('/{rule}/prepnout',                          [Admin\AutomationController::class, 'toggle'])->name('toggle');
        Route::get('/{rule}/logy',                               [Admin\AutomationController::class, 'logs'])->name('logs');
        Route::post('/{rule}/test',                              [Admin\AutomationController::class, 'testFire'])->name('test');
    });

    Route::get('/system', [Admin\SystemHealthController::class, 'index'])->name('system.index');

    Route::get('/audit', [Admin\AuditLogController::class, 'index'])->name('logs.audit');
    Route::get('/audit/export', [Admin\AuditLogController::class, 'export'])->name('logs.audit.export');

    Route::get('/bezpecnost', [Admin\SecurityController::class, 'index'])->name('security.index');

    /* ── User management ── */
    /* ── Impersonation ── */
    Route::get('/impersonate/{user}/start', [Admin\ImpersonateController::class, 'start'])->name('impersonate.start');

    Route::get('/uzivatele', [Admin\UserController::class, 'index'])->name('users.index');
    Route::get('/uzivatele/karty', [Admin\UserController::class, 'cards'])->name('user-cards');
    Route::get('/uzivatele/novy', [Admin\UserController::class, 'create'])->name('users.create');
    Route::post('/uzivatele', [Admin\UserController::class, 'store'])->name('users.store');
    Route::get('/uzivatele/{user}/upravit', [Admin\UserController::class, 'edit'])->name('users.edit');
    Route::put('/uzivatele/{user}', [Admin\UserController::class, 'update'])->name('users.update');

    /* ── Roles & permissions ── */
    Route::get('/role-opravneni', [Admin\PageController::class, 'rolesPermission'])->name('roles-permission');

    /* ── Pricing ── */
    Route::get('/cenik', [Admin\PageController::class, 'pricing'])->name('pricing');

    /* ── Discount codes ── */
    Route::get('/slevy', [Admin\DiscountCodeController::class, 'index'])->name('discount-codes.index');
    Route::post('/slevy', [Admin\DiscountCodeController::class, 'store'])->name('discount-codes.store');
    Route::post('/slevy/{code}/toggle', [Admin\DiscountCodeController::class, 'toggle'])->name('discount-codes.toggle');
    Route::delete('/slevy/{code}', [Admin\DiscountCodeController::class, 'destroy'])->name('discount-codes.destroy');

    /* ── Reviews ── */
    Route::get('/recenze', [Admin\PageController::class, 'reviews'])->name('reviews');

    /* ── Mailbox ── */
    Route::get('/posta', [Admin\PageController::class, 'mailbox'])->name('mailbox');

    /* ── Kanban ── */
    Route::get('/kanban', [Admin\PageController::class, 'kanban'])->name('kanban');

    /* ── Tasks ── */
    Route::get('/ukoly', [Admin\TaskController::class, 'index'])->name('tasks');
    Route::post('/ukoly', [Admin\TaskController::class, 'store'])->name('tasks.store');
    Route::put('/ukoly/{task}', [Admin\TaskController::class, 'update'])->name('tasks.update');
    Route::post('/ukoly/{task}/status', [Admin\TaskController::class, 'toggleStatus'])->name('tasks.toggle');
    Route::delete('/ukoly/{task}', [Admin\TaskController::class, 'destroy'])->name('tasks.destroy');

    /* ── Calendar ── */
    Route::get('/kalendar', [Admin\CalendarController::class, 'index'])->name('calendar');
    Route::get('/kalendar/events', [Admin\CalendarController::class, 'events'])->name('calendar.events');

    /* ── Todo ── */
    Route::get('/todo', [Admin\PageController::class, 'todo'])->name('todo');

    /* ── Contacts ── */
    Route::get('/kontakty', [Admin\PageController::class, 'contacts'])->name('contacts');

    /* ── Bookmarks ── */
    Route::get('/zalozky', [Admin\PageController::class, 'bookmarks'])->name('bookmarks');

    /* ── Social / Profile ── */
    Route::get('/profil', [Admin\PageController::class, 'social'])->name('social');

    /* ── File manager ── */
    Route::get('/soubory', [Admin\PageController::class, 'fileManager'])->name('file-manager');

    /* ── Subscribers ── */
    Route::get('/odberevatele', [Admin\SubscriberController::class, 'index'])->name('subscribers.index');
    Route::get('/odberevatele/export', [Admin\SubscriberController::class, 'export'])->name('subscribers.export');
    Route::post('/odberevatele', [Admin\SubscriberController::class, 'store'])->name('subscribers.store');
    Route::post('/odberevatele/{subscriber}/toggle', [Admin\SubscriberController::class, 'toggle'])->name('subscribers.toggle');
    Route::delete('/odberevatele/{subscriber}', [Admin\SubscriberController::class, 'destroy'])->name('subscribers.destroy');

    /* ── Newsletter ── */
    Route::get('/newsletter', [Admin\NewsletterCampaignController::class, 'index'])->name('newsletter.index');
    Route::get('/newsletter/nova', [Admin\NewsletterCampaignController::class, 'create'])->name('newsletter.create');
    Route::post('/newsletter', [Admin\NewsletterCampaignController::class, 'store'])->name('newsletter.store');
    Route::get('/newsletter/{campaign}', [Admin\NewsletterCampaignController::class, 'show'])->name('newsletter.show');
    Route::get('/newsletter/{campaign}/upravit', [Admin\NewsletterCampaignController::class, 'edit'])->name('newsletter.edit');
    Route::put('/newsletter/{campaign}', [Admin\NewsletterCampaignController::class, 'update'])->name('newsletter.update');
    Route::delete('/newsletter/{campaign}', [Admin\NewsletterCampaignController::class, 'destroy'])->name('newsletter.destroy');
    Route::post('/newsletter/{campaign}/odeslat', [Admin\NewsletterCampaignController::class, 'send'])->name('newsletter.send');
    Route::post('/newsletter/{campaign}/odeslano', [Admin\NewsletterCampaignController::class, 'markSent'])->name('newsletter.mark-sent');

    /* ── Drip sequences ── */
    Route::get('/drip', [Admin\DripSequenceController::class, 'index'])->name('drip.index');
    Route::post('/drip', [Admin\DripSequenceController::class, 'store'])->name('drip.store');
    Route::get('/drip/{drip}', [Admin\DripSequenceController::class, 'show'])->name('drip.show');
    Route::delete('/drip/{drip}', [Admin\DripSequenceController::class, 'destroy'])->name('drip.destroy');
    Route::post('/drip/{drip}/aktivovat', [Admin\DripSequenceController::class, 'toggleActive'])->name('drip.toggle');
    Route::post('/drip/{drip}/krok', [Admin\DripSequenceController::class, 'storeStep'])->name('drip.step.store');
    Route::delete('/drip/{drip}/krok/{step}', [Admin\DripSequenceController::class, 'destroyStep'])->name('drip.step.destroy');
    Route::post('/drip/{drip}/zapsat', [Admin\DripSequenceController::class, 'enroll'])->name('drip.enroll');

    /* ── Sitemap ── */
    Route::get('/mapa-webu', [Admin\PageController::class, 'sitemap'])->name('sitemap');

    /* ── Sample page ── */
    Route::get('/ukazka', [Admin\PageController::class, 'samplePage'])->name('sample-page');

    /* ── Search ── */
    Route::get('/hledani', [Admin\SearchController::class, 'index'])->name('search');

    Route::get('/nastaveni', [Admin\SettingsController::class, 'index'])->name('settings.index');
    Route::post('/nastaveni', [Admin\SettingsController::class, 'update'])->name('settings.update');

    /* ── Exchange rates & DAC7 ── */
    Route::get('/kurzy', [Admin\ExchangeRateController::class, 'index'])->name('exchange-rates.index');
    Route::put('/kurzy/{currency}', [Admin\ExchangeRateController::class, 'update'])->name('exchange-rates.update');
    Route::get('/dac7/export', [Admin\Dac7ReportController::class, 'export'])->name('dac7.export');

    /* ── Financial reports ── */
    Route::get('/pohledavky-aging', [Admin\FinancialReportController::class, 'aging'])->name('financial-report.aging');
    Route::get('/pohledavky-aging/export-csv', [Admin\FinancialReportController::class, 'exportRevenueCsv'])->name('financial-report.revenue-csv');

    Route::get('/obsah', [SiteContentController::class, 'index'])->name('site-content.index');
    Route::post('/obsah/bulk', [SiteContentController::class, 'bulkUpdate'])->name('site-content.bulk');
    Route::get('/obsah/{siteContent}/upravit', [SiteContentController::class, 'edit'])->name('site-content.edit');
    Route::put('/obsah/{siteContent}', [SiteContentController::class, 'update'])->name('site-content.update');

    Route::get('/blog', [AdminBlogController::class, 'index'])->name('blog.index');
    Route::get('/blog/{blog}/nahled', [AdminBlogController::class, 'show'])->name('blog.show');
    Route::get('/blog/novy', [AdminBlogController::class, 'create'])->name('blog.create');
    Route::post('/blog', [AdminBlogController::class, 'store'])->name('blog.store');
    Route::get('/blog/{blog}/upravit', [AdminBlogController::class, 'edit'])->name('blog.edit');
    Route::put('/blog/{blog}', [AdminBlogController::class, 'update'])->name('blog.update');
    Route::delete('/blog/{blog}', [AdminBlogController::class, 'destroy'])->name('blog.destroy');

    Route::get('/znalostni-baze', [AdminKbController::class, 'index'])->name('kb.index');
    Route::get('/znalostni-baze/{kb}/nahled', [AdminKbController::class, 'show'])->name('kb.show');
    Route::post('/znalostni-baze/recenze/{review}/schvalit', [AdminKbController::class, 'approveReview'])->name('kb.review.approve');
    Route::delete('/znalostni-baze/recenze/{review}', [AdminKbController::class, 'deleteReview'])->name('kb.review.delete');
    Route::get('/znalostni-baze/novy', [AdminKbController::class, 'create'])->name('kb.create');
    Route::post('/znalostni-baze', [AdminKbController::class, 'store'])->name('kb.store');
    Route::get('/znalostni-baze/{kb}/upravit', [AdminKbController::class, 'edit'])->name('kb.edit');
    Route::put('/znalostni-baze/{kb}', [AdminKbController::class, 'update'])->name('kb.update');
    Route::delete('/znalostni-baze/{kb}', [AdminKbController::class, 'destroy'])->name('kb.destroy');

    Route::get('/partner-program/nastaveni', [Admin\PartnerProgramController::class, 'settings'])->name('partner-program.settings');
    Route::put('/partner-program/nastaveni', [Admin\PartnerProgramController::class, 'updateSettings'])->name('partner-program.settings.update');

    Route::prefix('partneri')->name('partners.')->group(function (): void {
        Route::get('/', [Admin\PartnerController::class, 'index'])->name('index');
        Route::get('/cekajici', [Admin\PartnerController::class, 'pending'])->name('pending');
        Route::get('/novy', [Admin\PartnerController::class, 'create'])->name('create');
        Route::post('/', [Admin\PartnerController::class, 'store'])->name('store');
        Route::get('/generate-code', [Admin\PartnerController::class, 'generateCode'])->name('generate-code');
        Route::get('/{partner:uuid}', [Admin\PartnerController::class, 'show'])->name('show');
        Route::get('/{partner:uuid}/upravit', [Admin\PartnerController::class, 'edit'])->name('edit');
        Route::put('/{partner:uuid}', [Admin\PartnerController::class, 'update'])->name('update');
        Route::post('/{partner:uuid}/status', [Admin\PartnerController::class, 'changeStatus'])->name('status');
        Route::post('/{partner:uuid}/schvalit', [Admin\PartnerController::class, 'approve'])->name('approve');
        Route::post('/{partner:uuid}/zamitnout', [Admin\PartnerController::class, 'reject'])->name('reject');
        Route::post('/{partner:uuid}/provize/{commission}/schvalit', [Admin\PartnerController::class, 'approveCommission'])->name('commissions.approve');
        Route::post('/{partner:uuid}/provize/{commission}/zamitnout', [Admin\PartnerController::class, 'rejectCommission'])->name('commissions.reject');
        Route::post('/{partner:uuid}/vyplata', [Admin\PartnerController::class, 'createPayout'])->name('payouts.create');
        Route::post('/{partner:uuid}/vyplata/{payout}/zaplatit', [Admin\PartnerController::class, 'markPayoutPaid'])->name('payouts.paid');
        Route::post('/{partner:uuid}/vyplata/{payout}/zrusit', [Admin\PartnerController::class, 'cancelPayout'])->name('payouts.cancel');
    });

    Route::prefix('reselleri')->name('resellers.')->group(function (): void {
        Route::get('/', [Admin\ResellerController::class, 'index'])->name('index');
        Route::get('/{reseller}', [Admin\ResellerController::class, 'show'])->name('show');
        Route::post('/{reseller}/schvalit', [Admin\ResellerController::class, 'approve'])->name('approve');
        Route::post('/{reseller}/zamitnout', [Admin\ResellerController::class, 'reject'])->name('reject');
        Route::post('/{reseller}/pozastavit', [Admin\ResellerController::class, 'suspend'])->name('suspend');
        Route::post('/{reseller}/odebrat-pristup', [Admin\ResellerController::class, 'revoke'])->name('revoke');
        Route::put('/{reseller}/markup', [Admin\ResellerController::class, 'updateMarkup'])->name('markup');

        Route::prefix('/{reseller}/cenik')->name('pricing.')->group(function (): void {
            Route::get('/', [Admin\ResellerPricingController::class, 'index'])->name('index');
            Route::post('/', [Admin\ResellerPricingController::class, 'store'])->name('store');
            Route::delete('/{override}', [Admin\ResellerPricingController::class, 'destroy'])->name('destroy');
        });
    });

    // Referral program (admin)
    Route::prefix('/referral-program')->name('referrals.')->group(function (): void {
        Route::get('/',                           [Admin\ReferralController::class, 'index'])->name('index');
        Route::post('/{referral}/kvalifikovat',   [Admin\ReferralController::class, 'qualify'])->name('qualify');
        Route::post('/{referral}/odemnit',        [Admin\ReferralController::class, 'reward'])->name('reward');
        Route::post('/{referral}/expirovat',      [Admin\ReferralController::class, 'expire'])->name('expire');
    });

    // Financial exports
    Route::prefix('/financni-exporty')->name('exports.')->group(function (): void {
        Route::get('/',                  [Admin\FinancialExportController::class, 'index'])->name('index');
        Route::get('/novy',              [Admin\FinancialExportController::class, 'create'])->name('create');
        Route::post('/',                 [Admin\FinancialExportController::class, 'store'])->name('store');
        Route::get('/{export}/stazeni',  [Admin\FinancialExportController::class, 'download'])->name('download');
        Route::delete('/{export}',       [Admin\FinancialExportController::class, 'destroy'])->name('destroy');
    });
});
