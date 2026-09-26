<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Incidents\IncidentService;
use Onhost\Domain\Incidents\MaintenanceService;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Partners\PartnerService;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Support\TicketService;
use Onhost\Domain\Tax\Models\VatValidation;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;
use RuntimeException;

/**
 * Local/staging accounts and a realistic customer portfolio for walking through the surfaces.
 * Refuses to run in production. Passwords are documented in README (dev only).
 *
 *  demo@onhost.cz / Demo-heslo-2026!      customer (owner of "Demo s.r.o.")
 *  agentura@onhost.cz / Demo-heslo-2026!  partner (Agentura Pixel s.r.o., referral of Demo s.r.o.)
 *  admin@onhost.cz / Admin-heslo-2026!    platform owner · noc@ (sre) · finance@ (billing) · support@ (support manager)
 */
final class DevAccountSeeder extends Seeder
{
    public const PASSWORD = 'Demo-heslo-2026!';

    public const STAFF_PASSWORD = 'Admin-heslo-2026!';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DevAccountSeeder must never run in production.');
        }
        if (User::query()->where('email', 'demo@onhost.cz')->exists()) {
            $this->command?->info('dev accounts already present');
            $this->backfillPlanVersions();

            return;
        }
        $ctx = CommandContext::system('dev-seed');
        $organizations = app(OrganizationService::class);

        // ── staff ──────────────────────────────────────────────────────────
        foreach ([['admin@onhost.cz', 'Alena Správcová', 'platform_owner'], ['noc@onhost.cz', 'Marek Šimek', 'sre'], ['finance@onhost.cz', 'Jana Účetní', 'billing_finance_admin'], ['support@onhost.cz', 'Petr Podpora', 'support_manager']] as [$email, $name, $role]) {
            $staff = $this->user($email, $name, self::STAFF_PASSWORD, true);
            PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $staff->id, 'role_key' => $role, 'scope_type' => 'global', 'scope_id' => null, 'organization_id' => null]);
        }

        // ── partner ────────────────────────────────────────────────────────
        $partnerOwner = $this->user('agentura@onhost.cz', 'Tomáš Pixel', self::PASSWORD, false);
        $partnerOrg = $organizations->create($partnerOwner, ['name' => 'Agentura Pixel s.r.o.', 'type' => 'company', 'country' => 'CZ', 'currency' => 'CZK', 'ico' => '27076551', 'street' => 'Křižíkova 148', 'city' => 'Praha', 'postal_code' => '18600', 'billing_email' => 'agentura@onhost.cz'], $ctx);
        // dev data, no VIES call: a VAT payer in the one vocabulary (TASK-0031), as if VIES had confirmed the DIČ today
        $partnerOrg->forceFill(['dic' => 'CZ27076551', 'vat_id' => 'CZ27076551', 'vat_status' => 'valid', 'vat_status_source' => 'vies', 'vat_checked_at' => now(), 'vat_checked_number' => 'CZ27076551'])->save();
        // … and finance's confirmation of the supplier, which VAT paid out to a Czech partner needs (TASK-0031 closing review)
        VatValidation::query()->create(['organization_id' => $partnerOrg->id, 'vat_id' => 'CZ27076551', 'valid' => true, 'status' => 'valid', 'source' => 'staff', 'reason' => 'staff',
            'name' => 'Agentura Pixel s.r.o.', 'note' => 'dev data', 'evidence' => 'dev data', 'actor' => 'seeder', 'country_code' => 'CZ', 'checked_at' => now()->subDays(40), 'expires_at' => now()->subDays(10)]);
        $partners = app(PartnerService::class);
        $partner = $partners->approve($partners->apply($partnerOrg, ['model' => 'share', 'company' => 'Agentura Pixel s.r.o.', 'clients' => '11–50'], $ctx), $ctx);

        // ── customer ───────────────────────────────────────────────────────
        $owner = $this->user('demo@onhost.cz', 'Jana Nováková', self::PASSWORD, false);
        $org = $organizations->create($owner, ['name' => 'Demo s.r.o.', 'type' => 'company', 'country' => 'CZ', 'currency' => 'CZK', 'ico' => '12345678', 'street' => 'Vodičkova 12', 'city' => 'Praha', 'postal_code' => '11000', 'billing_email' => 'demo@onhost.cz'], $ctx);
        $partners->attribute($org, $partner->code, $ctx);
        app(WalletService::class)->topup($org, Money::decimal('5000', 'CZK'), 'bank', 'dev-seed-topup', $ctx, null, 'Počáteční kredit (dev)');

        $pve = ProviderInstance::query()->where('key', 'proxmox-cz1')->first();
        $isp = ProviderInstance::query()->where('key', 'ispconfig-shared01')->first();
        $ptero = ProviderInstance::query()->where('key', 'pterodactyl-games01')->first();
        $node = Node::query()->where('provider_instance_id', $pve?->id)->first();

        $vps = $this->service($org, ['product_key' => 'vps', 'family' => 'cloud', 'name' => 'VPS 4/8', 'label' => 'app-prod', 'hostname' => 'app-prod.demo.onhost.cloud', 'region_code' => 'cz1', 'provider_instance_id' => $pve?->id, 'node_id' => $node?->id, 'sla_class' => 'business',
            'desired_spec' => ['vcpu' => 4, 'ram_mb' => 8192, 'nvme_gb' => 160, 'image' => 'debian-12', 'hostname' => 'app-prod'], 'tags' => ['access' => ['ipv4' => '192.0.2.21', 'ipv6' => '2001:db8:10::21', 'ssh' => 'root@192.0.2.21']], 'health' => ['cpu_pct' => 41, 'ram_pct' => 62, 'net_pct' => 18, 'checked_at' => now()->toIso8601String()], 'activated_at' => now()->subMonths(4)], 249000);
        if ($pve !== null) {
            ProviderBinding::query()->create(['service_id' => $vps->id, 'provider_instance_id' => $pve->id, 'remote_type' => 'qemu', 'remote_id' => '1042', 'remote_node' => $node?->name ?? 'prg1-n1', 'meta' => ['name' => 'onhost-'.$vps->id], 'ownership' => ['cores' => 'ONHOST_MANAGED', 'memory' => 'ONHOST_MANAGED'], 'idempotency_key' => 'dev-vps', 'adapter_version' => '1.0.0']);
        }
        $web = $this->service($org, ['plan_key' => 'profi', 'product_key' => 'web-hosting', 'family' => 'web', 'name' => 'Webhosting Pro', 'label' => 'demo-web.cz', 'hostname' => 'demo-web.cz', 'region_code' => 'cz1', 'provider_instance_id' => $isp?->id, 'sla_class' => 'standard',
            'desired_spec' => ['executor' => 'ispconfig', 'domain' => 'demo-web.cz', 'php_version' => '8.4'], 'entitlements' => ['quota_mb' => 20480, 'mailboxes' => 10, 'databases' => 5], 'tags' => ['access' => ['ipv4' => '192.0.2.10', 'ftp_host' => 'shared01.onhost.cloud']], 'health' => ['checked_at' => now()->toIso8601String()], 'activated_at' => now()->subMonths(9)], 44900);
        if ($isp !== null) {
            ProviderBinding::query()->create(['service_id' => $web->id, 'provider_instance_id' => $isp->id, 'remote_type' => 'web_domain', 'remote_id' => '17', 'remote_node' => null, 'meta' => ['client_id' => 5], 'ownership' => [], 'idempotency_key' => 'dev-web', 'adapter_version' => '1.0.0']);
        }
        $mail = $this->service($org, ['plan_key' => 'mail-business', 'product_key' => 'mail-hosting', 'family' => 'mail', 'name' => 'Mail Business', 'label' => 'demo-web.cz · pošta', 'hostname' => 'demo-web.cz', 'region_code' => 'cz1', 'provider_instance_id' => $isp?->id, 'sla_class' => 'standard',
            'desired_spec' => ['executor' => 'ispconfig', 'family' => 'mail', 'domain' => 'demo-web.cz'], 'entitlements' => ['mailboxes' => 10, 'aliases' => 50, 'quota_gb_per_mailbox' => 10], 'tags' => ['access' => ['imap' => 'mail.onhost.cloud', 'smtp' => 'mail.onhost.cloud', 'webmail' => 'https://webmail.onhost.cloud']], 'health' => ['checked_at' => now()->toIso8601String()], 'activated_at' => now()->subDays(30)], 9900);
        if ($isp !== null) {
            ProviderBinding::query()->create(['service_id' => $mail->id, 'provider_instance_id' => $isp->id, 'remote_type' => 'mail_domain', 'remote_id' => '5', 'remote_node' => '1', 'meta' => ['client_id' => 5, 'domain' => 'demo-web.cz'], 'ownership' => [], 'idempotency_key' => 'dev-mail', 'adapter_version' => '1.0.0']);
        }
        $game = $this->service($org, ['product_key' => 'game-server', 'family' => 'game', 'name' => 'Minecraft 8 GB', 'label' => 'mc-community', 'hostname' => 'mc.demo-web.cz', 'region_code' => 'cz1', 'provider_instance_id' => $ptero?->id, 'sla_class' => 'standard',
            'desired_spec' => ['egg' => 'minecraft-paper', 'ram_mb' => 8192, 'disk_mb' => 40960, 'slots' => 60], 'tags' => ['access' => ['ipv4' => '192.0.2.30', 'port' => 25565]], 'health' => ['cpu_pct' => 58, 'ram_pct' => 63, 'net_pct' => 47, 'players' => 24, 'checked_at' => now()->toIso8601String()], 'activated_at' => now()->subMonths(2)], 59000);
        if ($ptero !== null) {
            ProviderBinding::query()->create(['service_id' => $game->id, 'provider_instance_id' => $ptero->id, 'remote_type' => 'server', 'remote_id' => '7a1c2e3f-demo', 'remote_node' => null, 'meta' => ['identifier' => '7a1c2e3f'], 'ownership' => [], 'idempotency_key' => 'dev-game', 'adapter_version' => '1.0.0']);
        }

        foreach ([['demo-web.cz', now()->addDays(300), true], ['skladomat.cz', now()->addDays(9), true], ['retenza.io', now()->addDays(410), false]] as [$fqdn, $expires, $critical]) {
            Domain::query()->create(['organization_id' => $org->id, 'fqdn_ascii' => $fqdn, 'fqdn_unicode' => $fqdn, 'tld' => substr($fqdn, strrpos($fqdn, '.') + 1), 'registrar_provider' => 'wedos', 'registrar_remote_id' => 'dev-'.$fqdn, 'state' => 'ACTIVE', 'registered_at' => now()->subYear(), 'expires_at' => $expires, 'auto_renew' => true, 'renewal_period' => 1, 'dns_provider' => 'powerdns', 'nameservers' => ['ns.onhost.cz', 'ns2.onhost.cz', 'ns3.onhost.eu'], 'dnssec' => $critical, 'transfer_lock' => true, 'critical' => $critical]);
        }

        // documents: one paid invoice (commission for the partner), one open
        $invoices = app(InvoiceService::class);
        $paid = $invoices->issue($invoices->draft($org, 'invoice', 'CZK', [$this->line('vps-4-8', 'VPS 4/8 — app-prod · 8/2026', 249000, $vps->id)], $ctx, null, ['postpaid' => true]), $ctx);
        $invoices->markPaid($paid, $paid->total(), 'bank', $ctx, paymentReference: 'DEV-2026-0801');
        $invoices->issue($invoices->draft($org, 'invoice', 'CZK', [$this->line('web-hosting-pro', 'Webhosting Pro — demo-web.cz · 9/2026', 44900, $web->id), $this->line('game-server', 'Minecraft 8 GB — mc-community · 9/2026', 59000, $game->id)], $ctx, null, ['postpaid' => true]), $ctx);

        app(TicketService::class)->create(['subject' => 'Pomalé dotazy po migraci', 'body' => 'Po migraci databáze na app-prod trvají dotazy do pokladny přes 3 sekundy. Můžete se podívat na indexy?', 'service_id' => $vps->id, 'channel' => 'portal'], $ctx->withScope($org->id), $org, $owner);

        // status history: a resolved incident and a planned maintenance
        $incidents = app(IncidentService::class);
        $incident = $incidents->open(['title' => 'Zvýšená latence webhostingu CZ1', 'severity' => 'p2', 'components' => ['web-cz1'], 'impact' => 'Odezva webů na shared01 2–4 s, bez výpadku.', 'affected_services' => [$web->id], 'started_at' => now()->subDays(3)->subHours(2)->toIso8601String()], $ctx);
        $incidents->update($incident, 'Příčina: zaplněný disk logy jednoho zákazníka. Čistíme a nasazujeme limit.', $ctx, 'IDENTIFIED');
        $incidents->resolve($incident, 'Disk uvolněn, per-web limit logů nasazen. Latence zpět pod 300 ms.', $ctx);
        $incident->forceFill(['resolved_at' => now()->subDays(3)->subMinutes(35)])->save();
        app(MaintenanceService::class)->schedule(['title' => 'Výměna disků na shared01', 'components' => ['web-cz1'], 'starts_at' => now()->addDays(5)->setTime(1, 0)->toIso8601String(), 'ends_at' => now()->addDays(5)->setTime(3, 0)->toIso8601String(), 'impact' => 'Krátké výpadky do 5 minut při přepnutí pole.', 'rollback' => 'Vrátit původní disky, obnovit z PBS snapshotu.', 'affected_services' => [$web->id]], $ctx);

        app(OutboxPublisher::class)->relayPending();
        $this->command?->info('dev accounts: demo@onhost.cz, agentura@onhost.cz, admin@onhost.cz, noc@onhost.cz, finance@onhost.cz, support@onhost.cz');
    }

    private function user(string $email, string $name, string $password, bool $staff): User
    {
        return User::query()->create(['name' => $name, 'email' => $email, 'password' => $password, 'locale' => 'cs', 'timezone' => 'Europe/Prague', 'is_staff' => $staff, 'state' => 'active', 'email_verified_at' => now()]);
    }

    private function service(Organization $org, array $attributes, int $monthlyMinor): Service
    {
        $planKey = $attributes['plan_key'] ?? null;
        unset($attributes['plan_key']);
        $catalog = $planKey !== null ? $this->catalogVersion((string) $attributes['product_key'], (string) $planKey) : ['version' => null, 'price' => null];
        $service = Service::query()->create($attributes + ['organization_id' => $org->id, 'state' => ServiceStateMachine::ACTIVE, 'entitlements' => $attributes['entitlements'] ?? [], 'desired_spec' => $attributes['desired_spec'] ?? [], 'plan_version_id' => $catalog['version']]);
        $service->forceFill(['actual_spec' => $service->desired_spec, 'last_reconciled_at' => now()])->save();
        $start = now()->startOfMonth();
        $sub = Subscription::query()->create(['organization_id' => $org->id, 'service_id' => $service->id, 'plan_version_id' => $catalog['version'], 'price_id' => $catalog['price'], 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => $monthlyMinor, 'state' => 'active', 'current_period_start' => $start, 'current_period_end' => $start->copy()->addMonth(), 'next_renewal_at' => $start->copy()->addMonth()->subDays(7), 'last_renewed_at' => $start]);
        $service->forceFill(['subscription_id' => $sub->id])->save();

        return $service;
    }

    /** The catalogue's monthly plan version and price (null when the catalogue is not seeded): plan changes and period switches need them. @return array{version:?string, price:?string} */
    private function catalogVersion(string $productKey, string $planKey): array
    {
        try {
            $resolved = app(CatalogService::class)->resolve($productKey, $planKey, 'CZK', 'month');

            return ['version' => $resolved['version']->id, 'price' => $resolved['price']->id];
        } catch (\Throwable) {
            return ['version' => null, 'price' => null];
        }
    }

    /** Databases seeded before plan versions existed on demo services: attach the catalogue's plan to the demo web service (idempotent). */
    private function backfillPlanVersions(): void
    {
        $catalog = $this->catalogVersion('web-hosting', 'profi');
        if ($catalog['version'] === null) {
            return;
        }
        foreach (Service::query()->where('hostname', 'demo-web.cz')->where('product_key', 'web-hosting')->whereNull('plan_version_id')->get() as $service) {
            $service->forceFill(['plan_version_id' => $catalog['version']])->save();
            Subscription::query()->where('service_id', $service->id)->whereNull('plan_version_id')->update(['plan_version_id' => $catalog['version'], 'price_id' => $catalog['price']]);
            $this->command?->info("plan version attached to {$service->hostname}");
        }
    }

    private function line(string $sku, string $description, int $net, string $serviceId): array
    {
        $tax = (int) round($net * 0.21);

        return ['sku' => $sku, 'description' => $description, 'qty' => 1, 'unit' => 'ks', 'unit_net' => $net, 'discount' => 0, 'net' => $net, 'tax_rate' => '21', 'tax_category' => 'S', 'tax' => $tax, 'total' => $net + $tax, 'service_id' => $serviceId, 'period_from' => now()->startOfMonth()->toDateString(), 'period_to' => now()->endOfMonth()->toDateString()];
    }
}
