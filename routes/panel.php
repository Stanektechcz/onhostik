<?php

declare(strict_types=1);

use App\Http\Controllers\Admin;
use App\Http\Controllers\Panel;
use App\Http\Controllers\Partner;
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

    Route::get('/sluzby', [Panel\ServiceController::class, 'index'])->name('services.index');
    Route::get('/sluzby/{service}', [Panel\ServiceController::class, 'show'])->name('services.show');
    Route::post('/sluzby/{service}/zaloha', [Panel\ServiceController::class, 'requestBackup'])->name('services.backup');
    Route::post('/sluzby/{service}/wordpress', [Panel\ServiceController::class, 'installWordpress'])->name('services.wordpress');
    Route::get('/sluzby/{service}/zmenit-plan', [Panel\ServiceController::class, 'changePlan'])->name('services.change-plan');
    Route::post('/sluzby/{service}/zmenit-plan', [Panel\ServiceController::class, 'applyChangePlan'])->name('services.apply-change-plan');

    Route::get('/domeny', [Panel\DomainController::class, 'index'])->name('domains.index');
    Route::get('/domeny/{domain}', [Panel\DomainController::class, 'show'])->name('domains.show');
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
    Route::get('/fakturace/platby', [Panel\BillingController::class, 'payments'])->name('billing.payments');
    Route::get('/fakturace/kredit', [Panel\BillingController::class, 'credits'])->name('billing.credits');
    Route::post('/fakturace/kredit/dobit', [Panel\BillingController::class, 'topUp'])->name('billing.credits.topup');

    Route::get('/ucet/profil', [Panel\AccountController::class, 'profile'])->name('account.profile');
    Route::get('/ucet/fakturacni-udaje', [Panel\AccountController::class, 'billing'])->name('account.billing');
    Route::put('/ucet/fakturacni-udaje', [Panel\AccountController::class, 'updateBilling'])->name('account.billing.update');
    Route::get('/ucet/zabezpeceni', [Panel\AccountController::class, 'security'])->name('account.security');
    Route::put('/ucet/zmena-hesla', [Panel\AccountController::class, 'updatePassword'])->name('account.password.update');

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

    Route::get('/ai', [Panel\AiController::class, 'index'])->name('ai.index');
    Route::post('/ai', [Panel\AiController::class, 'run'])->name('ai.run');
});

