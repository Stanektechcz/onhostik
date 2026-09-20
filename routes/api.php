<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ArchiveController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\CalendarController;
use App\Http\Controllers\Api\V1\CapacityController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\ComplianceController;
use App\Http\Controllers\Api\V1\ContentController;
use App\Http\Controllers\Api\V1\DnsController;
use App\Http\Controllers\Api\V1\DomainController;
use App\Http\Controllers\Api\V1\GreenController;
use App\Http\Controllers\Api\V1\InsightsController;
use App\Http\Controllers\Api\V1\IntegrationController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\MarketplaceController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\OrganizationStatusController;
use App\Http\Controllers\Api\V1\PartnerController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\RegistrarConnectionController;
use App\Http\Controllers\Api\V1\RewardsController;
use App\Http\Controllers\Api\V1\ServiceAccessController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\Staff\ApprovalController;
use App\Http\Controllers\Api\V1\Staff\ChargebackController;
use App\Http\Controllers\Api\V1\Staff\ComplianceController as StaffComplianceController;
use App\Http\Controllers\Api\V1\Staff\ConsoleController;
use App\Http\Controllers\Api\V1\Staff\ContentController as StaffContentController;
use App\Http\Controllers\Api\V1\Staff\CustomerController;
use App\Http\Controllers\Api\V1\Staff\IncidentController as StaffIncidentController;
use App\Http\Controllers\Api\V1\Staff\MarketplaceController as StaffMarketplaceController;
use App\Http\Controllers\Api\V1\Staff\OnCallController;
use App\Http\Controllers\Api\V1\Staff\PartnerController as StaffPartnerController;
use App\Http\Controllers\Api\V1\Staff\PaymentsController;
use App\Http\Controllers\Api\V1\Staff\PricingController;
use App\Http\Controllers\Api\V1\Staff\ProvisioningController;
use App\Http\Controllers\Api\V1\Staff\RegistrarController;
use App\Http\Controllers\Api\V1\Staff\ReportController;
use App\Http\Controllers\Api\V1\Staff\SupportController as StaffSupportController;
use App\Http\Controllers\Api\V1\StatusController;
use App\Http\Controllers\Api\V1\SupportController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Controllers\Api\V1\WebToolsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ONhost API v1 (blueprint §17, handoff docs-laravel-backend §5)
| Mounted under `/v1`. JSON only; errors `{error, message, status, errors?{field:[…]}}`;
| lists `?limit=&offset=` + `X-Total-Count`; POST/PUT/PATCH honour `Idempotency-Key`.
|--------------------------------------------------------------------------
*/

// ── public ──────────────────────────────────────────────────────────────────
Route::middleware('throttle:public')->group(function (): void {
    Route::get('catalog', [CatalogController::class, 'index']);
    Route::get('catalog/tlds', [CatalogController::class, 'tlds']);
    Route::get('catalog/promo', [CatalogController::class, 'promo']);
    Route::get('catalog/regions', [CatalogController::class, 'regions']); // regional pricing (audit §5j-8)
    Route::get('catalog/{product}', [CatalogController::class, 'show']);
    Route::post('domains/check', [CatalogController::class, 'checkDomains'])->middleware('throttle:domain-check');

    Route::get('cart', [CartController::class, 'show']);
    Route::put('cart', [CartController::class, 'update']);
    Route::post('cart/promo', [CartController::class, 'promo']);
    Route::post('cart/quote', [CartController::class, 'quote']);
    Route::post('checkout/guest', [CheckoutController::class, 'guest'])->middleware('throttle:auth');

    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('auth/password/reset', [AuthController::class, 'requestPasswordReset'])->middleware('throttle:auth');
    Route::post('auth/password/reset/confirm', [AuthController::class, 'confirmPasswordReset'])->middleware('throttle:auth');
    Route::post('auth/verify-email', [AuthController::class, 'verifyEmail'])->middleware('throttle:auth');

    Route::post('webhooks/payments/{provider}', [PaymentController::class, 'webhook'])->withoutMiddleware('throttle:public')->middleware('throttle:payment-callbacks'); // every callback makes us ask the provider: a ceiling per source
    Route::get('oncall/feed/{token}.ics', [OnCallController::class, 'feed'])->where('token', '[a-f0-9]{48}')->middleware('throttle:probes'); // the rota for a calendar app (audit §5u-4)
    Route::post('webhooks/alertmanager', [OnCallController::class, 'alertmanager'])->withoutMiddleware('throttle:public')->middleware('throttle:probes'); // Prometheus rules → on-call alerts (infra/monitoring/alertmanager.yml)
    Route::post('webhooks/oncall/{provider}', [OnCallController::class, 'inbound'])->withoutMiddleware('throttle:public')->middleware('throttle:probes'); // the pager acknowledged / resolved on its side (audit §5q-1)
    Route::post('hooks/deploy/{source}', [WebToolsController::class, 'hook'])->middleware('throttle:auth'); // git push notifications (HMAC-signed)
    Route::post('hooks/run/{token}', [IntegrationController::class, 'runHook'])->middleware('throttle:auth'); // action hooks (token in the URL)
    Route::post('integrations/discord/interactions', [IntegrationController::class, 'discordInteractions'])->withoutMiddleware('throttle:public'); // Discord slash commands and buttons (Ed25519-signed)

    // status page (`/stav`), DSA abuse notice form, external probe ingest
    Route::get('status', [StatusController::class, 'status']);
    Route::get('status/org/{slug}', [OrganizationStatusController::class, 'show']); // the organization's own status page (audit §5j-5)
    Route::get('status/host-check', [OrganizationStatusController::class, 'hostAllowed']); // on-demand TLS ask for the customers' status hosts (audit §5k-3)
    Route::post('probes/capacity/{capacityRequest}/ready', [CapacityController::class, 'ready'])->withoutMiddleware('throttle:public')->middleware('throttle:probes'); // a vendor node's bootstrap reports back (audit §5o-7)
    Route::post('probes/capacity/{capacityRequest}/activate', [CapacityController::class, 'activate'])->withoutMiddleware('throttle:public')->middleware('throttle:probes'); // the playbook reports the hypervisor installed (audit §5p-7)
    Route::post('probes/power', [GreenController::class, 'ingestPower'])->withoutMiddleware('throttle:public')->middleware('throttle:probes'); // measured watts per node (audit §5k-6)
    Route::get('green', [GreenController::class, 'platform']); // green hosting profile (audit §5j-10)
    Route::get('marketplace', [MarketplaceController::class, 'index']); // marketplace of partner services (audit §5j-1)
    Route::get('marketplace/{listing}', [MarketplaceController::class, 'show']);
    Route::get('incidents', [StatusController::class, 'incidents']);
    Route::get('incidents/postmortems', [StatusController::class, 'postmortems']);
    Route::get('incidents/{number}', [StatusController::class, 'incident']);
    Route::post('abuse/reports', [ComplianceController::class, 'reportAbuse'])->middleware('throttle:auth');
    Route::post('probes/results', [StatusController::class, 'ingest'])->withoutMiddleware('throttle:public')->middleware('throttle:probes');

    // marketing content (onhost-data.js / onhost-content.js) and inbound forms
    Route::get('posts', [ContentController::class, 'posts']);
    Route::get('posts/{slug}', [ContentController::class, 'post']);
    Route::get('kb', [ContentController::class, 'kb']);
    Route::get('kb/{slug}', [ContentController::class, 'kbArticle']);
    Route::get('changelog', [ContentController::class, 'changelog']);
    Route::get('locations', [ContentController::class, 'locations']);
    Route::get('stock', [ContentController::class, 'stock']);
    Route::post('leads', [ContentController::class, 'lead'])->middleware('throttle:auth');
    Route::post('tender/request', [ContentController::class, 'tender'])->middleware('throttle:auth');
    Route::get('reseller/tiers', [ContentController::class, 'resellerTiers']);
    Route::post('reseller/apply', [ContentController::class, 'resellerApply'])->middleware('throttle:auth');
});

