<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Customer\Models\Customer;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\DomainRegistration;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Support\Models\SupportTicket;
use App\Models\CustomerCommunicationLog;
use App\Models\CustomerOnboardingStep;
use App\Models\ServiceBackupLog;
use App\Models\ServiceFirewallRule;
use App\Models\ServiceHealthIncident;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo data for the LOCAL preview environment: one demo customer with one
 * service per provisioning platform plus related agendas, so every detail
 * page has real content to render. Never intended for production.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('DemoDataSeeder refuses to run in production.');

            return;
        }

        $user = User::query()->firstOrCreate(
            ['email' => 'demo@onhost.local'],
            [
                'name'              => 'Demo Zákazník',
                'password'          => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $customer = Customer::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'email'              => $user->email,
                'company_name'       => 'Demo s.r.o.',
                'country_code'       => 'CZ',
                'preferred_currency' => 'CZK',
                'type'               => 'company',
            ],
        );

        $definitions = [
            ['driver' => ProvisioningDriver::AAPanel,     'label' => 'demo-web.cz',        'external_id' => 'AAP-1001', 'resources' => ['disk_mb' => 20_480, 'bandwidth_gb' => 200, 'databases' => 5, 'emails' => 25, 'php_version' => '82']],
            ['driver' => ProvisioningDriver::Proxmox,     'label' => 'demo-vps-01',        'external_id' => '100',      'resources' => ['cpu' => 2, 'ram_mb' => 4_096, 'disk_mb' => 81_920]],
            ['driver' => ProvisioningDriver::Pterodactyl, 'label' => 'demo-minecraft',     'external_id' => '1',        'resources' => ['slots' => 20, 'ram_mb' => 4_096]],
            ['driver' => ProvisioningDriver::Wedos,       'label' => 'demo-domena.cz',     'external_id' => 'demo-domena.cz', 'resources' => null],
        ];

        $services = [];

        foreach ($definitions as $def) {
            $services[$def['driver']->value] = Service::query()->firstOrCreate(
                ['customer_id' => $customer->id, 'label' => $def['label']],
                [
                    'product_id'          => \App\Domains\Products\Models\Product::query()->value('id'),
                    'provisioning_driver' => $def['driver'],
                    'external_id'         => $def['external_id'],
                    'status'              => ServiceStatus::Active,
                    'resources'           => $def['resources'],
                    'next_due_date'       => now()->addMonth(),
                ],
            );
        }

        $web = $services['aapanel'];
        $vps = $services['proxmox'];

        DomainRegistration::query()->firstOrCreate(
            ['service_id' => $services['wedos']->id],
            [
                'domain'      => 'demo-domena',
                'tld'         => 'cz',
                'registrar'   => 'wedos',
                'expires_at'  => now()->addMonths(11),
                'auto_renew'  => true,
                'nameservers' => ['ns1.onhost.cz', 'ns2.onhost.cz'],
            ],
        );

        if (Invoice::query()->where('customer_id', $customer->id)->count() === 0) {
            Invoice::factory()->create(['customer_id' => $customer->id, 'status' => InvoiceStatus::Paid,    'paid_at' => now()->subDays(20)]);
            Invoice::factory()->create(['customer_id' => $customer->id, 'status' => InvoiceStatus::Sent]);
        }

        if (SupportTicket::query()->where('customer_id', $customer->id)->count() === 0) {
            SupportTicket::factory()->create([
                'customer_id' => $customer->id,
                'subject'     => 'Dotaz k nastavení e-mailů',
            ]);
        }

        ServiceFirewallRule::query()->firstOrCreate(
            ['service_id' => $vps->id, 'ip_cidr' => '0.0.0.0/0', 'port_from' => 443],
            ['direction' => 'in', 'protocol' => 'tcp', 'port_to' => 443, 'action' => 'allow', 'is_active' => true],
        );

        ServiceHealthIncident::query()->firstOrCreate(
            ['service_id' => $web->id, 'title' => 'Zvýšená zátěž disku'],
            ['severity' => 'warning', 'status' => 'open'],
        );

        if (ServiceBackupLog::query()->where('service_id', $web->id)->count() === 0) {
            ServiceBackupLog::create([
                'service_id'       => $web->id,
                'status'           => 'success',
                'size_bytes'       => 734_003_200,
                'duration_seconds' => 61,
                'started_at'       => now()->subDay(),
                'completed_at'     => now()->subDay()->addSeconds(61),
            ]);
        }

        foreach ([['Ověření e-mailu', true, true], ['První objednávka', true, true], ['Nastavení DNS', false, false]] as [$step, $required, $done]) {
            CustomerOnboardingStep::query()->firstOrCreate(
                ['customer_id' => $customer->id, 'step' => $step],
                ['is_required' => $required, 'completed_at' => $done ? now()->subDays(3) : null],
            );
        }

        CustomerCommunicationLog::query()->firstOrCreate(
            ['customer_id' => $customer->id, 'subject' => 'Uvítací hovor'],
            ['channel' => 'phone', 'direction' => 'outbound', 'body' => 'Proveden onboarding hovor, zákazník spokojen.'],
        );
    }
}