/*
|--------------------------------------------------------------------------
| Partner portal — auth + access-partner permission
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'can:access-partner'])->prefix('partner')->name('partner.')->group(function (): void {
    Route::get('/', [Partner\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/referraly', [Partner\PartnerController::class, 'referrals'])->name('referrals');
    Route::get('/provize', [Partner\PartnerController::class, 'commissions'])->name('commissions');
    Route::get('/vyplaty', [Partner\PartnerController::class, 'payouts'])->name('payouts');
    Route::get('/materialy', [Partner\PartnerController::class, 'assets'])->name('assets');
    Route::get('/nastaveni', [Partner\PartnerController::class, 'profile'])->name('profile');
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
    Route::get('/objednavky/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');

    Route::get('/faktury', [Admin\InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/faktury/{invoice}', [Admin\InvoiceController::class, 'show'])->name('invoices.show');
    Route::post('/faktury/{invoice}/oznacit-zaplacenou', [Admin\InvoiceController::class, 'markPaid'])->name('invoices.mark-paid');
    Route::post('/faktury/{invoice}/danovy-doklad', [Admin\InvoiceController::class, 'issueTaxDocument'])->name('invoices.tax-document');
    Route::post('/faktury/{invoice}/zrusit', [Admin\InvoiceController::class, 'cancel'])->name('invoices.cancel');

    Route::get('/platby', [Admin\PaymentController::class, 'index'])->name('payments.index');
    Route::post('/platby/{payment}/vraceni', [Admin\PaymentController::class, 'refund'])->name('payments.refund');

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
    Route::get('/sluzby/{service}', [Admin\ServiceController::class, 'show'])->name('services.show');
    Route::post('/sluzby/{service}/pozastavit', [Admin\ServiceController::class, 'suspend'])->name('services.suspend');
    Route::post('/sluzby/{service}/obnovit', [Admin\ServiceController::class, 'unsuspend'])->name('services.unsuspend');

    Route::get('/domeny', [Admin\DomainController::class, 'index'])->name('domains.index');

    Route::get('/servery', [Admin\ServerController::class, 'index'])->name('servers.index');
    Route::get('/servery/novy', [Admin\ServerController::class, 'create'])->name('servers.create');
    Route::post('/servery', [Admin\ServerController::class, 'store'])->name('servers.store');
    Route::get('/servery/{server}/upravit', [Admin\ServerController::class, 'edit'])->name('servers.edit');
    Route::put('/servery/{server}', [Admin\ServerController::class, 'update'])->name('servers.update');
    Route::delete('/servery/{server}', [Admin\ServerController::class, 'destroy'])->name('servers.destroy');
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

    Route::get('/metriky', [Admin\MetricsController::class, 'index'])->name('metrics.index');

    Route::get('/system', [Admin\SystemHealthController::class, 'index'])->name('system.index');

    Route::get('/audit', [Admin\AuditLogController::class, 'index'])->name('logs.audit');

    /* ── User management ── */
    /* ── Impersonation ── */
    Route::get('/impersonate/{user}/start', [Admin\ImpersonateController::class, 'start'])->name('impersonate.start');
    Route::get('/impersonate/stop', [Admin\ImpersonateController::class, 'stop'])->name('impersonate.stop');

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
    Route::get('/ukoly', [Admin\PageController::class, 'tasks'])->name('tasks');

    /* ── Calendar ── */
    Route::get('/kalendar', [Admin\PageController::class, 'calendar'])->name('calendar');

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
    Route::get('/odberevatele', [Admin\PageController::class, 'subscribers'])->name('subscribers');

    /* ── Sitemap ── */
    Route::get('/mapa-webu', [Admin\PageController::class, 'sitemap'])->name('sitemap');

    /* ── Sample page ── */
    Route::get('/ukazka', [Admin\PageController::class, 'samplePage'])->name('sample-page');

    /* ── Search ── */
    Route::get('/hledani', [Admin\SearchController::class, 'index'])->name('search');

    Route::get('/nastaveni', [Admin\SettingsController::class, 'index'])->name('settings.index');
    Route::post('/nastaveni', [Admin\SettingsController::class, 'update'])->name('settings.update');

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
        Route::get('/novy', [Admin\PartnerController::class, 'create'])->name('create');
        Route::post('/', [Admin\PartnerController::class, 'store'])->name('store');
        Route::get('/generate-code', [Admin\PartnerController::class, 'generateCode'])->name('generate-code');
        Route::get('/{partner:uuid}', [Admin\PartnerController::class, 'show'])->name('show');
        Route::get('/{partner:uuid}/upravit', [Admin\PartnerController::class, 'edit'])->name('edit');
        Route::put('/{partner:uuid}', [Admin\PartnerController::class, 'update'])->name('update');
        Route::post('/{partner:uuid}/status', [Admin\PartnerController::class, 'changeStatus'])->name('status');
        Route::post('/{partner:uuid}/provize/{commission}/schvalit', [Admin\PartnerController::class, 'approveCommission'])->name('commissions.approve');
        Route::post('/{partner:uuid}/provize/{commission}/zamitnout', [Admin\PartnerController::class, 'rejectCommission'])->name('commissions.reject');
        Route::post('/{partner:uuid}/vyplata', [Admin\PartnerController::class, 'createPayout'])->name('payouts.create');
        Route::post('/{partner:uuid}/vyplata/{payout}/zaplatit', [Admin\PartnerController::class, 'markPayoutPaid'])->name('payouts.paid');
        Route::post('/{partner:uuid}/vyplata/{payout}/zrusit', [Admin\PartnerController::class, 'cancelPayout'])->name('payouts.cancel');
    });
});
