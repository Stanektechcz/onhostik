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

Route::middleware(['auth', 'require-customer-2fa', 'resolve-member-customer', 'restrict-member-billing'])->prefix('panel')->name('panel.')->group(function (): void {
    Route::get('/', [Panel\DashboardController::class, 'index'])->name('dashboard');
    Route::post('/onboarding/dismiss', [Panel\OnboardingController::class, 'dismiss'])->name('onboarding.dismiss');
    Route::post('/oznameni/{announcement}/skryt', [Panel\AnnouncementDismissController::class, 'dismiss'])->name('announcements.dismiss');

    Route::get('/sluzby', [Panel\ServiceController::class, 'index'])->name('services.index');
    Route::get('/sluzby/kalendar.ics', [Panel\ServiceCalendarController::class, 'download'])->name('services.calendar');
    Route::get('/sluzby/firewall', [Panel\ServiceFirewallRuleController::class, 'index'])->name('service-firewall-rules.index');
    Route::post('/sluzby/firewall', [Panel\ServiceFirewallRuleController::class, 'store'])->name('service-firewall-rules.store');
    Route::delete('/sluzby/firewall/{serviceFirewallRule}', [Panel\ServiceFirewallRuleController::class, 'destroy'])->name('service-firewall-rules.destroy');
    Route::get('/sluzby/{service}', [Panel\ServiceController::class, 'show'])->name('services.show');
    /* ── Phase 276: live status + VPS power actions ── */
    Route::get('/sluzby/{service}/zivy-stav', [Panel\ServiceController::class, 'liveStatus'])->name('services.live-status');
    Route::post('/sluzby/{service}/vps-akce', Panel\VpsPowerController::class)->name('services.vps-action');
    /* ── Phase 277: webhosting PHP version ── */
    Route::post('/sluzby/{service}/php-verze', Panel\WebhostingPhpController::class)->name('services.php-version');
    /* ── Phase 278: game server actions ── */
    Route::post('/sluzby/{service}/game-akce', Panel\GameServerActionController::class)->name('services.game-action');
    Route::post('/sluzby/{service}/zaloha', [Panel\ServiceController::class, 'requestBackup'])->name('services.backup');
    Route::post('/sluzby/{service}/recenze', [Panel\ServiceReviewController::class, 'store'])->name('services.review');
    Route::post('/sluzby/{service}/schranky', [Panel\ServiceMailboxController::class, 'store'])->name('services.mailboxes');
    Route::put('/sluzby/{service}/zaloha-plan', [Panel\ServiceController::class, 'updateBackupSchedule'])->name('services.backup-schedule');
    Route::post('/sluzby/{service}/wordpress', [Panel\ServiceController::class, 'installWordpress'])->name('services.wordpress');
    Route::get('/sluzby/{service}/export-pouziti', [Panel\ServiceUsageExportController::class, 'export'])->name('services.usage-export');
    Route::get('/sluzby/{service}/zmenit-plan', [Panel\ServiceController::class, 'changePlan'])->name('services.change-plan');
    Route::get('/sluzby/{service}/zmenit-plan/nahled', [Panel\ServiceController::class, 'changePlanPreview'])->name('services.change-plan-preview');
    Route::post('/sluzby/{service}/zmenit-plan', [Panel\ServiceController::class, 'applyChangePlan'])->name('services.apply-change-plan');
    Route::post('/sluzby/{service}/zrusit', [Panel\ServiceController::class, 'requestCancellation'])->name('services.request-cancel');
    Route::post('/sluzby/{service}/pozastavit', [Panel\ServiceController::class, 'pause'])->name('services.pause');
    Route::post('/sluzby/{service}/obnovit', [Panel\ServiceController::class, 'resume'])->name('services.resume');
    Route::post('/sluzby/{service}/zrusit-na-konci', [Panel\ServiceController::class, 'cancelAtPeriodEnd'])->name('services.cancel-at-period-end');
    Route::put('/sluzby/{service}/poznamka', [Panel\ServiceController::class, 'updateNote'])->name('services.update-note');
    Route::post('/sluzby/{service}/auto-obnova', [Panel\ServiceController::class, 'toggleAutoRenew'])->name('services.toggle-auto-renew');
    Route::patch('/sluzby/{service}/prejmenovat', [Panel\ServiceController::class, 'rename'])->name('services.rename');

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
        /* ── 178: on-demand GDPR art. 28 Data Processing Agreement ── */
        Route::get('/dpa', [Panel\ComplianceController::class, 'downloadDpa'])->name('dpa');
    });

    Route::get('/developer', [Panel\DeveloperPortalController::class, 'index'])->name('developer.index');
    Route::prefix('/developer/oauth')->name('developer.oauth-apps.')->group(function (): void {
        Route::post('/', [Panel\DeveloperPortalController::class, 'storeOAuthApp'])->name('store');
        Route::patch('/{oauthApp}', [Panel\DeveloperPortalController::class, 'regenSecret'])->name('regen');
        Route::delete('/{oauthApp}', [Panel\DeveloperPortalController::class, 'destroyOAuthApp'])->name('destroy');
    });

    // Customer-facing global search across services/invoices/domains/tickets.
    Route::get('/hledat', Panel\GlobalSearchController::class)->name('search');

    // Sub-accounts: switch the active account (for users belonging to several).
    Route::post('/prepnout-ucet/{customer}', [Panel\CustomerSwitchController::class, 'switch'])->name('account.switch');

    // Sub-accounts: the owner manages members + invitations (owner-only enforced
    // in the controller — members legitimately reach the rest of the panel).
    Route::prefix('/ucet/clenove')->name('account.members.')->group(function (): void {
        Route::get('/', [Panel\CustomerMemberController::class, 'index'])->name('index');
        Route::post('/pozvat', [Panel\CustomerMemberController::class, 'invite'])->name('invite');
        Route::delete('/{member}', [Panel\CustomerMemberController::class, 'removeMember'])->name('remove');
        Route::delete('/pozvanky/{invitation}', [Panel\CustomerMemberController::class, 'revokeInvitation'])->name('invitations.revoke');
    });

    // Git deployment — connect a repository, deploy on demand, auto-deploy on push.
    Route::prefix('/sluzby/{service}/git')->name('services.git.')->group(function (): void {
        Route::post('/',        [Panel\ServiceGitController::class, 'store'])->name('store');
        // Deploys run shell commands on the node — throttled so a stuck button
        // can't queue dozens of concurrent checkouts.
        Route::post('/nasadit', [Panel\ServiceGitController::class, 'deploy'])->middleware('throttle:10,1')->name('deploy');
        Route::post('/token',   [Panel\ServiceGitController::class, 'rotateToken'])->name('rotate-token');
        Route::delete('/',      [Panel\ServiceGitController::class, 'destroy'])->name('destroy');
    });

    Route::get('/sluzby/{service}/waf', [Panel\WafController::class, 'index'])->name('waf.index');
    Route::post('/sluzby/{service}/waf', [Panel\WafController::class, 'store'])->name('waf.store');
    Route::delete('/sluzby/{service}/waf/{rule}', [Panel\WafController::class, 'destroy'])->name('waf.destroy');
    Route::patch('/sluzby/{service}/waf/{rule}', [Panel\WafController::class, 'toggle'])->name('waf.toggle');

    Route::get('/domeny', [Panel\DomainController::class, 'index'])->name('domains.index');
    /* ── F90: static route MUST precede the /domeny/{domain} wildcard ── */
    Route::post('/domeny/hromadne-nameservery', [Panel\DomainController::class, 'bulkNameservers'])->name('domains.bulk-nameservers');
    Route::get('/domeny/{domain}', [Panel\DomainController::class, 'show'])->name('domains.show');
    Route::post('/domeny/{domain}/auto-renew', [Panel\DomainController::class, 'toggleAutoRenew'])->name('domains.auto-renew');
    Route::put('/domeny/{domain}/nameservery', [Panel\DomainController::class, 'updateNameservers'])->name('domains.nameservers');
    Route::get('/domeny/{domain}/dns', [Panel\DnsController::class, 'show'])->name('domains.dns');
    Route::post('/domeny/{domain}/dns', [Panel\DnsController::class, 'store'])->name('domains.dns.store');
    /* ── F83: the editor could only ADD records; update/delete were missing ── */
    Route::put('/domeny/{domain}/dns', [Panel\DnsController::class, 'update'])->name('domains.dns.update');
    Route::delete('/domeny/{domain}/dns', [Panel\DnsController::class, 'destroy'])->name('domains.dns.destroy');
    /* ── F91: one-click provider record sets ── */
    Route::post('/domeny/{domain}/dns/sablona', [Panel\DnsController::class, 'applyTemplate'])->name('domains.dns.template');
    /* ── 64: DNSSEC (DS record) management — the client spoke it, the UI didn't ── */
    Route::post('/domeny/{domain}/dnssec', [Panel\DnsController::class, 'dnssecStore'])->name('domains.dnssec.store');
    Route::delete('/domeny/{domain}/dnssec', [Panel\DnsController::class, 'dnssecDestroy'])->name('domains.dnssec.destroy');

    Route::get('/objednavky', [Panel\OrderController::class, 'index'])->name('orders.index');
    Route::get('/objednavky/nova', [Panel\OrderController::class, 'create'])->name('orders.create');
    Route::post('/objednavky', [Panel\OrderController::class, 'store'])->name('orders.store');
    Route::get('/objednavky/{order}', [Panel\OrderController::class, 'show'])->name('orders.show');
    Route::post('/objednavky/{order}/zrusit', [Panel\OrderController::class, 'cancel'])->name('orders.cancel');

    Route::get('/pokladna', [Panel\CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/sleva/validovat', [Panel\DiscountCodeController::class, 'validate'])->name('discount.validate');

    Route::get('/kosik', [Panel\CartController::class, 'index'])->name('cart.index');
    Route::post('/kosik/pridat/{plan}', [Panel\CartController::class, 'add'])->name('cart.add');
    Route::patch('/kosik/mnozstvi/{plan}', [Panel\CartController::class, 'update'])->name('cart.update');
    Route::post('/kosik/objednat', [Panel\CartController::class, 'checkout'])->name('cart.checkout');
    Route::delete('/kosik/odebrat/{plan}', [Panel\CartController::class, 'remove'])->name('cart.remove');
    Route::delete('/kosik', [Panel\CartController::class, 'clear'])->name('cart.clear');

    Route::get('/vernostni-program', [Panel\LoyaltyController::class, 'index'])->name('loyalty.index');
    Route::post('/vernostni-program/uplatnit/{reward}', [Panel\LoyaltyController::class, 'redeem'])->name('loyalty.redeem');
    Route::get('/udrzba', [Panel\ServiceMaintenanceController::class, 'index'])->name('maintenance.index');
    Route::get('/referral', [Panel\ReferralController::class, 'index'])->name('referral.index');

    Route::get('/oblibene', [Panel\WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('/oblibene/pridat/{plan}', [Panel\WishlistController::class, 'add'])->name('wishlist.add');
    Route::delete('/oblibene/odebrat/{plan}', [Panel\WishlistController::class, 'remove'])->name('wishlist.remove');

    Route::post('/fakturace/faktury/stazeni-zip', [Panel\InvoiceBulkDownloadController::class, 'download'])->name('billing.invoices.bulk-zip');
    Route::get('/fakturace/vykaz', [Panel\BillingStatementController::class, 'index'])->name('billing.statement');
    Route::get('/fakturace/vykaz/{year}/{month}', [Panel\BillingStatementController::class, 'download'])->name('billing.statement.download')->where(['year' => '[0-9]{4}', 'month' => '[0-9]{1,2}']);
    Route::get('/fakturace/faktury', [Panel\BillingController::class, 'invoices'])->name('billing.invoices');
    Route::get('/fakturace/faktury/{invoice}', [Panel\BillingController::class, 'invoiceShow'])->name('billing.invoices.show');
    Route::get('/fakturace/faktury/{invoice}/tisk', [Panel\BillingController::class, 'invoicePrint'])->name('billing.invoices.print');
    Route::put('/fakturace/faktury/{invoice}/reference', [Panel\BillingController::class, 'updateReference'])->name('billing.invoices.update-reference');
    Route::post('/fakturace/faktury/{invoice}/namitka', [Panel\InvoiceDisputeController::class, 'store'])->name('billing.invoices.dispute');
    Route::post('/fakturace/faktury/{invoice}/splatkovy-plan', [Panel\InvoiceInstallmentController::class, 'store'])->name('billing.invoices.installment');
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
    Route::get('/fakturace/kredit/auto-dobiti', [Panel\CreditAutoTopupController::class, 'show'])->name('billing.auto-topup.show');
    Route::put('/fakturace/kredit/auto-dobiti', [Panel\CreditAutoTopupController::class, 'update'])->name('billing.auto-topup.update');

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
    Route::post('/tour/dokoncit', [Panel\OnboardingTourController::class, 'complete'])->name('tour.complete');
    /* ── 92: web push subscriptions ── */
    Route::get('/push/klic', [Panel\PushSubscriptionController::class, 'key'])->name('push.key');
    Route::post('/push/prihlasit', [Panel\PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::post('/push/odhlasit', [Panel\PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');
    Route::get('/ucet/export-dat', [Panel\AccountController::class, 'exportData'])->name('account.data-export');
    Route::get('/ucet/fakturacni-udaje', [Panel\AccountController::class, 'billing'])->name('account.billing');
    Route::put('/ucet/fakturacni-udaje', [Panel\AccountController::class, 'updateBilling'])->name('account.billing.update');
    Route::get('/ucet/zabezpeceni', [Panel\AccountController::class, 'security'])->name('account.security');
    Route::get('/ucet/relace', [Panel\SessionController::class, 'index'])->name('account.sessions');
    Route::delete('/ucet/relace/{sessionId}', [Panel\SessionController::class, 'destroy'])->name('account.sessions.destroy');
    Route::delete('/ucet/relace', [Panel\SessionController::class, 'destroyOthers'])->name('account.sessions.destroy-others');
    // Credential change is a high-value target — throttle it like a login
    // (audit 500 #151), so a hijacked session can't brute-force the current
    // password to take the account over.
    Route::put('/ucet/zmena-hesla', [Panel\AccountController::class, 'updatePassword'])
        ->middleware('throttle:6,1')
        ->name('account.password.update');
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
    // Chat endpoints are user-facing and abusable — throttle them.
    Route::post('/ai/chat', [Panel\AiController::class, 'chat'])->middleware('throttle:30,1')->name('ai.chat');
    // Predictive suggestions as the user types (deterministic, no external AI).
    Route::get('/ai/napoveda', [Panel\AiController::class, 'predict'])->middleware('throttle:60,1')->name('ai.predict');
    Route::post('/ai/eskalovat', [Panel\AiController::class, 'escalate'])->middleware('throttle:10,1')->name('ai.escalate');
    Route::post('/ai/priloha', [Panel\AiController::class, 'upload'])->middleware('throttle:20,1')->name('ai.upload');
    Route::get('/ai/priloha/{message}', [Panel\AiController::class, 'attachment'])->name('ai.attachment');
    Route::get('/ai/poll', [Panel\AiController::class, 'poll'])->middleware('throttle:120,1')->name('ai.poll');

    Route::get('/notifikace', [Panel\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifikace/{id}/precist', [Panel\NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifikace/precist-vse', [Panel\NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    /* ── I130: in-app changelog „co je nového“ ── */
    Route::get('/novinky', [Panel\ChangelogController::class, 'index'])->name('changelog.index');

    Route::get('/reseller-program', [Panel\ResellerController::class, 'index'])->name('reseller-program');
    Route::post('/reseller-program', [Panel\ResellerController::class, 'store'])->name('reseller-program.apply');

    /* ── Activity feed (Phase 93) ── */
    Route::get('/aktivity', [Panel\ActivityFeedController::class, 'index'])->name('activity-feed');

    /* ── Outgoing webhooks (Phase 117) ── */
    Route::prefix('/webhooky')->name('webhooks.')->group(function (): void {
        Route::get('/',                              [Panel\WebhookController::class, 'index'])->name('index');
        Route::post('/',                             [Panel\WebhookController::class, 'store'])->name('store');
        Route::post('/{webhook}/prepnout',           [Panel\WebhookController::class, 'toggle'])->name('toggle');
        Route::delete('/{webhook}',                  [Panel\WebhookController::class, 'destroy'])->name('destroy');
        Route::get('/{webhook}/doruceni',            [Panel\WebhookController::class, 'deliveries'])->name('deliveries');
    });

    /* ── Phase 158: 2FA Recovery Codes ── */
    Route::get('/ucet/2fa-zalohy', [Panel\TwoFactorRecoveryController::class, 'show'])->name('account.2fa-recovery.show');
    Route::post('/ucet/2fa-zalohy/regenerovat', [Panel\TwoFactorRecoveryController::class, 'regenerate'])->name('account.2fa-recovery.regenerate');

    /* ── Phase 160: Service Billing Pause (customer) ── */
    Route::post('/sluzby/{service}/pozastavit-fakturaci', [Panel\ServiceBillingPauseController::class, 'store'])->name('services.billing-pause.store');
    Route::delete('/sluzby/{service}/pozastavit-fakturaci', [Panel\ServiceBillingPauseController::class, 'destroy'])->name('services.billing-pause.destroy');

    /* ── Phase 166: Digest Frequency ── */
    Route::get('/ucet/digest-frekvence', [Panel\DigestFrequencyController::class, 'show'])->name('account.digest-frequency.show');
    Route::patch('/ucet/digest-frekvence', [Panel\DigestFrequencyController::class, 'update'])->name('account.digest-frequency.update');

    /* ── Phase 168: Customer API Usage Dashboard ── */
    Route::get('/api/pouziti', [Panel\ApiUsageDashboardController::class, 'index'])->name('api.usage-dashboard');

    /* ── Phase 173: Dark Mode Toggle ── */
    Route::post('/ucet/dark-mode', [Panel\DarkModeController::class, 'toggle'])->name('account.dark-mode.toggle');

    /* ── Phase 175: Service Resource Usage Alert ── */
    Route::patch('/sluzby/{service}/prah-upozorneni', [Panel\ServiceUsageAlertController::class, 'update'])->name('services.usage-alert.update');

    /* ── Phase 177: Referral Reward History ── */
    Route::get('/doporuceni/odmeny', [Panel\ReferralRewardController::class, 'index'])->name('referrals.rewards.index');

    /* ── Phase 179: Login History ── */
    Route::get('/ucet/historie-prihlaseni', [Panel\LoginHistoryController::class, 'index'])->name('account.login-history');

    /* ── Phase 181: Ticket File Attachments ── */
    Route::post('/podpora/{ticket}/prilohy', [Panel\TicketAttachmentController::class, 'store'])->name('support.attachments.store');
    Route::delete('/podpora/prilohy/{attachment}', [Panel\TicketAttachmentController::class, 'destroy'])->name('support.attachments.destroy');

    /* ── Phase 183: Saved Payment Methods ── */
    Route::get('/platebni-metody', [Panel\SavedPaymentMethodController::class, 'index'])->name('payment-methods.index');
    // Money-adjacent writes are throttled (audit 500 #151).
    Route::post('/platebni-metody', [Panel\SavedPaymentMethodController::class, 'store'])
        ->middleware('throttle:20,1')->name('payment-methods.store');
    Route::patch('/platebni-metody/{method}/vychozi', [Panel\SavedPaymentMethodController::class, 'setDefault'])->name('payment-methods.default');
    Route::delete('/platebni-metody/{method}', [Panel\SavedPaymentMethodController::class, 'destroy'])
        ->middleware('throttle:20,1')->name('payment-methods.destroy');

    /* ── Phase 189: Account Deletion Request ── */
    Route::get('/ucet/smazat', [Panel\AccountDeletionController::class, 'create'])->name('account.delete.create');
    // Irreversible — throttled so it can't be script-triggered repeatedly.
    Route::post('/ucet/smazat', [Panel\AccountDeletionController::class, 'store'])
        ->middleware('throttle:5,1')->name('account.delete.store');
    Route::post('/ucet/zadost-smazani', [Panel\AccountDeletionController::class, 'store'])
        ->middleware('throttle:5,1')->name('account.delete-request');

    /* ── Phase 191: Dashboard Widget Config ── */
    Route::get('/widgety', [Panel\DashboardWidgetController::class, 'index'])->name('dashboard-widgets.index');
    Route::post('/widgety', [Panel\DashboardWidgetController::class, 'update'])->name('dashboard-widgets.update');

    /* ── Phase 192: Invoice Dispute History ── */
    Route::get('/fakturace/namitky', [Panel\InvoiceDisputeController::class, 'index'])->name('invoices.disputes.index');

    /* ── Phase 193: Service Auto-Renewal Preferences ── */
    Route::patch('/sluzby/{service}/obnoveni', [Panel\ServiceAutoRenewalController::class, 'update'])->name('services.auto-renewal.update');

    /* ── Phase 196: Ticket Rating ── */
    Route::post('/podpora/{ticket}/hodnoceni', [Panel\TicketRatingController::class, 'store'])->name('support.rating.store');

    /* ── Phase 199: Service Dependency Guard ── */
    Route::get('/sluzby/{service}/zavislosti', [Panel\ServiceDependencyController::class, 'check'])->name('services.dependency.check');

    /* ── Phase 202: Service Uptime Monitoring (customer) ── */
    Route::get('/sluzby/{service}/uptime', [Panel\ServiceUptimeController::class, 'show'])->name('services.uptime.show');

    /* ── Phase 204: Customer Webhook Subscriptions ── */
    Route::get('/webhook-subscriptions', [Panel\WebhookSubscriptionController::class, 'index'])->name('webhook-subscriptions.index');
    Route::post('/webhook-subscriptions', [Panel\WebhookSubscriptionController::class, 'store'])->name('webhook-subscriptions.store');
    Route::delete('/webhook-subscriptions/{subscription}', [Panel\WebhookSubscriptionController::class, 'destroy'])->name('webhook-subscriptions.destroy');

    /* ── Phase 205: KB Article Comments ── */
    Route::post('/znalostni-baze/{article}/komentare', [Panel\KbArticleCommentController::class, 'store'])->name('kb.comments.store');

    /* ── Phase 208: DNS Health Check ── */
    Route::get('/nastroje/dns', [Panel\DnsHealthCheckController::class, 'check'])->name('tools.dns.check');

    /* ── Phase 209: Plan Change Prorate Calculator ── */
    Route::get('/sluzby/{service}/prorate', [Panel\PlanChangeProrateController::class, 'calculate'])->name('services.prorate.calculate');

    /* ── Phase 211: GDPR Data Export ── */
    Route::get('/gdpr-export', [Panel\GdprExportController::class, 'index'])->name('gdpr.export.index');
    Route::post('/gdpr-export', [Panel\GdprExportController::class, 'store'])->name('gdpr.export.store');
    Route::get('/gdpr-export/download/{token}', [Panel\GdprExportController::class, 'download'])->name('gdpr.export.download');

    /* ── Phase 212: Email Delivery Tracking ── */
    Route::get('/e-maily', [Panel\EmailDeliveryController::class, 'index'])->name('email-deliveries.index');

    /* ── Phase 213: Customer Service Notes ── */
    Route::get('/sluzby/{service}/poznamky', [Panel\ServiceCustomerNoteController::class, 'index'])->name('service-notes.index');
    Route::post('/sluzby/{service}/poznamky', [Panel\ServiceCustomerNoteController::class, 'store'])->name('service-notes.store');
    Route::delete('/poznamky/{note}', [Panel\ServiceCustomerNoteController::class, 'destroy'])->name('service-notes.destroy');

    /* ── Phase 215: Service Resource Snapshot History ── */
    Route::get('/sluzby/{service}/snapshoty', [Panel\ServiceResourceSnapshotController::class, 'show'])->name('service-snapshots.show');

    /* ── Phase 217: Customer Referral Statistics Dashboard ── */
    Route::get('/referral-statistiky', [Panel\ReferralStatsDashboardController::class, 'index'])->name('referral-stats.index');

    /* ── Phase 219: Unread Notification Badge ── */
    Route::get('/notifikace/pocet', [Panel\NotificationBadgeController::class, 'count'])->name('notifications.badge.count');

    /* ── Phase 227: Panel Billing Addresses ── */
    Route::get('/fakturacni-adresy', [Panel\BillingAddressController::class, 'index'])->name('billing-addresses.index');
    Route::post('/fakturacni-adresy', [Panel\BillingAddressController::class, 'store'])->name('billing-addresses.store');
    Route::get('/fakturacni-adresy/{billingAddress}/upravit', [Panel\BillingAddressController::class, 'edit'])->name('billing-addresses.edit');
    Route::patch('/fakturacni-adresy/{billingAddress}', [Panel\BillingAddressController::class, 'update'])->name('billing-addresses.update');
    Route::delete('/fakturacni-adresy/{billingAddress}', [Panel\BillingAddressController::class, 'destroy'])->name('billing-addresses.destroy');

    /* ── Phase 231: Panel Usage Alert Configuration ── */
    Route::get('/upozorneni-vyuziti', [Panel\UsageAlertConfigController::class, 'index'])->name('usage-alert-configs.index');
    Route::post('/upozorneni-vyuziti', [Panel\UsageAlertConfigController::class, 'store'])->name('usage-alert-configs.store');
    Route::delete('/upozorneni-vyuziti/{usageAlertConfig}', [Panel\UsageAlertConfigController::class, 'destroy'])->name('usage-alert-configs.destroy');

    /* ── Phase 234: Panel Service Pin Management ── */
    Route::get('/sluzby/{service}/pin', [Panel\ServicePinController::class, 'show'])->name('service-pins.show');
    Route::post('/sluzby/{service}/pin', [Panel\ServicePinController::class, 'store'])->name('service-pins.store');

    /* ── Phase 235: Panel Snapshot Restore Requests ── */
    Route::get('/obnoveni-snapshotu', [Panel\SnapshotRestoreRequestController::class, 'index'])->name('snapshot-restore-requests.index');
    Route::post('/obnoveni-snapshotu', [Panel\SnapshotRestoreRequestController::class, 'store'])->name('snapshot-restore-requests.store');

    /* ── Phase 237: Panel Emergency Contacts ── */
    Route::get('/nouzoze-kontakty', [Panel\EmergencyContactController::class, 'index'])->name('emergency-contacts.index');
    Route::post('/nouzoze-kontakty', [Panel\EmergencyContactController::class, 'store'])->name('emergency-contacts.store');
    Route::delete('/nouzoze-kontakty/{emergencyContact}', [Panel\EmergencyContactController::class, 'destroy'])->name('emergency-contacts.destroy');

    /* ── Phase 238: Panel Voucher Application ── */
    Route::get('/voucher', [Panel\VoucherApplicationController::class, 'index'])->name('voucher-application.index');
    Route::post('/voucher', [Panel\VoucherApplicationController::class, 'store'])->name('voucher-application.store');

    /* ── Phase 239: Panel Service Upgrade Requests ── */
    Route::get('/zadosti-o-upgrade', [Panel\ServiceUpgradeRequestController::class, 'index'])->name('service-upgrade-requests.index');
    Route::post('/zadosti-o-upgrade', [Panel\ServiceUpgradeRequestController::class, 'store'])->name('service-upgrade-requests.store');
    Route::get('/zadosti-o-upgrade/{serviceUpgradeRequest}', [Panel\ServiceUpgradeRequestController::class, 'show'])->name('service-upgrade-requests.show');

    /* ── Phase 245: Panel Domain Transfer Requests ── */
    Route::get('/prevody-domen', [Panel\DomainTransferRequestController::class, 'index'])->name('domain-transfer-requests.index');
    Route::post('/prevody-domen', [Panel\DomainTransferRequestController::class, 'store'])->name('domain-transfer-requests.store');

    /* ── Phase 246: Panel Service Changelog ── */
    Route::get('/changelog-sluzby', [Panel\ServiceChangelogController::class, 'index'])->name('service-changelogs.index');

    /* ── Phase 247: Panel Chargeback History ── */
    Route::get('/chargeback', [Panel\ChargebackController::class, 'index'])->name('chargebacks.index');

    /* ── Phase 248: Panel Price Change Notifications ── */
    Route::get('/oznameni-zmen-cen', [Panel\PriceChangeNotificationController::class, 'index'])->name('price-change-notifications.index');

    /* ── Phase 251: Panel Payment Retry Status ── */
    Route::get('/opakovani-plateb', [Panel\PaymentRetryStatusController::class, 'index'])->name('payment-retry-status.index');

    /* ── Phase 253: Panel Service Migration Status ── */
    Route::get('/migrace-sluzby', [Panel\ServiceMigrationStatusController::class, 'index'])->name('service-migration-status.index');

    /* ── Phase 256: Panel Report Schedules ── */
    Route::get('/planovane-reporty', [Panel\ReportScheduleController::class, 'index'])->name('report-schedules.index');

    /* ── Phase 263: Panel Customer Communication Log ── */
    Route::get('/komunikace', [Panel\CustomerCommunicationLogController::class, 'index'])->name('customer-communication-logs.index');

    /* ── Phase 264: Panel Service Health Incidents ── */
    Route::get('/incidenty', [Panel\ServiceHealthIncidentController::class, 'index'])->name('service-health-incidents.index');

    /* ── Phase 265: Panel Maintenance Windows ──
       URL must differ from Phase 66's /udrzba (same URI would silently
       drop the earlier route name from the collection). */
    Route::get('/okna-udrzby', [Panel\MaintenanceWindowController::class, 'index'])->name('maintenance-windows.index');

    /* ── Phase 266: Panel Customer Onboarding Steps ── */
    Route::get('/onboarding-kroky', [Panel\CustomerOnboardingStepController::class, 'index'])->name('customer-onboarding-steps.index');

    /* ── Phase 267: Panel Reseller Payout Requests ── */
    Route::get('/vyplata-provize', [Panel\ResellerPayoutRequestController::class, 'index'])->name('reseller-payout-requests.index');
    Route::post('/vyplata-provize', [Panel\ResellerPayoutRequestController::class, 'store'])->name('reseller-payout-requests.store');

    /* ── Phase 270: Panel Service Backup Logs ── */
    Route::get('/zalohy', [Panel\ServiceBackupLogController::class, 'index'])->name('service-backup-logs.index');

    /* ── Phase 271: Panel Portal Announcements ── */
    Route::get('/oznameni-portalu', [Panel\PortalAnnouncementController::class, 'index'])->name('portal-announcements.index');
});

/* ── NPS Survey (Phase 95) — public, token-authenticated ── */
Route::get('/nps/{token}', [Panel\NpsSurveyController::class, 'show'])->name('nps.show');
Route::post('/nps/{token}', [Panel\NpsSurveyController::class, 'submit'])->name('nps.submit');

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
    Route::post('/nastaveni/vyplaty', [Partner\PartnerController::class, 'updatePayout'])->name('profile.payout');
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

/*
| Admin account security — deliberately OUTSIDE require-admin-2fa: this is
| where an admin without confirmed 2FA is sent to set it up, so it must
| stay reachable (same controller/view as the shared account page, but the
| admin never leaves the /admin URL space).
*/
Route::middleware(['auth', 'can:access-admin', 'admin-ip-allowlist'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/ucet/zabezpeceni', [Panel\AccountController::class, 'security'])->name('account.security');
});

Route::middleware(['auth', 'can:access-admin', 'require-admin-2fa', 'admin-ip-allowlist'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

    /* ── Global search — JSON quick-search for the header autocomplete (Phase 96) ── */
    Route::get('/hledat', Admin\GlobalSearchController::class)->name('search.quick');

    Route::get('/zakaznici', [Admin\CustomerController::class, 'index'])->name('customers.index');
    Route::get('/zakaznici/export', [Admin\CustomerController::class, 'export'])->name('customers.export');
    Route::get('/zakaznici/segmentace', [Admin\CustomerSegmentationController::class, 'index'])->name('customers.segmentation');
    Route::get('/zakaznici/ltv-report', [Admin\CustomerLtvReportController::class, 'index'])->name('customers.ltv-report');
    Route::get('/zakaznici/import', [Admin\CustomerImportController::class, 'create'])->name('customers.import');
    Route::post('/zakaznici/import', [Admin\CustomerImportController::class, 'store'])->name('customers.import.store');
    Route::get('/zakaznici/{customer}', [Admin\CustomerController::class, 'show'])->name('customers.show');
    Route::post('/zakaznici/{customer}/kredit', [Admin\CustomerController::class, 'adjustCredit'])->name('customers.credit');
    Route::put('/zakaznici/{customer}/poznamky', [Admin\CustomerController::class, 'updateNotes'])->name('customers.notes');
    Route::patch('/zakaznici/{customer}/prefkontakt', [Admin\CustomerController::class, 'updatePreferredContact'])->name('customers.preferred-contact');
    Route::patch('/zakaznici/{customer}', [Admin\CustomerController::class, 'update'])->name('customers.update');
    Route::post('/zakaznici/{customer}/prepnout-aktivni', [Admin\CustomerController::class, 'toggleActive'])->name('customers.toggle-active');
    Route::post('/zakaznici/{customer}/interni-poznamky', [Admin\CustomerInternalNoteController::class, 'store'])->name('customers.internal-notes.store');
    Route::delete('/zakaznici/{customer}/interni-poznamky/{note}', [Admin\CustomerInternalNoteController::class, 'destroy'])->name('customers.internal-notes.destroy');
    Route::post('/zakaznici/{customer}/interni-poznamky/{note}/pripnout', [Admin\CustomerInternalNoteController::class, 'pin'])->name('customers.internal-notes.pin');
    Route::post('/zakaznici/{customer}/stitky', [Admin\CustomerTagController::class, 'assign'])->name('customers.tags.assign');

    /* ── 75: unified internal notes on any entity (order/invoice/…) ── */
    Route::post('/poznamky/{type}/{id}', [Admin\EntityNoteController::class, 'store'])->name('entity-notes.store');
    Route::delete('/poznamky/{type}/{id}/{note}', [Admin\EntityNoteController::class, 'destroy'])->name('entity-notes.destroy');
    Route::post('/poznamky/{type}/{id}/{note}/pripnout', [Admin\EntityNoteController::class, 'pin'])->name('entity-notes.pin');

    /* ── 74: four-eyes approval review queue ── */
    Route::get('/schvalovani', [Admin\ApprovalRequestController::class, 'index'])->name('approvals.index');
    Route::post('/schvalovani/{approvalRequest}/schvalit', [Admin\ApprovalRequestController::class, 'approve'])->name('approvals.approve');
    Route::post('/schvalovani/{approvalRequest}/zamitnout', [Admin\ApprovalRequestController::class, 'reject'])->name('approvals.reject');
    Route::prefix('/zakaznici/{customer}/kontakty')->name('customer-contacts.')->group(function (): void {
        Route::get('/', [Admin\CustomerContactController::class, 'index'])->name('index');
        Route::post('/', [Admin\CustomerContactController::class, 'store'])->name('store');
        Route::put('/{contact}', [Admin\CustomerContactController::class, 'update'])->name('update');
        Route::delete('/{contact}', [Admin\CustomerContactController::class, 'destroy'])->name('destroy');
    });
    Route::delete('/zakaznici/{customer}/stitky/{customerTag}', [Admin\CustomerTagController::class, 'detach'])->name('customer-tags.detach');
    Route::get('/zakaznici/{customer}/login-historie', [Admin\CustomerLoginHistoryController::class, 'show'])->name('customer-login-history.show');

    Route::prefix('/stitky-zakazniku')->name('customer-tags.')->group(function (): void {
        Route::get('/', [Admin\CustomerTagController::class, 'index'])->name('index');
        Route::post('/', [Admin\CustomerTagController::class, 'store'])->name('store');
        Route::put('/{customerTag}', [Admin\CustomerTagController::class, 'update'])->name('update');
        Route::delete('/{customerTag}', [Admin\CustomerTagController::class, 'destroy'])->name('destroy');
    });

    Route::get('/objednavky', [Admin\OrderController::class, 'index'])->name('orders.index');
    Route::get('/objednavky/export', [Admin\OrderController::class, 'export'])->name('orders.export');
    Route::get('/objednavky/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
    Route::post('/objednavky/{order}/akceptovat', [Admin\OrderController::class, 'accept'])->name('orders.accept');
    Route::post('/objednavky/{order}/zrusit', [Admin\OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('/objednavky/{order}/zridit', [Admin\OrderController::class, 'provision'])->name('orders.provision');
    Route::patch('/objednavky/{order}', [Admin\OrderController::class, 'update'])->name('orders.update');

    Route::get('/faktury', [Admin\InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/faktury/export', [Admin\InvoiceController::class, 'export'])->name('invoices.export');
    Route::get('/faktury/namitky', [Admin\InvoiceDisputeController::class, 'index'])->name('invoice-disputes.index');
    Route::patch('/faktury/namitky/{dispute}', [Admin\InvoiceDisputeController::class, 'resolve'])->name('invoice-disputes.resolve');
    Route::get('/faktury/fronta-neuspesnych-plateb', [Admin\FailedPaymentQueueController::class, 'index'])->name('failed-payment-queue.index');
    Route::post('/faktury/fronta-neuspesnych-plateb/{invoice}', [Admin\FailedPaymentQueueController::class, 'resend'])->name('failed-payment-queue.resend');
    Route::post('/faktury/hromadna-upominka', [Admin\InvoiceController::class, 'sendBulkPaymentReminders'])->name('invoices.bulk-payment-reminder');
    Route::post('/faktury/hromadne-zaplaceni', [Admin\InvoiceController::class, 'batchMarkPaid'])->name('invoices.batch-mark-paid');
    Route::get('/faktury/ad-hoc/vytvorit', [Admin\InvoiceController::class, 'adhocCreate'])->name('invoices.adhoc-create');
    Route::post('/faktury/ad-hoc', [Admin\InvoiceController::class, 'adhocStore'])->name('invoices.adhoc-store');
    /* ── Phase 154: Invoice Installments (static, BEFORE {invoice} wildcard) ── */
    Route::get('/faktury/splatkove-plany', [Admin\InvoiceInstallmentController::class, 'index'])->name('invoice-installments.index');

    /* ── Phase 155: Proforma Batch (static, BEFORE {invoice} wildcard) ── */
    Route::get('/faktury/proformy', [Admin\ProformaBatchController::class, 'index'])->name('proforma-batch.index');
    Route::patch('/faktury/proformy/{invoice}/konvertovat', [Admin\ProformaBatchController::class, 'convert'])->name('proforma-batch.convert');

    Route::get('/faktury/{invoice}', [Admin\InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/faktury/{invoice}/pdf', [Admin\InvoiceController::class, 'downloadPdf'])->name('invoices.pdf');
    Route::post('/faktury/{invoice}/oznacit-zaplacenou', [Admin\InvoiceController::class, 'markPaid'])->name('invoices.mark-paid');
    Route::post('/faktury/{invoice}/danovy-doklad', [Admin\InvoiceController::class, 'issueTaxDocument'])->name('invoices.tax-document');
    Route::post('/faktury/{invoice}/dobropis', [Admin\InvoiceController::class, 'issueCreditNote'])->name('invoices.credit-note');
    Route::post('/faktury/{invoice}/zrusit', [Admin\InvoiceController::class, 'cancel'])->name('invoices.cancel');
    Route::post('/faktury/{invoice}/odeslat-email', [Admin\InvoiceController::class, 'resendEmail'])->name('invoices.resend-email');
    Route::post('/faktury/{invoice}/upominka-platby', [Admin\InvoiceController::class, 'sendPaymentReminder'])->name('invoices.payment-reminder');
    Route::post('/faktury/{invoice}/pozastavit-upominkani', [Admin\InvoiceController::class, 'pauseDunning'])->name('invoices.pause-dunning');
    Route::post('/faktury/{invoice}/obnovit-upominkani', [Admin\InvoiceController::class, 'resumeDunning'])->name('invoices.resume-dunning');

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
    Route::get('/sluzby/dashboard-obnov', [Admin\ServiceRenewalDashboardController::class, 'index'])->name('services.renewal-dashboard');
    Route::get('/sluzby/export', [Admin\ServiceController::class, 'export'])->name('services.export');
    Route::post('/sluzby/hromadne-pozastavit', [Admin\ServiceController::class, 'batchSuspend'])->name('services.batch-suspend');
    Route::post('/sluzby/hromadne-reaktivovat', [Admin\ServiceController::class, 'batchUnsuspend'])->name('services.batch-unsuspend');
    Route::get('/sluzby/vytvorit', [Admin\ServiceController::class, 'create'])->name('services.create');
    Route::post('/sluzby', [Admin\ServiceController::class, 'store'])->name('services.store');
    Route::get('/sluzby/stitky', [Admin\ServiceTagController::class, 'index'])->name('services.tags.index');
    Route::post('/sluzby/stitky', [Admin\ServiceTagController::class, 'store'])->name('services.tags.store');
    Route::delete('/sluzby/stitky/{serviceTag}', [Admin\ServiceTagController::class, 'destroy'])->name('services.tags.destroy');
    Route::get('/sluzby/{service}/smlouva', [Admin\ServiceContractController::class, 'download'])->name('services.contract');

    /* ── Phase 160: Service Billing Pause index (static, BEFORE {service} wildcard) ── */
    Route::get('/sluzby/pozastaveni-fakturace', [Admin\ServiceBillingPauseController::class, 'index'])->name('service-billing-pause.index');

    Route::get('/sluzby/{service}', [Admin\ServiceController::class, 'show'])->name('services.show');
    Route::post('/sluzby/{service}/pozastavit', [Admin\ServiceController::class, 'suspend'])->name('services.suspend');
    Route::post('/sluzby/{service}/obnovit', [Admin\ServiceController::class, 'unsuspend'])->name('services.unsuspend');
    Route::put('/sluzby/{service}/popis', [Admin\ServiceController::class, 'updateLabel'])->name('services.update-label');
    Route::put('/sluzby/{service}/splatnost', [Admin\ServiceController::class, 'adjustDueDate'])->name('services.adjust-due-date');
    Route::post('/sluzby/{service}/obnova', [Admin\ServiceController::class, 'manualRenewal'])->name('services.manual-renewal');
    Route::post('/sluzby/{service}/stitky', [Admin\ServiceTagController::class, 'assign'])->name('services.tags.assign');
    Route::delete('/sluzby/{service}/stitky/{serviceTag}', [Admin\ServiceTagController::class, 'detach'])->name('services.tags.detach');
    /* ── Phase 272: Service 360° — read-only live status from backend panel ── */
    Route::get('/sluzby/{service}/zivy-stav', Admin\ServiceLiveStatusController::class)->name('services.live-status');
    /* ── Read-only reconciliation with the backend panel (drift detection) ── */
    Route::post('/sluzby/{service}/synchronizovat', [Admin\ServiceController::class, 'syncRemote'])->name('services.sync-remote');
    Route::get('/sluzby/{service}/nahled-zrizeni', [Admin\ServiceController::class, 'provisionPreview'])->name('services.provision-preview');
    Route::post('/sluzby/{service}/zridit-znovu', [Admin\ServiceController::class, 'reprovision'])->name('services.reprovision');
    /* ── Full aaPanel-parity configuration (databases, FTP, cron, SSL, quota) ── */
    Route::post('/sluzby/{service}/konfigurace', [Admin\ServiceConfigController::class, 'store'])->name('services.config');

    /* ── G95: full line-item editing on an order ── */
    Route::post('/objednavky/{order}/polozky', [Admin\OrderItemController::class, 'store'])->name('orders.items.store');
    Route::put('/objednavky/{order}/polozky/{item}', [Admin\OrderItemController::class, 'update'])->name('orders.items.update');
    Route::delete('/objednavky/{order}/polozky/{item}', [Admin\OrderItemController::class, 'destroy'])->name('orders.items.destroy');

    /* ── G96: roles and permissions editable from the UI ── */
    Route::post('/role-opravneni/role', [Admin\RolePermissionController::class, 'store'])->name('roles.store');
    Route::delete('/role-opravneni/role/{role}', [Admin\RolePermissionController::class, 'destroy'])->name('roles.destroy');
    Route::post('/role-opravneni/role/{role}/opravneni', [Admin\RolePermissionController::class, 'syncPermissions'])->name('roles.permissions');
    Route::post('/role-opravneni/opravneni', [Admin\RolePermissionController::class, 'storePermission'])->name('permissions.store');
    Route::post('/role-opravneni/uzivatel/{user}', [Admin\RolePermissionController::class, 'assign'])->name('roles.assign');

    /* ── G99: bulk actions over selected customers ── */
    Route::post('/zakaznici/hromadna-akce', Admin\CustomerBulkActionController::class)->name('customers.bulk-action');

    /* ── G97: admin-side account recovery for a customer ── */
    Route::post('/uzivatele/{user}/obnova-hesla', [Admin\CustomerSecurityController::class, 'sendPasswordReset'])->name('users.password-reset');
    Route::post('/uzivatele/{user}/zrusit-2fa', [Admin\CustomerSecurityController::class, 'resetTwoFactor'])->name('users.reset-2fa');
    Route::post('/uzivatele/{user}/odhlasit-vse', [Admin\CustomerSecurityController::class, 'logoutEverywhere'])->name('users.logout-everywhere');

    /* ── Snapshot restore: customer requests, admin approves (overwrites data) ── */
    Route::get('/pozadavky-obnova', [Admin\SnapshotRestoreRequestController::class, 'index'])->name('snapshot-restore-requests.index');
    Route::post('/pozadavky-obnova/{snapshotRestoreRequest}/schvalit', [Admin\SnapshotRestoreRequestController::class, 'approve'])->name('snapshot-restore-requests.approve');
    Route::post('/pozadavky-obnova/{snapshotRestoreRequest}/zamitnout', [Admin\SnapshotRestoreRequestController::class, 'reject'])->name('snapshot-restore-requests.reject');

    /* ── Queue health: a stalled worker means paid orders never provision ── */
    Route::get('/fronta-uloh', [Admin\QueueController::class, 'index'])->name('queue.index');
    Route::post('/fronta-uloh/opakovat', [Admin\QueueController::class, 'retry'])->name('queue.retry');
    Route::post('/fronta-uloh/smazat', [Admin\QueueController::class, 'forget'])->name('queue.forget');
    /* ── Phase 276: admin VPS power actions ── */
    Route::post('/sluzby/{service}/vps-akce', Admin\ServiceVpsPowerController::class)->name('services.vps-action');
    /* ── Phase 277: admin webhosting PHP version ── */
    Route::post('/sluzby/{service}/php-verze', Admin\ServicePhpVersionController::class)->name('services.php-version');
    /* ── Phase 278: admin game server actions ── */
    Route::post('/sluzby/{service}/game-akce', Admin\ServiceGameActionController::class)->name('services.game-action');

    /* ── E53: přeřazení služby na jiný server (vyprázdnění plného serveru) ── */
    Route::post('/sluzby/{service}/migrace', Admin\ServiceMigrateController::class)->name('services.migrate');

    /* ── L120: stažení dokončeného dávkového exportu faktur ──
       Vlastní název, aby nekolidoval s admin.exports.* (finanční exporty). */
    Route::get('/faktury/batch-export/{export}/stahnout', [Admin\InvoiceBatchExportController::class, 'download'])
        ->name('invoice-batch.download');

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
    Route::post('/servery/{server}/vyprazdnit', [Admin\ServerController::class, 'drain'])->name('servers.drain');

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

    // Service resource limits
    Route::prefix('/zdroje-sluzeb')->name('service-resources.')->group(function (): void {
        Route::get('/', [Admin\ServiceResourceController::class, 'index'])->name('index');
        Route::put('/{service}/limity', [Admin\ServiceResourceController::class, 'updateLimits'])->name('update-limits');
        Route::post('/{service}/vyuziti', [Admin\ServiceResourceController::class, 'recordUsage'])->name('record-usage');
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

    Route::get('/chat', [Admin\ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/{conversation}', [Admin\ChatController::class, 'show'])->name('chat.show');
    Route::post('/chat/{conversation}/odpoved', [Admin\ChatController::class, 'reply'])->name('chat.reply');
    Route::post('/chat/{conversation}/ticket', [Admin\ChatController::class, 'toTicket'])->name('chat.to-ticket');
    Route::post('/chat/{conversation}/uzavrit', [Admin\ChatController::class, 'close'])->name('chat.close');

    Route::get('/podpora', [Admin\SupportController::class, 'index'])->name('support.index');
    Route::get('/podpora/sla-monitor', [Admin\SupportController::class, 'slaMonitor'])->name('support.sla-monitor');
    Route::get('/podpora/csat', [Admin\CsatController::class, 'index'])->name('support.csat');
    Route::get('/podpora/nps', [Admin\NpsController::class, 'index'])->name('support.nps');
    Route::get('/podpora/makra', [Admin\TicketMacroController::class, 'index'])->name('support.macros.index');
    Route::post('/podpora/makra', [Admin\TicketMacroController::class, 'store'])->name('support.macros.store');
    Route::put('/podpora/makra/{macro}', [Admin\TicketMacroController::class, 'update'])->name('support.macros.update');
    Route::delete('/podpora/makra/{macro}', [Admin\TicketMacroController::class, 'destroy'])->name('support.macros.destroy');
    Route::get('/podpora/{ticket}', [Admin\SupportController::class, 'show'])->name('support.show');
    Route::post('/podpora/{ticket}/odpoved', [Admin\SupportController::class, 'reply'])->name('support.reply');
    Route::put('/podpora/{ticket}', [Admin\SupportController::class, 'update'])->name('support.update');
    Route::post('/podpora/{ticket}/sla', [Admin\SupportController::class, 'setSla'])->name('support.sla');
    Route::post('/podpora/{ticket}/kb-navrh', [Admin\SupportController::class, 'generateKbDraft'])->name('support.kb-draft');
    Route::post('/podpora/{ticket}/ai-analyse', [Admin\SupportController::class, 'analyseTicket'])->name('support.ai-analyse');

    Route::get('/ai', [Admin\AiController::class, 'index'])->name('ai.index');
    Route::post('/ai', [Admin\AiController::class, 'run'])->name('ai.run');
    Route::post('/ai/schvaleni/{approval}', [Admin\AiController::class, 'review'])->name('ai.review');

    Route::get('/winback-kampane', [Admin\WinbackCampaignController::class, 'index'])->name('winback-campaigns.index');
    Route::post('/winback-kampane', [Admin\WinbackCampaignController::class, 'store'])->name('winback-campaigns.store');
    Route::post('/winback-kampane/{winbackCampaign}/odeslat', [Admin\WinbackCampaignController::class, 'send'])->name('winback-campaigns.send');

    Route::get('/metriky', [Admin\MetricsController::class, 'index'])->name('metrics.index');
    Route::get('/metriky/mrr-trend', [Admin\MrrTrendController::class, 'index'])->name('metrics.mrr-trend');
    Route::get('/metriky/arpu', [Admin\ArpuController::class, 'index'])->name('metrics.arpu');
    Route::get('/metriky/aktivni-uzivatele', [Admin\ActiveUsersController::class, 'index'])->name('metrics.active-users');

    // Report builder
    Route::prefix('/reporty')->name('reports.')->group(function (): void {
        Route::get('/', [Admin\ReportController::class, 'index'])->name('index');
        Route::get('/stahnout', [Admin\ReportController::class, 'download'])->name('download');
    });
    Route::get('/bi', [Admin\BiController::class, 'index'])->name('bi.index');
    Route::get('/bi-v2', [Admin\BiV2Controller::class, 'index'])->name('bi-v2.index');
    Route::get('/api-usage', [Admin\ApiUsageController::class, 'index'])->name('api-usage.index');

    /* ── Onboarding stats (Phase 84) ── */
    Route::get('/onboarding-stats', [Admin\OnboardingStatsController::class, 'index'])->name('onboarding-stats.index');

    /* ── Upcoming renewals (Phase 85) ── */
    Route::get('/nadchazejici-obnovy', [Admin\UpcomingRenewalController::class, 'index'])->name('upcoming-renewals.index');

    /* ── Expiring credits (Phase 86) ── */
    Route::get('/expirujici-kredity', [Admin\ExpiringCreditController::class, 'index'])->name('expiring-credits.index');

    /* ── Admin audit trail (Phase 89) ── */
    Route::get('/audit-trail', [Admin\AdminAuditTrailController::class, 'index'])->name('audit-trail.index');

    /* ── Security settings / 2FA enforcement (Phase 90) ── */
    Route::prefix('/security-settings')->name('security-settings.')->group(function (): void {
        Route::get('/', [Admin\SecuritySettingsController::class, 'index'])->name('index');
        Route::put('/', [Admin\SecuritySettingsController::class, 'update'])->name('update');
    });

    /* ── Service notes & labels (Phase 92) ── */
    Route::get('/sluzby/{service}/poznamky', [Admin\ServiceNotesController::class, 'edit'])->name('service-notes.edit');
    Route::put('/sluzby/{service}/poznamky', [Admin\ServiceNotesController::class, 'update'])->name('service-notes.update');

    Route::prefix('/stitky-sluzeb')->name('service-labels.')->group(function (): void {
        Route::get('/', [Admin\ServiceNotesController::class, 'labelsIndex'])->name('index');
        Route::post('/', [Admin\ServiceNotesController::class, 'labelsStore'])->name('store');
        Route::delete('/{serviceLabel}', [Admin\ServiceNotesController::class, 'labelsDestroy'])->name('destroy');
    });

    /* ── API Rate Limit Tracking ── */
    Route::prefix('/api-rate-limits')->name('api-rate-limits.')->group(function (): void {
        Route::get('/', [Admin\ApiRateLimitController::class, 'index'])->name('index');
        Route::post('/', [Admin\ApiRateLimitController::class, 'store'])->name('store');
        Route::delete('/{apiRateLimit}', [Admin\ApiRateLimitController::class, 'destroy'])->name('destroy');
    });

    // Bulk operations
    Route::prefix('/hromadne')->name('bulk.')->group(function (): void {
        Route::get('/',                                  [Admin\BulkController::class, 'index'])->name('index');
        Route::post('/sluzby/prodlouzit',               [Admin\BulkController::class, 'serviceExtendDueDate'])->name('service-extend');
        Route::post('/sluzby/ukoncit',                  [Admin\BulkController::class, 'serviceTerminate'])->name('service-terminate');
        Route::post('/sluzby/export',                   [Admin\BulkController::class, 'serviceExport'])->name('service-export');
        Route::post('/sluzby/pozastavit',               [Admin\BulkController::class, 'serviceSuspend'])->name('service-suspend');
        Route::post('/sluzby/obnovit',                  [Admin\BulkController::class, 'serviceResume'])->name('service-resume');
        Route::post('/sluzby/auto-obnova',              [Admin\BulkController::class, 'serviceSetAutoRenew'])->name('service-auto-renew');
        Route::post('/sluzby/upozorneni-obnova',        [Admin\BulkExpiryNotificationController::class, 'send'])->name('service-expiry-notify');
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

    // Loyalty reward catalog — redeemable rewards (admin)
    Route::prefix('/vernostni-odmeny')->name('loyalty-rewards.')->group(function (): void {
        Route::get('/',              [Admin\LoyaltyRewardController::class, 'index'])->name('index');
        Route::post('/',             [Admin\LoyaltyRewardController::class, 'store'])->name('store');
        Route::put('/{reward}',      [Admin\LoyaltyRewardController::class, 'update'])->name('update');
        Route::delete('/{reward}',   [Admin\LoyaltyRewardController::class, 'destroy'])->name('destroy');
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

    // Curated chatbot answers — extend the knowledge base without a deploy.
    Route::prefix('/odpovedi-chatu')->name('chat-answers.')->group(function (): void {
        Route::get('/',                [Admin\ChatAnswerController::class, 'index'])->name('index');
        Route::post('/',               [Admin\ChatAnswerController::class, 'store'])->name('store');
        Route::put('/{chatAnswer}',    [Admin\ChatAnswerController::class, 'update'])->name('update');
        Route::delete('/{chatAnswer}', [Admin\ChatAnswerController::class, 'destroy'])->name('destroy');
    });

    // Feature flags — enable functionality without a deploy (audit 500 #383).
    Route::prefix('/feature-flags')->name('feature-flags.')->group(function (): void {
        Route::get('/',                 [Admin\FeatureFlagController::class, 'index'])->name('index');
        Route::post('/',                [Admin\FeatureFlagController::class, 'store'])->name('store');
        Route::put('/{featureFlag}',    [Admin\FeatureFlagController::class, 'update'])->name('update');
        Route::delete('/{featureFlag}', [Admin\FeatureFlagController::class, 'destroy'])->name('destroy');
    });

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
    Route::get('/slevy/{code}', [Admin\DiscountCodeController::class, 'show'])->name('discount-codes.show');
    Route::post('/slevy/{code}/toggle', [Admin\DiscountCodeController::class, 'toggle'])->name('discount-codes.toggle');
    Route::delete('/slevy/{code}', [Admin\DiscountCodeController::class, 'destroy'])->name('discount-codes.destroy');

    /* ── Reviews (service reviews module) ── */
    Route::get('/recenze', [Admin\ServiceReviewController::class, 'index'])->name('reviews');
    Route::post('/recenze/{review}/schvalit', [Admin\ServiceReviewController::class, 'approve'])->name('reviews.approve');
    Route::post('/recenze/{review}/zamitnout', [Admin\ServiceReviewController::class, 'reject'])->name('reviews.reject');

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
    Route::get('/hledani/autocomplete', [Admin\SearchController::class, 'autocomplete'])->name('search.autocomplete');

    // Invoice custom field definitions
    Route::prefix('/faktura-pole')->name('invoice-fields.')->group(function (): void {
        Route::get('/', [Admin\InvoiceFieldController::class, 'index'])->name('index');
        Route::post('/', [Admin\InvoiceFieldController::class, 'store'])->name('store');
        Route::put('/{invoiceField}', [Admin\InvoiceFieldController::class, 'update'])->name('update');
        Route::delete('/{invoiceField}', [Admin\InvoiceFieldController::class, 'destroy'])->name('destroy');
        Route::post('/faktury/{invoice}/hodnoty', [Admin\InvoiceFieldController::class, 'saveValues'])->name('save-values');
    });

    Route::get('/nastaveni', [Admin\SettingsController::class, 'index'])->name('settings.index');
    Route::post('/nastaveni', [Admin\SettingsController::class, 'update'])->name('settings.update');

    /* ── Helpdesk webhooks ── */
    Route::prefix('/helpdesk-webhooky')->name('helpdesk-webhooks.')->group(function (): void {
        Route::get('/', [Admin\HelpdeskWebhookController::class, 'index'])->name('index');
        Route::post('/', [Admin\HelpdeskWebhookController::class, 'store'])->name('store');
        Route::put('/{helpdeskWebhook}', [Admin\HelpdeskWebhookController::class, 'update'])->name('update');
        Route::delete('/{helpdeskWebhook}', [Admin\HelpdeskWebhookController::class, 'destroy'])->name('destroy');
        Route::post('/{helpdeskWebhook}/regenerate-secret', [Admin\HelpdeskWebhookController::class, 'regenerateSecret'])->name('regenerate');
    });

    /* ── Maintenance banners (Phase 81) ── */
    Route::prefix('/udrzba-bannery')->name('maintenance-banners.')->group(function (): void {
        Route::get('/', [Admin\MaintenanceController::class, 'index'])->name('index');
        Route::post('/', [Admin\MaintenanceController::class, 'store'])->name('store');
        Route::put('/{maintenanceWindow}', [Admin\MaintenanceController::class, 'update'])->name('update');
        Route::delete('/{maintenanceWindow}', [Admin\MaintenanceController::class, 'destroy'])->name('destroy');
    });

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
    Route::get('/znalostni-baze/analytika', [Admin\KbAnalyticsController::class, 'index'])->name('kb.analytics');

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

    // System Announcements (Phase 72)
    Route::prefix('/oznameni')->name('announcements.')->group(function (): void {
        Route::get('/',                             [Admin\SystemAnnouncementController::class, 'index'])->name('index');
        Route::get('/nove',                         [Admin\SystemAnnouncementController::class, 'create'])->name('create');
        Route::post('/',                            [Admin\SystemAnnouncementController::class, 'store'])->name('store');
        Route::get('/{announcement}/upravit',       [Admin\SystemAnnouncementController::class, 'edit'])->name('edit');
        Route::put('/{announcement}',               [Admin\SystemAnnouncementController::class, 'update'])->name('update');
        Route::post('/{announcement}/odeslat',      [Admin\SystemAnnouncementController::class, 'publish'])->name('publish');
        Route::delete('/{announcement}',            [Admin\SystemAnnouncementController::class, 'destroy'])->name('destroy');
    });

    // Service Lifecycle (Phase 71)
    Route::prefix('/zivotni-cyklus')->name('lifecycle.')->group(function (): void {
        Route::get('/',                           [Admin\ServiceLifecycleController::class, 'index'])->name('index');
        Route::post('/{service}/pozastavit',      [Admin\ServiceLifecycleController::class, 'suspend'])->name('suspend');
        Route::post('/{service}/obnovit',         [Admin\ServiceLifecycleController::class, 'unsuspend'])->name('unsuspend');
        Route::post('/{service}/ukoncit',         [Admin\ServiceLifecycleController::class, 'terminate'])->name('terminate');
    });

    /* ── API Usage Analytics (Phase 103) ── */
    Route::get('/api-analytics', [Admin\ApiUsageAnalyticsController::class, 'index'])->name('api-analytics');

    /* ── Bulk customer email (Phase 118) ── */
    Route::prefix('/hromadny-email')->name('bulk-email.')->group(function (): void {
        Route::get('/',                            [Admin\BulkCustomerEmailController::class, 'index'])->name('index');
        Route::post('/',                           [Admin\BulkCustomerEmailController::class, 'store'])->name('store');
        Route::get('/nahled-poctu',                [Admin\BulkCustomerEmailController::class, 'previewCount'])->name('preview-count');
        Route::get('/{bulkEmail}',                 [Admin\BulkCustomerEmailController::class, 'show'])->name('show');
        Route::post('/{bulkEmail}/odeslat',        [Admin\BulkCustomerEmailController::class, 'send'])->name('send');
        Route::delete('/{bulkEmail}',              [Admin\BulkCustomerEmailController::class, 'destroy'])->name('destroy');
    });

    /* ── Cancellation Survey (Phase 104) ── */
    Route::get('/odchody', [Admin\CancellationSurveyController::class, 'index'])->name('cancellation-survey');

    /* ── Revenue Forecast (Phase 107) ── */
    Route::get('/prognoza-trzeb', [Admin\RevenueForecastController::class, 'index'])->name('revenue-forecast');

    /* ── Customer Health Scores (Phase 110) ── */
    Route::get('/zdravi-zakazniku', [Admin\CustomerHealthScoreController::class, 'index'])->name('customer-health-scores.index');

    /* ── IP Allowlist (Phase 102) ── */
    Route::prefix('/ip-allowlist')->name('ip-allowlist.')->group(function (): void {
        Route::get('/',                [Admin\IpAllowlistController::class, 'index'])->name('index');
        Route::post('/',               [Admin\IpAllowlistController::class, 'store'])->name('store');
        Route::put('/{entry}',         [Admin\IpAllowlistController::class, 'update'])->name('update');
        Route::delete('/{entry}',      [Admin\IpAllowlistController::class, 'destroy'])->name('destroy');
    });

    /* ── KPI Alert System (Phase 100) ── */
    Route::prefix('/kpi-alerts')->name('kpi-alerts.')->group(function (): void {
        Route::get('/',              [Admin\KpiAlertController::class, 'index'])->name('index');
        Route::post('/',             [Admin\KpiAlertController::class, 'store'])->name('store');
        Route::put('/{kpiAlert}',    [Admin\KpiAlertController::class, 'update'])->name('update');
        Route::delete('/{kpiAlert}', [Admin\KpiAlertController::class, 'destroy'])->name('destroy');
    });

    // SLA Tiers (Phase 70)
    Route::prefix('/sla-tiery')->name('sla-tiers.')->group(function (): void {
        Route::get('/',                    [Admin\SlaTierController::class, 'index'])->name('index');
        Route::get('/novy',                [Admin\SlaTierController::class, 'create'])->name('create');
        Route::post('/',                   [Admin\SlaTierController::class, 'store'])->name('store');
        Route::get('/{slaTier}/upravit',   [Admin\SlaTierController::class, 'edit'])->name('edit');
        Route::put('/{slaTier}',           [Admin\SlaTierController::class, 'update'])->name('update');
        Route::delete('/{slaTier}',        [Admin\SlaTierController::class, 'destroy'])->name('destroy');
    });

    // SLA Incidents (Phase 70)
    Route::prefix('/sla-incidenty')->name('sla-incidents.')->group(function (): void {
        Route::get('/',                           [Admin\SlaIncidentController::class, 'index'])->name('index');
        Route::get('/novy',                       [Admin\SlaIncidentController::class, 'create'])->name('create');
        Route::post('/',                          [Admin\SlaIncidentController::class, 'store'])->name('store');
        Route::get('/{slaIncident}',              [Admin\SlaIncidentController::class, 'show'])->name('show');
        Route::post('/{slaIncident}/aktualizace', [Admin\SlaIncidentController::class, 'addUpdate'])->name('update');
    });

    /* ── Phase 152: VAT Summary Report ── */
    Route::get('/metriky/dph-prehled', [Admin\VatSummaryController::class, 'index'])->name('metrics.vat-summary');

    /* ── Phase 153: Invoice Reminder Escalation ── */
    Route::post('/faktury/eskalovat-upominky', fn () => \Illuminate\Support\Facades\Artisan::call('billing:escalate-reminders') ?: back()->with('status', 'Upomínky odeslány.'))->name('invoices.escalate-reminders');

    /* ── Phase 156: Email Suppression ── */
    Route::get('/email-potlaceni', [Admin\EmailSuppressionController::class, 'index'])->name('email-suppression.index');
    Route::post('/zakaznici/{customer}/email-potlacit', [Admin\EmailSuppressionController::class, 'suppress'])->name('email-suppression.suppress');
    Route::delete('/zakaznici/{customer}/email-potlacit', [Admin\EmailSuppressionController::class, 'unsuppress'])->name('email-suppression.unsuppress');

    /* ── Phase 159: Customer Account Merge ── */
    Route::get('/zakaznici/{customer}/sloucit', [Admin\CustomerMergeController::class, 'show'])->name('customers.merge.show');
    Route::post('/zakaznici/{customer}/sloucit', [Admin\CustomerMergeController::class, 'merge'])->name('customers.merge');

    /* ── Phase 160: Service Billing Pause (admin) ── */
    Route::post('/sluzby/{service}/pozastaveni-fakturace/schvalit', [Admin\ServiceBillingPauseController::class, 'approve'])->name('service-billing-pause.approve');
    Route::delete('/sluzby/{service}/pozastaveni-fakturace', [Admin\ServiceBillingPauseController::class, 'reject'])->name('service-billing-pause.reject');

    /* ── Phase 161: Service Config Snapshots ── */
    Route::get('/sluzby/{service}/config-snapshoty', [Admin\ServiceConfigSnapshotController::class, 'index'])->name('services.config-snapshots.index');
    Route::post('/sluzby/{service}/config-snapshoty', [Admin\ServiceConfigSnapshotController::class, 'store'])->name('services.config-snapshots.store');

    /* ── Phase 163: Server Capacity Planning ── */
    Route::get('/servery/kapacita', [Admin\ServerCapacityController::class, 'index'])->name('servers.capacity');

    /* ── Phase 165: Email Log ── */
    Route::get('/email-logy', [Admin\EmailLogController::class, 'index'])->name('email-logs.index');

    /* ── Phase 167: Customer Onboarding Checklist ── */
    Route::get('/zakaznici/{customer}/onboarding', [Admin\CustomerOnboardingController::class, 'show'])->name('customers.onboarding');

    /* ── Phase 169: Revenue Cohort Analysis ── */
    Route::get('/metriky/kohorty', [Admin\RevenueCohortController::class, 'index'])->name('metrics.cohort');

    /* ── Phase 170: Revenue by Product Category ── */
    Route::get('/metriky/produktove-kategorie', [Admin\ProductCategoryRevenueController::class, 'index'])->name('metrics.product-categories');

    /* ── Phase 171: Scheduled Tasks / Failed Jobs Dashboard ── */
    Route::get('/system/fronty', [Admin\ScheduledTasksController::class, 'index'])->name('scheduled-tasks.index');
    Route::delete('/system/fronty/clear', [Admin\ScheduledTasksController::class, 'clearFailed'])->name('scheduled-tasks.clear');
    Route::delete('/system/fronty/{id}', [Admin\ScheduledTasksController::class, 'retryJob'])->name('scheduled-tasks.retry');

    /* ── Phase 172: Invoice Batch PDF Export ── */
    Route::post('/faktury/batch-export', [Admin\InvoiceBatchExportController::class, 'export'])->name('invoice-batch.export');

    /* ── Phase 174: Churn Risk Heatmap ── */
    Route::get('/metriky/heatmapa-churn', [Admin\ChurnRiskHeatmapController::class, 'index'])->name('metrics.churn-heatmap');

    /* ── Phase 176: Custom Invoice Footer / Settings ── */
    Route::get('/nastaveni-faktur', [Admin\InvoiceSettingsController::class, 'show'])->name('invoice-settings.show');
    Route::patch('/nastaveni-faktur', [Admin\InvoiceSettingsController::class, 'update'])->name('invoice-settings.update');

    /* ── Phase 178: Provisioning Audit Trail ── */
    Route::get('/sluzby/{service}/provisioning-audit', [Admin\ProvisioningAuditController::class, 'index'])->name('services.provisioning-audit.index');

    /* ── Phase 182: Revenue by Country ── */
    Route::get('/metriky/zeme', [Admin\RevenueByCountryController::class, 'index'])->name('metrics.revenue-by-country');

    /* ── Phase 184: Failed Admin Login Monitor ── */
    Route::get('/bezpecnost/neuspesna-prihlaseni', [Admin\FailedLoginMonitorController::class, 'index'])->name('failed-logins.index');

    /* ── Phase 185: Domain WHOIS ── */
    Route::get('/nastroje/whois', [Admin\DomainWhoisController::class, 'index'])->name('domain-whois.index');
    Route::post('/nastroje/whois', [Admin\DomainWhoisController::class, 'lookup'])->name('domain-whois.lookup');

    /* ── Phase 186: Affiliate Commissions ── */
    Route::get('/affiliate', [Admin\AffiliateCommissionController::class, 'index'])->name('affiliate-commissions.index');
    Route::patch('/affiliate/{commission}/schvalit', [Admin\AffiliateCommissionController::class, 'approve'])->name('affiliate-commissions.approve');
    Route::patch('/affiliate/{commission}/vyplatit', [Admin\AffiliateCommissionController::class, 'markPaid'])->name('affiliate-commissions.pay');

    /* ── Phase 187: Invoice Partial Payments ── */
    Route::get('/faktury/{invoice}/castecne-platby', [Admin\InvoicePartialPaymentController::class, 'index'])->name('invoice-partial-payments.index');
    Route::post('/faktury/{invoice}/castecne-platby', [Admin\InvoicePartialPaymentController::class, 'store'])->name('invoice-partial-payments.store');

    /* ── Phase 188: Announcement Stats ── */
    Route::get('/oznameni/statistiky', [Admin\AnnouncementStatsController::class, 'index'])->name('announcement-stats.index');
    Route::get('/oznameni/{announcement}/statistiky', [Admin\AnnouncementStatsController::class, 'show'])->name('announcement-stats.show');

    /* ── Phase 189: Account Deletion Requests (admin) ── */
    Route::get('/zadosti-smazani', [Admin\AccountDeletionAdminController::class, 'index'])->name('account-deletion.index');
    Route::patch('/zadosti-smazani/{deletion}/schvalit', [Admin\AccountDeletionAdminController::class, 'approve'])->name('account-deletion.approve');
    Route::patch('/zadosti-smazani/{deletion}/zamit', [Admin\AccountDeletionAdminController::class, 'reject'])->name('account-deletion.reject');

    /* ── Phase 190: API Rate Limit Config ── */
    Route::get('/api-rate-limit', [Admin\ApiRateLimitConfigController::class, 'index'])->name('api-rate-limit.index');
    Route::post('/api-rate-limit', [Admin\ApiRateLimitConfigController::class, 'store'])->name('api-rate-limit.store');
    Route::delete('/api-rate-limit/{config}', [Admin\ApiRateLimitConfigController::class, 'destroy'])->name('api-rate-limit.destroy');

    /* ── Phase 194: Bulk Email Campaigns ── */
    Route::get('/hromadne-emaily', [Admin\BulkEmailCampaignController::class, 'index'])->name('bulk-email-campaigns.index');
    Route::get('/hromadne-emaily/nova', [Admin\BulkEmailCampaignController::class, 'create'])->name('bulk-email-campaigns.create');
    Route::post('/hromadne-emaily', [Admin\BulkEmailCampaignController::class, 'store'])->name('bulk-email-campaigns.store');
    Route::delete('/hromadne-emaily/{campaign}', [Admin\BulkEmailCampaignController::class, 'destroy'])->name('bulk-email-campaigns.destroy');

    /* ── Phase 195: Customer Credit Transfer ── */
    Route::get('/kredit/prevod', [Admin\CustomerCreditTransferController::class, 'index'])->name('credit-transfer.index');
    Route::post('/kredit/prevod', [Admin\CustomerCreditTransferController::class, 'transfer'])->name('credit-transfer.transfer');

    /* ── Phase 197: Invoice Templates ── */
    Route::get('/sablony-faktur', [Admin\InvoiceTemplateController::class, 'index'])->name('invoice-templates.index');
    Route::post('/sablony-faktur', [Admin\InvoiceTemplateController::class, 'store'])->name('invoice-templates.store');
    Route::delete('/sablony-faktur/{template}', [Admin\InvoiceTemplateController::class, 'destroy'])->name('invoice-templates.destroy');

    /* ── Phase 198: Audit Log Export ── */
    Route::get('/audit-log/export', [Admin\AuditLogExportController::class, 'index'])->name('audit-log-export.index');
    Route::get('/audit-log/export/download', [Admin\AuditLogExportController::class, 'export'])->name('audit-log-export.export');

    /* ── Phase 200: CSAT Dashboard ── */
    Route::get('/metriky/csat', [Admin\CustomerSatisfactionController::class, 'index'])->name('metrics.csat');

    /* ── Phase 201: Webhook Retry Policy ── */
    Route::get('/webhook-retry-politika', [Admin\WebhookRetryPolicyController::class, 'index'])->name('webhook-retry-policy.index');
    Route::patch('/webhook-retry-politika/{endpointId}', [Admin\WebhookRetryPolicyController::class, 'update'])->name('webhook-retry-policy.update');

    /* ── Phase 202: Service Uptime Monitoring (admin) ── */
    Route::get('/uptime', [Admin\ServiceUptimeController::class, 'index'])->name('service-uptime.index');
    Route::get('/uptime/{service}', [Admin\ServiceUptimeController::class, 'show'])->name('service-uptime.show');
    Route::post('/uptime/{service}/check', [Admin\ServiceUptimeController::class, 'store'])->name('service-uptime.store');

    /* ── Phase 203: Invoice Reminder Rules ── */
    Route::get('/upominky-faktur', [Admin\InvoiceReminderRuleController::class, 'index'])->name('invoice-reminder-rules.index');
    Route::post('/upominky-faktur', [Admin\InvoiceReminderRuleController::class, 'store'])->name('invoice-reminder-rules.store');
    Route::patch('/upominky-faktur/{rule}', [Admin\InvoiceReminderRuleController::class, 'update'])->name('invoice-reminder-rules.update');
    Route::delete('/upominky-faktur/{rule}', [Admin\InvoiceReminderRuleController::class, 'destroy'])->name('invoice-reminder-rules.destroy');

    /* ── Phase 206: Reseller White-label Branding ── */
    Route::get('/reseller-whitelabel', [Admin\ResellerWhitelabelController::class, 'index'])->name('reseller-whitelabel.index');
    Route::get('/reseller-whitelabel/{reseller}/upravit', [Admin\ResellerWhitelabelController::class, 'edit'])->name('reseller-whitelabel.edit');
    Route::patch('/reseller-whitelabel/{reseller}', [Admin\ResellerWhitelabelController::class, 'update'])->name('reseller-whitelabel.update');

    /* ── Phase 205: KB Article Comments (admin moderation) ── */
    Route::get('/znalostni-baze/komentare', [Admin\KbArticleCommentController::class, 'index'])->name('kb-comments.index');
    Route::patch('/znalostni-baze/komentare/{comment}/schvalit', [Admin\KbArticleCommentController::class, 'approve'])->name('kb-comments.approve');
    Route::delete('/znalostni-baze/komentare/{comment}', [Admin\KbArticleCommentController::class, 'destroy'])->name('kb-comments.destroy');

    /* ── Phase 210: Service Resource Usage Dashboard ── */
    Route::get('/metriky/vyuziti-zdroju', [Admin\ServiceResourceUsageController::class, 'index'])->name('service-resource-usage.index');

    /* ── Phase 207: Fraud Review Queue ── */
    Route::get('/fraud-review', [Admin\FraudReviewController::class, 'index'])->name('fraud-reviews.index');
    Route::post('/fraud-review', [Admin\FraudReviewController::class, 'store'])->name('fraud-reviews.store');
    Route::patch('/fraud-review/{review}', [Admin\FraudReviewController::class, 'update'])->name('fraud-reviews.update');

    /* ── Phase 212: Email Delivery Tracking (admin) ── */
    Route::get('/e-maily', [Admin\EmailDeliveryController::class, 'index'])->name('email-deliveries.index');

    /* ── Phase 214: Admin Tax Rate Configuration ── */
    Route::get('/sazby-dani', [Admin\TaxRateController::class, 'index'])->name('tax-rates.index');
    Route::post('/sazby-dani', [Admin\TaxRateController::class, 'store'])->name('tax-rates.store');
    Route::patch('/sazby-dani/{taxRate}', [Admin\TaxRateController::class, 'update'])->name('tax-rates.update');
    Route::delete('/sazby-dani/{taxRate}', [Admin\TaxRateController::class, 'destroy'])->name('tax-rates.destroy');

    /* ── Phase 215: Service Resource Snapshot History (admin) ── */
    Route::get('/snapshoty', [Admin\ServiceResourceSnapshotController::class, 'index'])->name('service-resource-snapshots.index');
    Route::post('/sluzby/{service}/snapshoty', [Admin\ServiceResourceSnapshotController::class, 'store'])->name('service-resource-snapshots.store');

    /* ── Phase 216: Admin IP Blocklist ── */
    Route::get('/ip-blocklist', [Admin\IpBlocklistController::class, 'index'])->name('ip-blocklist.index');
    Route::post('/ip-blocklist', [Admin\IpBlocklistController::class, 'store'])->name('ip-blocklist.store');
    Route::delete('/ip-blocklist/{ipBlocklistEntry}', [Admin\IpBlocklistController::class, 'destroy'])->name('ip-blocklist.destroy');

    /* ── Phase 218: Invoice Dunning Configuration ── */
    Route::get('/dunning-konfigurace', [Admin\DunningConfigController::class, 'index'])->name('dunning-configs.index');
    Route::post('/dunning-konfigurace', [Admin\DunningConfigController::class, 'store'])->name('dunning-configs.store');
    Route::patch('/dunning-konfigurace/{dunningConfig}', [Admin\DunningConfigController::class, 'update'])->name('dunning-configs.update');
    Route::delete('/dunning-konfigurace/{dunningConfig}', [Admin\DunningConfigController::class, 'destroy'])->name('dunning-configs.destroy');

    /* ── Phase 220: Admin Customer Export Templates ── */
    Route::get('/sablony-exportu', [Admin\ExportTemplateController::class, 'index'])->name('export-templates.index');
    Route::post('/sablony-exportu', [Admin\ExportTemplateController::class, 'store'])->name('export-templates.store');
    Route::delete('/sablony-exportu/{exportTemplate}', [Admin\ExportTemplateController::class, 'destroy'])->name('export-templates.destroy');

    /* ── Phase 221: Admin Revenue by Reseller Report ── */
    Route::get('/prehled-reselleru', [Admin\RevenueByResellerController::class, 'index'])->name('revenue-by-reseller.index');

    /* ── Phase 222: Admin Payment Retry Schedules ── */
    Route::get('/opakovani-plateb', [Admin\PaymentRetryScheduleController::class, 'index'])->name('payment-retry-schedules.index');
    Route::post('/opakovani-plateb', [Admin\PaymentRetryScheduleController::class, 'store'])->name('payment-retry-schedules.store');
    Route::delete('/opakovani-plateb/{paymentRetrySchedule}', [Admin\PaymentRetryScheduleController::class, 'destroy'])->name('payment-retry-schedules.destroy');

    /* ── Phase 223: Admin Price Change Notifications ── */
    /* ── I130: správa changelogu ── */
    Route::get('/novinky', [Admin\ProductUpdateController::class, 'index'])->name('product-updates.index');
    Route::get('/novinky/nova', [Admin\ProductUpdateController::class, 'create'])->name('product-updates.create');
    Route::post('/novinky', [Admin\ProductUpdateController::class, 'store'])->name('product-updates.store');
    Route::get('/novinky/{productUpdate}/upravit', [Admin\ProductUpdateController::class, 'edit'])->name('product-updates.edit');
    Route::put('/novinky/{productUpdate}', [Admin\ProductUpdateController::class, 'update'])->name('product-updates.update');
    Route::delete('/novinky/{productUpdate}', [Admin\ProductUpdateController::class, 'destroy'])->name('product-updates.destroy');

    Route::get('/oznameni-zdrazeni', [Admin\PriceChangeNotificationController::class, 'index'])->name('price-change-notifications.index');
    Route::post('/oznameni-zdrazeni', [Admin\PriceChangeNotificationController::class, 'store'])->name('price-change-notifications.store');
    Route::delete('/oznameni-zdrazeni/{priceChangeNotification}', [Admin\PriceChangeNotificationController::class, 'destroy'])->name('price-change-notifications.destroy');

    /* ── Phase 224: Admin Service Changelogs ── */
    Route::get('/changelog-sluzeb', [Admin\ServiceChangelogController::class, 'index'])->name('service-changelogs.index');
    Route::post('/changelog-sluzeb', [Admin\ServiceChangelogController::class, 'store'])->name('service-changelogs.store');

    /* ── Phase 225: Admin Domain Transfer Requests ── */
    Route::get('/prevody-domen', [Admin\DomainTransferRequestController::class, 'index'])->name('domain-transfer-requests.index');
    Route::patch('/prevody-domen/{domainTransferRequest}', [Admin\DomainTransferRequestController::class, 'update'])->name('domain-transfer-requests.update');

    /* ── Phase 226: Admin License Keys ── */
    Route::get('/licencni-klice', [Admin\LicenseKeyController::class, 'index'])->name('license-keys.index');
    Route::post('/licencni-klice', [Admin\LicenseKeyController::class, 'store'])->name('license-keys.store');
    Route::patch('/licencni-klice/{licenseKey}', [Admin\LicenseKeyController::class, 'update'])->name('license-keys.update');

    /* ── Phase 228: Admin Service Migration Batches ── */
    Route::get('/migrace-sluzeb', [Admin\ServiceMigrationBatchController::class, 'index'])->name('service-migration-batches.index');
    Route::post('/migrace-sluzeb', [Admin\ServiceMigrationBatchController::class, 'store'])->name('service-migration-batches.store');

    /* ── Phase 229: Admin Customer Segment Tags ── */
    Route::get('/segmentacni-stitky', [Admin\CustomerSegmentTagController::class, 'index'])->name('customer-segment-tags.index');
    Route::post('/segmentacni-stitky', [Admin\CustomerSegmentTagController::class, 'store'])->name('customer-segment-tags.store');
    Route::delete('/segmentacni-stitky/{customerSegmentTag}', [Admin\CustomerSegmentTagController::class, 'destroy'])->name('customer-segment-tags.destroy');

    /* ── Phase 230: Admin SSL Certificate Checks ── */
    Route::get('/ssl-kontroly', [Admin\SslCertificateCheckController::class, 'index'])->name('ssl-certificate-checks.index');
    Route::post('/ssl-kontroly', [Admin\SslCertificateCheckController::class, 'store'])->name('ssl-certificate-checks.store');

    /* ── Phase 232: Admin Auto Suspend Rules ── */
    Route::get('/pravidla-pozastaveni', [Admin\AutoSuspendRuleController::class, 'index'])->name('auto-suspend-rules.index');
    Route::post('/pravidla-pozastaveni', [Admin\AutoSuspendRuleController::class, 'store'])->name('auto-suspend-rules.store');
    Route::patch('/pravidla-pozastaveni/{autoSuspendRule}', [Admin\AutoSuspendRuleController::class, 'update'])->name('auto-suspend-rules.update');

    /* ── Phase 233: Admin Report Schedules ── */
    Route::get('/planovane-reporty', [Admin\ReportScheduleController::class, 'index'])->name('report-schedules.index');
    Route::post('/planovane-reporty', [Admin\ReportScheduleController::class, 'store'])->name('report-schedules.store');
    Route::delete('/planovane-reporty/{reportSchedule}', [Admin\ReportScheduleController::class, 'destroy'])->name('report-schedules.destroy');

    /* ── Phase 236: Admin Chargeback Management ── */
    Route::get('/chargeback', [Admin\ChargebackController::class, 'index'])->name('chargebacks.index');
    Route::patch('/chargeback/{chargeback}', [Admin\ChargebackController::class, 'update'])->name('chargebacks.update');

    /* ── Phase 240: Admin Customer Merges ── */
    Route::get('/slucovani-zakazniku', [Admin\CustomerMergeController::class, 'index'])->name('customer-merges.index');
    Route::post('/slucovani-zakazniku', [Admin\CustomerMergeController::class, 'store'])->name('customer-merges.store');

    /* ── Phase 241: Admin Saved Search Filters ── */
    Route::get('/ulozene-filtry', [Admin\SavedSearchFilterController::class, 'index'])->name('saved-search-filters.index');
    Route::post('/ulozene-filtry', [Admin\SavedSearchFilterController::class, 'store'])->name('saved-search-filters.store');
    Route::patch('/ulozene-filtry/{savedSearchFilter}', [Admin\SavedSearchFilterController::class, 'update'])->name('saved-search-filters.update');
    Route::delete('/ulozene-filtry/{savedSearchFilter}', [Admin\SavedSearchFilterController::class, 'destroy'])->name('saved-search-filters.destroy');

    /* ── Phase 242: Admin Tax Rate Application Log ── */
    Route::get('/aplikace-sazeb-dani', [Admin\TaxRateApplicationController::class, 'index'])->name('tax-rate-applications.index');

    /* ── Phase 243: Admin Service Log Viewer ── */
    Route::get('/logy-sluzeb', [Admin\ServiceLogController::class, 'index'])->name('service-logs.index');

    /* ── Phase 244: Admin Promotional Banners ── */
    Route::get('/reklamni-bannery', [Admin\PromotionalBannerController::class, 'index'])->name('promotional-banners.index');
    Route::post('/reklamni-bannery', [Admin\PromotionalBannerController::class, 'store'])->name('promotional-banners.store');
    Route::patch('/reklamni-bannery/{promotionalBanner}', [Admin\PromotionalBannerController::class, 'update'])->name('promotional-banners.update');
    Route::delete('/reklamni-bannery/{promotionalBanner}', [Admin\PromotionalBannerController::class, 'destroy'])->name('promotional-banners.destroy');

    /* ── Phase 249: Admin Voucher Management ── */
    Route::get('/vouchery', [Admin\VoucherController::class, 'index'])->name('vouchers.index');
    Route::post('/vouchery', [Admin\VoucherController::class, 'store'])->name('vouchers.store');
    Route::patch('/vouchery/{voucher}', [Admin\VoucherController::class, 'update'])->name('vouchers.update');

    /* ── Phase 250: Admin Chargeback Analytics ── */
    Route::get('/chargeback/analytika', [Admin\ChargebackAnalyticsController::class, 'index'])->name('chargeback-analytics.index');

    /* ── Phase 252: Admin SSL Certificate Monitoring ── */
    Route::get('/ssl-monitoring', [Admin\SslCertificateMonitoringController::class, 'index'])->name('ssl-monitoring.index');

    /* ── Phase 254: Admin Customer Segment Analytics ── */
    Route::get('/segmentacni-stitky/analytika', [Admin\CustomerSegmentAnalyticsController::class, 'index'])->name('customer-segment-analytics.index');

    /* ── Phase 255: Admin Auto-Suspend Log ── */
    Route::get('/pravidla-pozastaveni/log', [Admin\AutoSuspendLogController::class, 'index'])->name('auto-suspend-log.index');

    /* ── Phase 257: Admin Voucher Analytics ── */
    Route::get('/vouchery/analytika', [Admin\VoucherAnalyticsController::class, 'index'])->name('voucher-analytics.index');

    /* ── Phase 259: Admin Service Log Analytics ── */
    Route::get('/logy-sluzeb/analytika', [Admin\ServiceLogAnalyticsController::class, 'index'])->name('service-log-analytics.index');

    /* ── Phase 260: Admin Report Schedule Run Trigger ── */
    Route::post('/planovane-reporty/{reportSchedule}/spustit', [Admin\ReportScheduleRunController::class, 'store'])->name('report-schedule-runs.store');

    /* ── Phase 261: Admin Domain Transfer Statistics ── */
    Route::get('/prevody-domen/statistiky', [Admin\DomainTransferStatisticsController::class, 'index'])->name('domain-transfer-statistics.index');

    /* ── Phase 262: Admin Service Firewall Rules ── */
    Route::get('/firewall-pravidla', [Admin\ServiceFirewallRuleController::class, 'index'])->name('service-firewall-rules.index');
    Route::post('/firewall-pravidla', [Admin\ServiceFirewallRuleController::class, 'store'])->name('service-firewall-rules.store');
    Route::patch('/firewall-pravidla/{serviceFirewallRule}', [Admin\ServiceFirewallRuleController::class, 'update'])->name('service-firewall-rules.update');
    Route::delete('/firewall-pravidla/{serviceFirewallRule}', [Admin\ServiceFirewallRuleController::class, 'destroy'])->name('service-firewall-rules.destroy');

    /* ── Phase 263: Admin Customer Communication Log ── */
    Route::get('/komunikace-zakazniku', [Admin\CustomerCommunicationLogController::class, 'index'])->name('customer-communication-logs.index');
    Route::post('/komunikace-zakazniku', [Admin\CustomerCommunicationLogController::class, 'store'])->name('customer-communication-logs.store');

    /* ── Phase 264: Admin Service Health Incidents ── */
    Route::get('/incidenty-sluzeb', [Admin\ServiceHealthIncidentController::class, 'index'])->name('service-health-incidents.index');
    Route::post('/incidenty-sluzeb', [Admin\ServiceHealthIncidentController::class, 'store'])->name('service-health-incidents.store');
    Route::patch('/incidenty-sluzeb/{serviceHealthIncident}', [Admin\ServiceHealthIncidentController::class, 'update'])->name('service-health-incidents.update');

    /* ── Phase 265: Admin Maintenance Windows ── */
    Route::get('/okna-udrzby', [Admin\MaintenanceWindowController::class, 'index'])->name('maintenance-windows.index');
    Route::post('/okna-udrzby', [Admin\MaintenanceWindowController::class, 'store'])->name('maintenance-windows.store');
    Route::patch('/okna-udrzby/{maintenanceWindow}', [Admin\MaintenanceWindowController::class, 'update'])->name('maintenance-windows.update');
    Route::delete('/okna-udrzby/{maintenanceWindow}', [Admin\MaintenanceWindowController::class, 'destroy'])->name('maintenance-windows.destroy');

    /* ── Phase 266: Admin Customer Onboarding Steps ── */
    Route::get('/kroky-onboardingu', [Admin\CustomerOnboardingStepController::class, 'index'])->name('customer-onboarding-steps.index');
    Route::post('/kroky-onboardingu', [Admin\CustomerOnboardingStepController::class, 'store'])->name('customer-onboarding-steps.store');
    Route::patch('/kroky-onboardingu/{customerOnboardingStep}', [Admin\CustomerOnboardingStepController::class, 'update'])->name('customer-onboarding-steps.update');

    /* ── Phase 267: Admin Reseller Payout Requests ── */
    Route::get('/vyplaty-resellerum', [Admin\ResellerPayoutRequestController::class, 'index'])->name('reseller-payout-requests.index');
    Route::patch('/vyplaty-resellerum/{resellerPayoutRequest}', [Admin\ResellerPayoutRequestController::class, 'update'])->name('reseller-payout-requests.update');

    /* ── Phase 268: Admin Service Config Profiles ── */
    Route::get('/konfiguracni-profily', [Admin\ServiceConfigProfileController::class, 'index'])->name('service-config-profiles.index');
    Route::post('/konfiguracni-profily', [Admin\ServiceConfigProfileController::class, 'store'])->name('service-config-profiles.store');
    Route::patch('/konfiguracni-profily/{serviceConfigProfile}', [Admin\ServiceConfigProfileController::class, 'update'])->name('service-config-profiles.update');
    Route::delete('/konfiguracni-profily/{serviceConfigProfile}', [Admin\ServiceConfigProfileController::class, 'destroy'])->name('service-config-profiles.destroy');

    /* ── Phase 269: Admin Revenue Comparison Dashboard ── */
    Route::get('/metriky/porovnani-trzeb', [Admin\RevenueComparisonController::class, 'index'])->name('metrics.revenue-comparison');

    /* ── Phase 270: Admin Service Backup Logs ── */
    Route::get('/zalohy-sluzeb', [Admin\ServiceBackupLogController::class, 'index'])->name('service-backup-logs.index');

    /* ── Phase 271: Admin Portal Announcements ── */
    Route::get('/oznameni-portalu', [Admin\PortalAnnouncementController::class, 'index'])->name('portal-announcements.index');
    Route::post('/oznameni-portalu', [Admin\PortalAnnouncementController::class, 'store'])->name('portal-announcements.store');
    Route::patch('/oznameni-portalu/{portalAnnouncement}', [Admin\PortalAnnouncementController::class, 'update'])->name('portal-announcements.update');
    Route::delete('/oznameni-portalu/{portalAnnouncement}', [Admin\PortalAnnouncementController::class, 'destroy'])->name('portal-announcements.destroy');
});