// ── signed in (cookie session or bearer token) ───────────────────────────────
Route::middleware(['auth:sanctum', 'token.scope', 'throttle:api', 'idempotency'])->group(function (): void { // token.scope: a bearer token reaches only the route families its scopes name
    Route::get('me', [AuthController::class, 'me']);
    Route::get('my/incidents', [StatusController::class, 'mine']);
    Route::get('sla-credits', [StatusController::class, 'credits']);
    Route::get('abuse-cases', [ComplianceController::class, 'abuseCases']);
    Route::post('abuse-cases/{case}/appeal', [ComplianceController::class, 'appeal']);
    Route::get('data-requests', [ComplianceController::class, 'dataRequests']);
    Route::post('data-requests', [ComplianceController::class, 'requestData']);
    Route::get('data-requests/{dataRequest}/download', [ComplianceController::class, 'download']);
    Route::post('data-requests/{dataRequest}/link', [ComplianceController::class, 'link']); // signed download link (audit §5j-7)

    // partner portal (Onhost-partner.dc.html)
    Route::prefix('partner')->group(function (): void {
        Route::post('apply', [PartnerController::class, 'apply']);
        Route::get('overview', [PartnerController::class, 'overview']);
        Route::get('clients', [PartnerController::class, 'clients']);
        Route::get('commissions', [PartnerController::class, 'commissions']);
        Route::get('payouts', [PartnerController::class, 'payouts']);
        Route::post('payouts', [PartnerController::class, 'requestPayout']);
        Route::get('whitelabel', [PartnerController::class, 'whitelabel']);
        Route::put('whitelabel', [PartnerController::class, 'updateWhitelabel']);
        Route::get('assets', [PartnerController::class, 'assets']);
        Route::get('model', [PartnerController::class, 'modelRequest']); // the commission model as a contract term (audit §5m-1)
        Route::post('model', [PartnerController::class, 'requestModel']);
        Route::get('changes', [PartnerController::class, 'changes']); // every contract term and its requests (audit §5n-1)
        Route::post('changes', [PartnerController::class, 'requestChange']);
        // marketplace (audit §5j-1): the partner's listings and deliveries
        Route::get('marketplace/listings', [MarketplaceController::class, 'partnerListings']);
        Route::post('marketplace/listings', [MarketplaceController::class, 'createListing']);
        Route::put('marketplace/listings/{listing}', [MarketplaceController::class, 'updateListing']);
        Route::post('marketplace/listings/{listing}/state', [MarketplaceController::class, 'listingState']);
        Route::get('marketplace/orders', [MarketplaceController::class, 'partnerOrders']);
        Route::post('marketplace/orders/{order}/start', [MarketplaceController::class, 'start']);
        Route::post('marketplace/orders/{order}/deliver', [MarketplaceController::class, 'deliver']);
        Route::post('marketplace/orders/{order}/evidence', [MarketplaceController::class, 'uploadEvidence']); // a file behind a checklist item (audit §5p-3)
    });
    // marketplace, customer side (the public `marketplace/{listing}` keeps the catalogue paths; the account owns its orders)
    Route::get('account/marketplace/orders', [MarketplaceController::class, 'orders']);
    Route::post('marketplace/{listing}/order', [MarketplaceController::class, 'order']);
    Route::post('account/marketplace/orders/{order}/accept', [MarketplaceController::class, 'accept']);
    Route::post('account/marketplace/orders/{order}/dispute', [MarketplaceController::class, 'dispute']);
    Route::post('account/marketplace/orders/{order}/cancel', [MarketplaceController::class, 'cancel']);
    Route::get('account/marketplace/orders/{order}/evidence/{entry}/{key}', [MarketplaceController::class, 'evidenceFile'])->where('entry', '[0-9]+'); // the file behind a report item (audit §5p-3)
    Route::patch('me', [MeController::class, 'update']);
    Route::post('me/password', [MeController::class, 'changePassword']);
    Route::get('me/shared-services', [ServiceAccessController::class, 'mine']);
    Route::post('me/totp/enroll', [MeController::class, 'totpEnroll']);
    Route::post('me/totp/confirm', [MeController::class, 'totpConfirm']);
    Route::post('me/totp/disable', [MeController::class, 'totpDisable']);
    Route::get('tokens', [MeController::class, 'tokens']);
    Route::post('tokens', [MeController::class, 'createToken']);
    Route::delete('tokens/{token}', [MeController::class, 'revokeToken']);
    Route::post('auth/step-up', [AuthController::class, 'stepUp']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::get('organizations', [OrganizationController::class, 'index']);
    Route::post('organizations', [OrganizationController::class, 'store']); // a customer profile for a user without one (audit §5z)
    Route::post('organizations/invitations/accept', [OrganizationController::class, 'acceptInvitation']);
    Route::get('organizations/{organization}', [OrganizationController::class, 'show']);
    Route::patch('organizations/{organization}', [OrganizationController::class, 'update']);
    Route::get('organizations/{organization}/digest', [OrganizationController::class, 'digest']);
    Route::post('organizations/{organization}/invitations', [OrganizationController::class, 'invite']);
    Route::delete('organizations/{organization}/invitations/{invitation}', [OrganizationController::class, 'cancelInvitation']);
    Route::patch('organizations/{organization}/members/{user}', [OrganizationController::class, 'changeRole']);
    Route::delete('organizations/{organization}/members/{user}', [OrganizationController::class, 'removeMember']);
    Route::post('organizations/{organization}/projects', [OrganizationController::class, 'createProject']);
    Route::get('organizations/{organization}/projects', [ProjectController::class, 'index']);
    Route::get('organizations/{organization}/projects/{project}', [ProjectController::class, 'show']);
    Route::patch('organizations/{organization}/projects/{project}', [ProjectController::class, 'update']);
    Route::post('organizations/{organization}/projects/{project}/archive', [ProjectController::class, 'archive']);
    Route::post('organizations/{organization}/projects/{project}/restore', [ProjectController::class, 'restore']);
    Route::post('organizations/{organization}/projects/{project}/members', [ProjectController::class, 'addMember']);
    Route::delete('organizations/{organization}/projects/{project}/members/{user}', [ProjectController::class, 'removeMember']);
    Route::get('organizations/{organization}/audit', [OrganizationController::class, 'audit']);

    Route::get('orders', [OrderController::class, 'index']);
    Route::post('orders', [OrderController::class, 'store']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::post('orders/{order}/transition', [OrderController::class, 'transition']);

    Route::get('monitors', [InsightsController::class, 'monitors'])->middleware('shed');
    Route::get('backups', [InsightsController::class, 'backups'])->middleware('shed');
    Route::get('calendar', [CalendarController::class, 'index']);
    Route::get('calendar/feed', [CalendarController::class, 'feed']);
    Route::post('calendar/feed/rotate', [CalendarController::class, 'rotate']);

    Route::get('wallet', [WalletController::class, 'show']);
    Route::get('account/rewards', [RewardsController::class, 'show']); // loyalty programme: points, level, badges
    Route::get('account/referral', [RewardsController::class, 'referral']); // invites (audit §5j-2)
    Route::post('account/referral/code', [RewardsController::class, 'referralCode']);
    Route::get('account/missions', [RewardsController::class, 'missions']); // missions and the streak (audit §5j-3)
    Route::post('account/missions/evaluate', [RewardsController::class, 'evaluateMissions']);
    Route::get('account/green', [GreenController::class, 'account']); // footprint (audit §5j-10)
    Route::get('account/status-page', [OrganizationStatusController::class, 'mine']); // status page settings and preview (audit §5j-5)
    Route::post('account/status-page/verify', [OrganizationStatusController::class, 'verify']); // the customer's own status host (audit §5k-3)
    Route::get('wallet/transactions', [WalletController::class, 'transactions']);
    Route::post('wallet/topup', [WalletController::class, 'topup']);
    Route::put('wallet/auto-topup', [WalletController::class, 'autoTopup']);
    Route::get('wallet/budget', [WalletController::class, 'budget']); // the customer's own monthly spending limit (H30)
    Route::put('wallet/budget', [WalletController::class, 'setBudget']);
    Route::get('payment-methods', [WalletController::class, 'paymentMethods']);
    Route::delete('payment-methods/{method}', [WalletController::class, 'removePaymentMethod']);
    Route::post('payments/init', [WalletController::class, 'topup']);
    Route::get('payments', [PaymentController::class, 'index']);
    Route::get('payments/{intent}', [PaymentController::class, 'show']);
    Route::post('payments/{intent}/sync', [PaymentController::class, 'sync']);

    Route::get('tickets', [SupportController::class, 'index']);
    Route::post('tickets', [SupportController::class, 'store']);
    Route::get('tickets/{ticket}', [SupportController::class, 'show']);
    Route::post('tickets/{ticket}/messages', [SupportController::class, 'reply']);
    Route::post('tickets/{ticket}/close', [SupportController::class, 'close']);
    Route::post('tickets/{ticket}/csat', [SupportController::class, 'rate']);
    Route::get('tickets/{ticket}/work-offers', [SupportController::class, 'workOffers']); // paid work outside the plan: offered with a price, billed only after approval (H29)
    Route::post('tickets/{ticket}/work-offers/{offer}/decision', [SupportController::class, 'decideWorkOffer']);
    Route::post('assistant/chat', [SupportController::class, 'assistant']);

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/read', [NotificationController::class, 'read']);
    Route::get('notifications/preferences', [NotificationController::class, 'preferences']);
    Route::put('notifications/preferences', [NotificationController::class, 'updatePreference']);
    Route::get('webhooks', [NotificationController::class, 'webhooks']);
    Route::post('webhooks', [NotificationController::class, 'createWebhook']);
    Route::delete('webhooks/{endpoint}', [NotificationController::class, 'deleteWebhook']);
    Route::get('webhooks/{endpoint}/deliveries', [NotificationController::class, 'webhookDeliveries']);

    Route::get('subscriptions', [BillingController::class, 'subscriptions']);
    Route::post('subscriptions/{subscription}/cancel', [BillingController::class, 'cancelSubscription']);
    Route::post('subscriptions/{subscription}/auto-renew', [BillingController::class, 'autoRenew']);
    Route::get('usage', [BillingController::class, 'usage']);
    Route::get('dunning', [BillingController::class, 'dunning']);

    Route::get('invoices', [InvoiceController::class, 'index']);
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf']);
    Route::get('invoices/{invoice}/ubl', [InvoiceController::class, 'ubl']);
    Route::post('invoices/{invoice}/pay', [InvoiceController::class, 'pay']);
    Route::post('invoices/{invoice}/credit-note', [InvoiceController::class, 'creditNote']);
    Route::post('invoices/{invoice}/mark-paid', [InvoiceController::class, 'markPaid']);

    Route::get('services', [ServiceController::class, 'index']);
    // archives of cancelled services (audit §5ab): free restore onto a new paid service, paid download of the compressed file
    Route::get('services/archives', [ArchiveController::class, 'index']);
    Route::post('services/archives/{backup}/download', [ArchiveController::class, 'download']);
    Route::post('services/archives/{backup}/restore', [ArchiveController::class, 'restore']);
    Route::get('services/{service}', [ServiceController::class, 'show']);
    Route::post('services/{service}/project', [ProjectController::class, 'assignService']);
    Route::get('services/{service}/plans', [ServiceController::class, 'plans']);
    Route::put('services/{service}/policy', [ServiceController::class, 'policy']);
    Route::get('services/{service}/access', [ServiceAccessController::class, 'index']);      // one service shared with another person
    Route::post('services/{service}/access', [ServiceAccessController::class, 'store']);
    Route::delete('services/{service}/access/{grant}', [ServiceAccessController::class, 'destroy']);
    Route::get('services/{service}/spec', [ServiceController::class, 'spec']);
    Route::put('services/{service}/spec', [ServiceController::class, 'applySpec']);
    Route::put('services/{service}/migration', [ServiceController::class, 'migrationWindow']);
    // chargeback in credit: request → support decides → the customer cancels and the agreed share of the unused period comes back
    Route::post('services/{service}/mailbox-password-link', [ServiceController::class, 'mailboxPasswordLink']); // a one-time link for the mailbox user to set their password
    Route::get('services/{service}/chargeback', [ServiceController::class, 'chargeback']);
    Route::post('services/{service}/chargeback', [ServiceController::class, 'requestChargeback']);
    Route::post('services/{service}/chargeback/cancel', [ServiceController::class, 'cancelWithChargeback']);
    Route::get('services/{service}/ssh-keys', [ServiceController::class, 'sshKeys']); // whose key sits on which shell account, and revocations the panel has not taken yet (H185)
    Route::post('services/{service}/actions', [ServiceController::class, 'action']);
    Route::post('services/{service}/game-files/upload', [ServiceController::class, 'uploadFile']); // binary files to a game server, scanned first (audit §5r-3/§5r-4)
    foreach (['power', 'resize', 'backup', 'restore', 'snapshot', 'suspend', 'resume', 'terminate'] as $shorthand) {
        Route::post("services/{service}/{$shorthand}", [ServiceController::class, 'action'])->defaults('action', $shorthand);
    }
    Route::get('services/{service}/console-token', [ServiceController::class, 'consoleToken']);
    Route::post('services/{service}/console-token', [ServiceController::class, 'consoleToken']);
    Route::get('services/{service}/usage', [ServiceController::class, 'usage']);
    Route::get('services/{service}/operations', [ServiceController::class, 'operations']);
    Route::get('services/{service}/backups', [ServiceController::class, 'backups']);
    Route::get('services/{service}/features', [ServiceController::class, 'features']);
    Route::get('services/{service}/resources/{kind}', [ServiceController::class, 'resources'])->where('kind', '[a-z_]+');
    Route::get('services/{service}/logs', [ServiceController::class, 'logs']);
    Route::get('services/{service}/files/download', [ServiceController::class, 'fileDownload']);
    // web toolkit: uploads/downloads through the control plane, monitoring, git deploy, backup schedule
    Route::post('services/{service}/uploads', [WebToolsController::class, 'upload']);
    Route::post('services/{service}/files/upload', [WebToolsController::class, 'fileUpload']);
    Route::get('services/{service}/downloads/{token}', [WebToolsController::class, 'download'])->where('token', 'dl_[A-Za-z0-9]+');
    Route::get('services/{service}/backups/{backup}/download', [WebToolsController::class, 'backupDownload']);
    Route::put('services/{service}/backups/schedule', [WebToolsController::class, 'setBackupSchedule']);
    Route::get('services/{service}/monitoring', [WebToolsController::class, 'monitoring']);
    Route::put('services/{service}/monitoring', [WebToolsController::class, 'setMonitoring']);
    Route::delete('services/{service}/monitoring/{monitor}', [WebToolsController::class, 'deleteMonitoring']);
    Route::get('services/{service}/deploy', [WebToolsController::class, 'deploy']);
    Route::put('services/{service}/deploy', [WebToolsController::class, 'configureDeploy']);
    Route::post('services/{service}/deploy/rotate-secret', [WebToolsController::class, 'rotateDeploySecret']);
    Route::delete('services/{service}/deploy', [WebToolsController::class, 'disconnectDeploy']);
    // integrations: Discord account link, action hooks
    Route::get('integrations/discord', [IntegrationController::class, 'discord']);
    Route::post('integrations/discord/link-code', [IntegrationController::class, 'discordLinkCode']);
    Route::delete('integrations/discord/links/{link}', [IntegrationController::class, 'discordUnlink']);
    Route::get('hooks/actions', [IntegrationController::class, 'hooks']);
    Route::post('hooks/actions', [IntegrationController::class, 'createHook']);
    Route::delete('hooks/actions/{hook}', [IntegrationController::class, 'deleteHook']);

    Route::get('domains', [DomainController::class, 'index']);
    Route::get('domains/contacts', [DomainController::class, 'contacts']);
    Route::post('domains/contacts', [DomainController::class, 'createContact']);
    Route::post('domains/transfer-in', [DomainController::class, 'transferIn']);
    Route::get('domains/{domain}', [DomainController::class, 'show']);
    Route::post('domains/{domain}/renew', [DomainController::class, 'renew']);
    Route::post('domains/{domain}/nameservers', [DomainController::class, 'nameservers']);
    Route::post('domains/{domain}/use-onhost-dns', [DomainController::class, 'useOnhostDns']);
    Route::post('domains/{domain}/auto-renew', [DomainController::class, 'autoRenew']);
    Route::post('domains/{domain}/transfer-lock', [DomainController::class, 'transferLock']);
    Route::post('domains/{domain}/auth-info', [DomainController::class, 'authInfo']);
    Route::post('domains/{domain}/dnssec/publish', [DomainController::class, 'publishDs']);
    Route::post('domains/{domain}/pair', [RegistrarConnectionController::class, 'pair']);
    Route::post('domains/{domain}/unpair', [RegistrarConnectionController::class, 'unpair']);

    // connected registrar accounts (bring your own WEDOS API): mirrored domains, zones, notices, credit watch, hosting pairing
    Route::get('registrar-connections', [RegistrarConnectionController::class, 'index']);
    Route::post('registrar-connections', [RegistrarConnectionController::class, 'store']);
    Route::get('registrar-connections/{connection}', [RegistrarConnectionController::class, 'show']);
    Route::get('registrar-connections/{connection}/history', [RegistrarConnectionController::class, 'history']);
    Route::post('registrar-connections/{connection}/probe', [RegistrarConnectionController::class, 'probe']);
    Route::post('registrar-connections/{connection}/sync', [RegistrarConnectionController::class, 'sync']);
    Route::patch('registrar-connections/{connection}', [RegistrarConnectionController::class, 'update']);
    Route::delete('registrar-connections/{connection}', [RegistrarConnectionController::class, 'destroy']);

    Route::get('dns/zones', [DnsController::class, 'index']);
    Route::post('dns/zones', [DnsController::class, 'store']);
    Route::get('dns/zones/{zone}', [DnsController::class, 'show']);
    Route::delete('dns/zones/{zone}', [DnsController::class, 'destroy']);
    Route::post('dns/zones/{zone}/changes', [DnsController::class, 'stage']);
    Route::get('dns/zones/{zone}/preview', [DnsController::class, 'preview']);
    Route::post('dns/zones/{zone}/commit', [DnsController::class, 'commit']);
    Route::post('dns/zones/{zone}/discard', [DnsController::class, 'discard']);
    Route::get('dns/zones/{zone}/versions', [DnsController::class, 'versions']);
    Route::post('dns/zones/{zone}/rollback', [DnsController::class, 'rollback']);
    Route::post('dns/zones/{zone}/dnssec', [DnsController::class, 'dnssec']);
    Route::get('dns/zones/{zone}/export', [DnsController::class, 'export']);
    // handoff aliases: /v1/domains/{name}/zone[/changes|/commit]
    Route::get('domains/{zone}/zone', [DnsController::class, 'show']);
    Route::post('domains/{zone}/zone/changes', [DnsController::class, 'stage']);
    Route::post('domains/{zone}/zone/commit', [DnsController::class, 'commit']);

    // ── staff ────────────────────────────────────────────────────────────────
    Route::prefix('staff')->group(function (): void {
        Route::get('customers', [CustomerController::class, 'index']);
        Route::get('customers/{organization}', [CustomerController::class, 'show']);
        Route::get('orders', [CustomerController::class, 'orders']);
        Route::get('orders/risk-review', [CustomerController::class, 'riskReview']);
        Route::post('orders/{order}/review', [CustomerController::class, 'reviewOrder']);
        Route::post('customers/{organization}/sandbox', [CustomerController::class, 'sandbox']); // sandbox tenant (audit §5j-9)
        Route::post('customers/{organization}/wallet/credit', [CustomerController::class, 'creditWallet']); // manual credit (audit §5y)
        Route::post('customers/{organization}/orders/quote', [CustomerController::class, 'quoteOrder']);
        Route::post('customers/{organization}/orders', [CustomerController::class, 'placeOrder']); // assisted order (audit §5y)
        Route::post('customers/{organization}/services', [CustomerController::class, 'createService']); // a service without an order (audit §5o)
        Route::get('services', [CustomerController::class, 'services']);
        Route::get('domains', [CustomerController::class, 'domains']);
        Route::get('integrations', [ProvisioningController::class, 'integrations']);
        Route::post('integrations', [ProvisioningController::class, 'upsertInstance']);
        Route::get('integrations/schema', [ProvisioningController::class, 'providerSchema']);
        Route::post('integrations/probe', [ProvisioningController::class, 'probe']);
        Route::get('integrations/{instance}', [ProvisioningController::class, 'instance']);
        Route::put('integrations/{instance}', [ProvisioningController::class, 'upsertInstance']);
        Route::post('integrations/{instance}/probe', [ProvisioningController::class, 'probeInstance']);
        Route::post('integrations/{instance}/state', [ProvisioningController::class, 'instanceState']);
        Route::post('integrations/{instance}/discover', [ProvisioningController::class, 'discoverNodes']);
        Route::post('integrations/{instance}/nodes', [ProvisioningController::class, 'upsertNode']);
        Route::post('integrations/{instance}/nodes/{node}/state', [ProvisioningController::class, 'nodeState']);
        Route::post('integrations/{instance}/prerequisites', [ProvisioningController::class, 'prerequisites']);
        // staff console views (audit §5f-2): game panels, automation rules, renewals ahead, the scheduler and bulk jobs
        Route::get('game', [ConsoleController::class, 'game']);
        // chargebacks for support: the queue, the decision, the returned share
        Route::get('chargebacks', [ChargebackController::class, 'index']);
        Route::get('chargebacks/settings', [ChargebackController::class, 'settings']);
        Route::get('chargebacks/analytics', [ChargebackController::class, 'analytics'])->middleware('shed'); // why customers leave (audit §5j-6)
        Route::post('chargebacks/analyse', [ChargebackController::class, 'analyse']);
        Route::put('chargebacks/settings', [ChargebackController::class, 'updateSettings']);
        Route::post('chargebacks/{chargeback}/decide', [ChargebackController::class, 'decide']);
        // loyalty programme: the level table and manual awards
        Route::get('loyalty/levels', [RewardsController::class, 'levels']);
        Route::put('loyalty/levels', [RewardsController::class, 'setLevels']);
        Route::post('loyalty/award', [RewardsController::class, 'award']);
        Route::post('loyalty/streak/{organization}', [RewardsController::class, 'approveStreak']); // the streak discount (audit §5j-3)
        Route::get('loyalty/missions', [RewardsController::class, 'missionCatalogue']); // the missions catalogue (audit §5k-5)
        Route::put('loyalty/missions', [RewardsController::class, 'setMissionCatalogue']);
        Route::get('loyalty/campaigns', [RewardsController::class, 'campaigns']); // mission campaigns (audit §5l-5)
        Route::put('loyalty/campaigns', [RewardsController::class, 'setCampaigns']);
        Route::get('loyalty/campaigns/{campaign}/analytics', [RewardsController::class, 'campaignAnalytics'])->middleware('shed'); // what a campaign did (audit §5m-5)
        Route::post('loyalty/campaigns/forecast', [RewardsController::class, 'forecastCampaign']); // what a campaign may cost (audit §5n-5)
        Route::get('referrals', [RewardsController::class, 'referrals']); // referral fraud review (audit §5l-4)
        Route::post('referrals/{referral}/review', [RewardsController::class, 'reviewReferral']);
        // marketplace curation (audit §5j-1)
        Route::get('marketplace/listings', [StaffMarketplaceController::class, 'listings']);
        Route::post('marketplace/listings/{listing}/state', [StaffMarketplaceController::class, 'listingState']);
        Route::get('marketplace/orders', [StaffMarketplaceController::class, 'orders']);
        Route::post('marketplace/orders/{order}/resolve', [StaffMarketplaceController::class, 'resolve']);
        // node rebalancing (audit §5i): the plan and its execution through the migration sagas
        Route::get('provisioning/rebalance', [ProvisioningController::class, 'rebalancePlan']);
        Route::post('provisioning/rebalance', [ProvisioningController::class, 'rebalanceApply']);
        Route::put('integrations/{instance}/game/eggs', [ConsoleController::class, 'mapEgg']);
        Route::post('integrations/{instance}/game/eggs/sync', [ConsoleController::class, 'syncEggs']);
        Route::post('integrations/{instance}/game/bootstrap', [ConsoleController::class, 'bootstrap']);
        Route::post('integrations/{instance}/game/allocations', [ConsoleController::class, 'createAllocations']);
        Route::post('integrations/{instance}/game/nodes/{node}/evacuate', [ConsoleController::class, 'evacuate']);
        Route::get('game/operator-variables', [ConsoleController::class, 'operatorVariables']); // Steam account and other operator-held template variables (audit §5t-1)
        Route::put('game/operator-variables/{env}', [ConsoleController::class, 'setOperatorVariable']);
        Route::put('integrations/{instance}/game/nodes/{node}', [ConsoleController::class, 'updateNode']); // node limits through the panel API (audit §5q follow-up)
        Route::post('services/{service}/migrate', [ConsoleController::class, 'migrate']);
        Route::get('automation', [ConsoleController::class, 'automation']);
        Route::put('automation/order.risk/tuning', [ConsoleController::class, 'riskTuning']);
        Route::put('automation/{key}', [ConsoleController::class, 'toggleAutomation']);
        Route::get('renewals', [ConsoleController::class, 'renewals']);
        Route::get('jobs', [ConsoleController::class, 'jobs']);
        Route::get('provisioning/board', [ProvisioningController::class, 'board']);
        Route::get('bulk-jobs', [ProvisioningController::class, 'bulkJobs']);
        Route::post('bulk-jobs', [ProvisioningController::class, 'startBulkJob']);
        Route::get('bulk-jobs/{job}', [ProvisioningController::class, 'bulkJob']);
        Route::get('placements', [ProvisioningController::class, 'placements']);
        Route::put('placements', [ProvisioningController::class, 'upsertPlacement']);
        Route::delete('placements/{placement}', [ProvisioningController::class, 'deletePlacement']);
        Route::get('payments/bank', [PaymentsController::class, 'bank']);
        Route::post('payments/bank/lines', [PaymentsController::class, 'recordBankLine']);
        Route::post('payments/bank/sync', [PaymentsController::class, 'syncBank']);
        Route::get('registrars', [RegistrarController::class, 'index']);
        Route::post('registrars/costs/refresh', [RegistrarController::class, 'refresh']);
        Route::post('registrars/costs/scrape', [RegistrarController::class, 'scrape']);
        Route::put('registrars/costs', [RegistrarController::class, 'upsertCost']);
        Route::put('registrars/policy', [RegistrarController::class, 'setPolicy']);
        Route::get('registrar-connections', [RegistrarController::class, 'connections']);
        Route::post('registrar-connections/{connection}/sync', [RegistrarController::class, 'syncConnection']);
        Route::post('registrar-connections/{connection}/disable', [RegistrarController::class, 'disableConnection']);
        Route::post('registrar-connections/{connection}/enable', [RegistrarController::class, 'enableConnection']);
        Route::get('pricing', [PricingController::class, 'index']);
        Route::put('pricing/commit-discounts', [PricingController::class, 'setCommitDiscounts']);
        Route::put('pricing/regions', [PricingController::class, 'setRegions']); // regional pricing (audit §5j-8)
        Route::put('pricing/domain-discounts', [PricingController::class, 'setDomainDiscount']);
        Route::delete('pricing/domain-discounts/{tld}', [PricingController::class, 'deleteDomainDiscount']);
        Route::put('pricing/promo-codes', [PricingController::class, 'upsertPromo']);
        Route::delete('pricing/promo-codes/{code}', [PricingController::class, 'deletePromo']);
        Route::put('pricing/options', [PricingController::class, 'upsertOption']);
        Route::delete('pricing/options/{product}/{key}', [PricingController::class, 'deleteOption']);
        Route::put('pricing/addon-products', [PricingController::class, 'setAddonProducts']);
        Route::get('pricing/plans/{product}/{plan}/versions', [PricingController::class, 'planVersions']); // a plan is never edited: a change is a new version (H01)
        Route::post('pricing/plans/{product}/{plan}/versions', [PricingController::class, 'publishPlanVersion']);
        Route::post('pricing/plans/{product}/{plan}/versions/{version}/activate', [PricingController::class, 'activatePlanVersion'])->whereNumber('version');
        // customer panel sidebar: category switches, order, labels (admin "Navigace klientského panelu")
        // the deletion lifecycle: restore window, archive retention, download fee (audit §5ab)
        Route::get('settings/lifecycle', [PricingController::class, 'lifecycle']);
        Route::put('settings/lifecycle', [PricingController::class, 'setLifecycle']);
        Route::get('settings/panel-nav', [PricingController::class, 'panelNav']);
        Route::put('settings/panel-nav', [PricingController::class, 'setPanelNav']);
        Route::get('capacity', [ProvisioningController::class, 'capacity']);
        Route::get('capacity/requests', [ProvisioningController::class, 'capacityRequests']); // capacity requests from the forecast (audit §5n-7)
        Route::post('capacity/requests/{capacityRequest}/decide', [ProvisioningController::class, 'decideCapacityRequest']);
        Route::post('capacity/forecast/run', [ProvisioningController::class, 'runCapacityForecast']); // the daily pass on demand from the console (audit §5o)
        Route::get('capacity/budget', [ProvisioningController::class, 'capacityBudget']); // the monthly cap on vendor node orders (audit §5q-5)
        Route::put('capacity/budget', [ProvisioningController::class, 'setCapacityBudget']);
        Route::get('oncall/alerts', [OnCallController::class, 'index']); // on-call alerts with escalation (audit §5q-1)
        Route::post('oncall/alerts/{alert}/ack', [OnCallController::class, 'acknowledge']);
        Route::post('oncall/alerts/{alert}/resolve', [OnCallController::class, 'resolve']);
        Route::post('oncall/test', [OnCallController::class, 'test']);
        Route::get('oncall/shifts', [OnCallController::class, 'shifts']); // the on-call rota (audit §5r-1)
        Route::get('oncall/shifts.ics', [OnCallController::class, 'shiftsIcal']); // iCalendar export (audit §5t-4)
        Route::post('oncall/shifts/import', [OnCallController::class, 'importShifts']);
        Route::post('oncall/feed-token', [OnCallController::class, 'feedToken']); // personal calendar subscription (audit §5u-4)
        Route::post('oncall/shifts', [OnCallController::class, 'addShift']);
        Route::delete('oncall/shifts/{shift}', [OnCallController::class, 'removeShift']);
        Route::get('provisioning/jobs', [ProvisioningController::class, 'operations']);
        Route::get('provisioning/jobs/{operation}', [ProvisioningController::class, 'operation']);
        Route::post('provisioning/jobs/{operation}/retry', [ProvisioningController::class, 'retry']);
        Route::post('provisioning/jobs/{operation}/cancel', [ProvisioningController::class, 'cancel']);
        Route::post('provisioning/freeze', [ProvisioningController::class, 'freeze']);
        Route::post('provisioning/thaw', [ProvisioningController::class, 'thaw']);
        Route::get('provisioning/load', [ProvisioningController::class, 'load']); // are overviews being shed, and why (H139)
        Route::put('provisioning/load', [ProvisioningController::class, 'setLoad']);
        Route::post('provisioning/services/{service}/reconcile', [ProvisioningController::class, 'reconcile']);
        // the deletion lifecycle board and the early removal (audit §5ab)
        Route::get('provisioning/deletions', [ProvisioningController::class, 'deletions']);
        Route::get('provisioning/ssh-key-revocations', [ProvisioningController::class, 'sshKeyRevocations']); // keys that may still open a session (H185)
        Route::post('provisioning/services/{service}/purge', [ProvisioningController::class, 'purgeService']);
        Route::get('services/{service}/panel-login', [WebToolsController::class, 'panelLogin']); // staff SSO into the customer's hosting panel (audited)
        Route::get('resource-mappings', [ProvisioningController::class, 'drifts']);
        Route::post('resource-mappings/{drift}/resolve', [ProvisioningController::class, 'resolveDrift']);
        Route::post('assistant/chat', [StaffSupportController::class, 'assistant']); // the assistant over one customer's account, for support and NOC
        Route::get('approvals', [ApprovalController::class, 'index']); // four eyes: requests for a second person (ApprovalService)
        Route::post('approvals/{approval}/decision', [ApprovalController::class, 'decide']);
        Route::get('tickets', [StaffSupportController::class, 'index']);
        Route::get('tickets/clusters', [StaffSupportController::class, 'clusters']);
        Route::get('tickets/macros', [StaffSupportController::class, 'macros']);
        Route::post('tickets/sla-tick', [StaffSupportController::class, 'sla']);
        Route::get('tickets/{ticket}', [StaffSupportController::class, 'show']);
        Route::post('tickets/{ticket}/messages', [StaffSupportController::class, 'reply']);
        Route::post('tickets/{ticket}/transition', [StaffSupportController::class, 'transition']);
        Route::post('tickets/{ticket}/assign', [StaffSupportController::class, 'assign']);
        Route::post('tickets/{ticket}/escalate', [StaffSupportController::class, 'escalate']);
        Route::get('tickets/{ticket}/work-offers', [StaffSupportController::class, 'workOffers']);
        Route::post('tickets/{ticket}/work-offers', [StaffSupportController::class, 'proposeWork']);
        Route::post('tickets/{ticket}/work-offers/{offer}/withdraw', [StaffSupportController::class, 'withdrawWork']);
        Route::post('tickets/{ticket}/work-offers/{offer}/complete', [StaffSupportController::class, 'completeWork']);
        Route::get('outbox', [NotificationController::class, 'outbox']);
        Route::post('outbox/{mail}/send', [NotificationController::class, 'sendMail']);
        Route::get('templates', [NotificationController::class, 'templates']);
        Route::post('templates/render', [NotificationController::class, 'testRender']);
        Route::get('reports/mrr', [ReportController::class, 'mrr'])->middleware('shed');
        Route::get('reports/collections', [ReportController::class, 'collections'])->middleware('shed');
        Route::get('reports/churn', [ReportController::class, 'churn'])->middleware('shed');
        Route::get('reports/revenue', [ReportController::class, 'revenue'])->middleware('shed');
        Route::get('dunning', [ReportController::class, 'dunning']);
        Route::post('dunning/run', [ReportController::class, 'runDunning']);

        // incidents / status / maintenance / SLO / SLA credits (admin #/incidenty)
        Route::get('incidents', [StaffIncidentController::class, 'index']);
        Route::post('incidents', [StaffIncidentController::class, 'open']);
        Route::get('incidents/metrics', [StaffIncidentController::class, 'metrics']);
        Route::get('incidents/components', [StaffIncidentController::class, 'components']);
        Route::get('incidents/{incident}', [StaffIncidentController::class, 'show']);
        Route::post('incidents/{incident}/updates', [StaffIncidentController::class, 'update']);
        Route::post('incidents/{incident}/resolve', [StaffIncidentController::class, 'resolve']);
        Route::post('incidents/{incident}/postmortem', [StaffIncidentController::class, 'postmortem']);
        Route::post('incidents/{incident}/sla-credits', [StaffIncidentController::class, 'creditCandidates']);
        Route::get('maintenance', [StaffIncidentController::class, 'maintenances']);
        Route::post('maintenance', [StaffIncidentController::class, 'scheduleMaintenance']);
        Route::post('maintenance/{maintenance}/approve', [StaffIncidentController::class, 'approveMaintenance']);
        Route::post('maintenance/{maintenance}/cancel', [StaffIncidentController::class, 'cancelMaintenance']);
        Route::post('maintenance/{maintenance}/complete', [StaffIncidentController::class, 'completeMaintenance']);
        Route::get('probes', [StaffIncidentController::class, 'probes']);
        Route::post('probes', [StaffIncidentController::class, 'registerProbe']);
        Route::get('reports/slo', [StaffIncidentController::class, 'slo']);
        Route::get('sla-credits', [StaffIncidentController::class, 'credits']);
        Route::post('sla-credits/{credit}/approve', [StaffIncidentController::class, 'approveCredit']);
        Route::post('sla-credits/{credit}/reject', [StaffIncidentController::class, 'rejectCredit']);
        Route::post('sla-credits/{credit}/issue', [StaffIncidentController::class, 'issueCredit']);

        // compliance: cyber incidents, regulatory timers, DSA abuse cases, data requests, legal hold
        Route::get('security/incidents', [StaffComplianceController::class, 'cyberIncidents']);
        Route::post('security/incidents', [StaffComplianceController::class, 'openCyberIncident']);
        Route::get('security/incidents/{case}', [StaffComplianceController::class, 'cyberIncident']);
        Route::post('security/incidents/{case}/transition', [StaffComplianceController::class, 'transitionCyberIncident']);
        Route::post('security/incidents/{case}/evidence', [StaffComplianceController::class, 'evidence']);
        Route::get('compliance/timers', [StaffComplianceController::class, 'timers']);
        Route::post('compliance/timers/{timer}/submit', [StaffComplianceController::class, 'submitTimer']);
        Route::post('compliance/timers/{timer}/waive', [StaffComplianceController::class, 'waiveTimer']);
        Route::get('abuse-cases', [StaffComplianceController::class, 'abuseCases']);
        Route::get('abuse-cases/{case}', [StaffComplianceController::class, 'abuseCase']);
        Route::post('abuse-cases/{case}/triage', [StaffComplianceController::class, 'triageAbuse']);
        Route::post('abuse-cases/{case}/notify', [StaffComplianceController::class, 'notifyAbuse']);
        Route::post('abuse-cases/{case}/action', [StaffComplianceController::class, 'actionAbuse']);
        Route::post('abuse-cases/{case}/close', [StaffComplianceController::class, 'closeAbuse']);
        Route::get('data-requests', [StaffComplianceController::class, 'dataRequests']);
        Route::post('data-requests/process', [StaffComplianceController::class, 'processDataRequests']);
        Route::post('customers/{organization}/legal-hold', [StaffComplianceController::class, 'legalHold']);

        // partners, leads and public content
        Route::get('partners', [StaffPartnerController::class, 'index']);
        Route::get('partners/payouts', [StaffPartnerController::class, 'payouts']);
        Route::post('partners/payouts/{payout}/approve', [StaffPartnerController::class, 'approvePayout']);
        Route::post('partners/payouts/{payout}/reject', [StaffPartnerController::class, 'rejectPayout']);
        Route::post('partners/payouts/{payout}/pay', [StaffPartnerController::class, 'payPayout']);
        Route::post('partners/tiers/recompute', [StaffPartnerController::class, 'recomputeTiers']);
        Route::get('partners/requests', [StaffPartnerController::class, 'requests']); // contract change requests (audit §5m-1)
        Route::post('partners/requests/{partnerRequest}/decide', [StaffPartnerController::class, 'decideRequest']);
        Route::get('partners/{partner}', [StaffPartnerController::class, 'show']);
        Route::post('partners/{partner}/approve', [StaffPartnerController::class, 'approve']);
        Route::post('partners/{partner}/state', [StaffPartnerController::class, 'state']);
        Route::get('leads', [StaffContentController::class, 'leads']);
        Route::post('leads/{lead}/transition', [StaffContentController::class, 'transitionLead']);
        Route::put('content/posts', [StaffContentController::class, 'upsertPost']);
        Route::put('content/kb', [StaffContentController::class, 'upsertArticle']);
        Route::put('content/changelog', [StaffContentController::class, 'upsertChangelog']);
        Route::put('content/stock', [StaffContentController::class, 'upsertStock']);
    });
});
