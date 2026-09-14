<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Onhost\Domain\Provisioning\Ipam\IpamService;
use Onhost\Domain\Provisioning\Models\IpPool;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Models\Region;

/**
 * Production infrastructure registry, driven entirely by the environment
 * (no fake endpoints ever land in a real database). Every instance points at
 * a secret reference (`bao://` in production, `env://` elsewhere) and is only
 * created when its base URL is configured. Re-runnable.
 */
final class InfrastructureSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->regions() as $code => $region) {
            Region::query()->updateOrCreate(['code' => $code], $region);
        }
        $secrets = (string) env('ONHOST_SECRETS_DRIVER', 'env') === 'openbao' ? 'bao://onhost/providers/' : 'env://';
        $instances = [
            ['key' => 'proxmox-cz1', 'provider' => 'proxmox', 'name' => 'Proxmox VE CZ1', 'region' => 'cz1', 'url' => env('PROXMOX_CZ1_URL'), 'secret' => $secrets.'PROXMOX_CZ1', 'capabilities' => ['vm.create' => true, 'vm.resize' => true, 'vm.snapshot' => true, 'vm.backup' => true, 'console' => true, 'compute' => true],
                'options' => ['default_node' => env('PROXMOX_CZ1_DEFAULT_NODE', 'pve1'), 'storage' => env('PROXMOX_CZ1_STORAGE', 'local-zfs'), 'backup_storage' => env('PROXMOX_CZ1_BACKUP_STORAGE', 'pbs-cz1'), 'pool' => env('PROXMOX_CZ1_POOL', 'onhost'), 'os_disk' => 'scsi0', 'template_node' => env('PROXMOX_CZ1_TEMPLATE_NODE', env('PROXMOX_CZ1_DEFAULT_NODE', 'pve1')), 'templates' => $this->templates('PROXMOX_CZ1_TEMPLATES'), 'verify_tls' => (bool) env('PROXMOX_CZ1_VERIFY_TLS', true), 'tls_ca' => env('PROXMOX_CZ1_TLS_CA')]],
            ['key' => 'pbs-cz1', 'provider' => 'pbs', 'name' => 'Proxmox Backup Server CZ1', 'region' => 'cz1', 'url' => env('PBS_CZ1_URL'), 'secret' => $secrets.'PBS_CZ1', 'capabilities' => ['backup.verify' => true, 'backup.list' => true],
                'options' => ['datastore' => env('PBS_CZ1_DATASTORE', 'onhost'), 'verify_tls' => (bool) env('PBS_CZ1_VERIFY_TLS', true)]],
            ['key' => 'ispconfig-shared01', 'provider' => 'ispconfig', 'name' => 'ISPConfig shared01', 'region' => 'cz1', 'url' => env('ISPCONFIG_SHARED01_URL'), 'secret' => $secrets.'ISPCONFIG_SHARED01', 'capabilities' => ['web.create' => true, 'mail.create' => true, 'database.create' => true, 'certificate.letsencrypt' => true],
                'options' => ['server_id' => (int) env('ISPCONFIG_SHARED01_SERVER_ID', 1), 'mail_server_id' => (int) env('ISPCONFIG_SHARED01_MAIL_SERVER_ID', 1), 'public_ipv4' => env('ISPCONFIG_SHARED01_PUBLIC_IPV4'), 'public_ipv6' => env('ISPCONFIG_SHARED01_PUBLIC_IPV6'), 'mail_host' => env('ONHOST_MAIL_HOST', 'mail.onhost.cz'), 'verify_tls' => (bool) env('ISPCONFIG_SHARED01_VERIFY_TLS', true)]],
            ['key' => 'aapanel-managed01', 'provider' => 'aapanel', 'name' => 'aaPanel managed01', 'region' => 'cz1', 'url' => env('AAPANEL_MANAGED01_URL'), 'secret' => $secrets.'AAPANEL_MANAGED01', 'capabilities' => ['web.create' => true, 'database.create' => true, 'certificate.letsencrypt' => true],
                'options' => ['public_ipv4' => env('AAPANEL_MANAGED01_PUBLIC_IPV4'), 'public_ipv6' => env('AAPANEL_MANAGED01_PUBLIC_IPV6'), 'verify_tls' => (bool) env('AAPANEL_MANAGED01_VERIFY_TLS', true)]],
            ['key' => 'pterodactyl-games01', 'provider' => 'pterodactyl', 'name' => 'Pterodactyl games01', 'region' => 'cz1', 'url' => env('PTERODACTYL_GAMES01_URL'), 'secret' => $secrets.'PTERODACTYL_GAMES01', 'capabilities' => ['game.create' => true, 'console' => true, 'backup' => true],
                'options' => ['eggs' => $this->eggs('PTERODACTYL_GAMES01_EGGS')]],
            ['key' => 'powerdns-hidden01', 'provider' => 'powerdns', 'name' => 'PowerDNS hidden primary', 'region' => null, 'url' => env('POWERDNS_HIDDEN01_URL'), 'secret' => $secrets.'POWERDNS_HIDDEN01', 'capabilities' => ['dns' => true, 'dnssec' => true],
                'options' => ['server_id' => 'localhost', 'nameservers' => config('onhost.dns.nameservers.powerdns'), 'also_notify' => $this->list('POWERDNS_HIDDEN01_ALSO_NOTIFY'), 'allow_axfr_from' => $this->list('POWERDNS_HIDDEN01_ALLOW_AXFR_FROM')]],
            ['key' => 'wedos-main', 'provider' => 'wedos', 'name' => 'WEDOS WAPI', 'region' => null, 'url' => config('onhost.wapi.endpoint'), 'secret' => $secrets.'WEDOS_MAIN', 'capabilities' => ['registrar' => true, 'poll' => true, 'credit' => true], 'options' => ['egress_ip' => config('onhost.wapi.egress_ip')]],
            ['key' => 'wedos-zone', 'provider' => 'wedos_zone', 'name' => 'WEDOS Zone (secondary DNS)', 'region' => null, 'url' => env('WEDOS_ZONE_ENABLED') ? config('onhost.wapi.endpoint') : null, 'secret' => $secrets.'WEDOS_MAIN', 'capabilities' => ['dns' => 'secondary'], 'options' => []],
            ['key' => 'rke2-apps-cz1', 'provider' => 'kubernetes', 'name' => 'RKE2 apps CZ1', 'region' => 'cz1', 'url' => env('RKE2_CZ1_API_URL'), 'secret' => $secrets.'RKE2_CZ1', 'capabilities' => ['namespace.tenant' => true, 'deploy' => true, 'build' => true],
                'options' => ['ingress_class' => env('RKE2_CZ1_INGRESS_CLASS', 'traefik'), 'ingress_namespace' => env('RKE2_CZ1_INGRESS_NAMESPACE', 'kube-system'), 'cluster_issuer' => env('RKE2_CZ1_CLUSTER_ISSUER', 'letsencrypt'), 'build_namespace' => env('RKE2_CZ1_BUILD_NAMESPACE', 'onhost-build'), 'registry' => env('RKE2_CZ1_REGISTRY', 'registry.onhost.internal'), 'tls_ca' => env('RKE2_CZ1_TLS_CA')]],
        ];
        foreach ($instances as $i) {
            if (empty($i['url'])) {
                continue; // not configured in this environment
            }
            ProviderInstance::query()->updateOrCreate(['key' => $i['key']], [
                'provider' => $i['provider'], 'name' => $i['name'], 'region_code' => $i['region'], 'base_url' => rtrim((string) $i['url'], '/'), 'secret_ref' => $i['secret'],
                'state' => 'active', 'capabilities' => $i['capabilities'], 'options' => array_filter($i['options'], fn ($v) => $v !== null), 'adapter_version' => '1.0.0',
            ]);
        }
        $this->nodes();
        $this->pools();
    }

    /** @return array<string, array<string,mixed>> */
    private function regions(): array
    {
        return ['cz1' => ['name' => env('ONHOST_REGION_CZ1_NAME', 'Praha'), 'country' => 'CZ', 'datacenter' => env('ONHOST_REGION_CZ1_DC'), 'state' => 'active', 'meta' => ['tier' => 'III']]];
    }

    /** Nodes come from ONHOST_NODES as JSON: [{"instance":"proxmox-cz1","name":"pve1","role":"compute","region":"cz1","capacity":{"cpu_cores":64,"ram_mb":262144,"disk_gb":4000},"failure_domain":"rack-a","remote_id":null,"tags":{}}] */
    private function nodes(): void
    {
        foreach ((array) json_decode((string) env('ONHOST_NODES', '[]'), true) as $n) {
            $instance = ProviderInstance::query()->where('key', $n['instance'] ?? '')->first();
            if ($instance === null || empty($n['name'])) {
                continue;
            }
            Node::query()->updateOrCreate(['provider_instance_id' => $instance->id, 'name' => $n['name']], [
                'region_code' => $n['region'] ?? $instance->region_code ?? 'cz1', 'zone' => $n['zone'] ?? null, 'role' => $n['role'] ?? 'compute', 'state' => $n['state'] ?? 'active',
                'capacity' => $n['capacity'] ?? [], 'usage' => $n['usage'] ?? [], 'failure_domain' => $n['failure_domain'] ?? null, 'tags' => $n['tags'] ?? [], 'remote_id' => $n['remote_id'] ?? null,
            ]);
        }
    }

    /** Pools from ONHOST_IP_POOLS JSON: [{"region":"cz1","family":4,"cidr":"…","purpose":"vps","gateway":"…","dns":["…"],"vlan":100,"reserve":4}] */
    private function pools(): void
    {
        $ipam = app(IpamService::class);
        foreach ((array) json_decode((string) env('ONHOST_IP_POOLS', '[]'), true) as $p) {
            if (empty($p['cidr'])) {
                continue;
            }
            $pool = IpPool::query()->updateOrCreate(['cidr' => $p['cidr'], 'family' => (int) ($p['family'] ?? 4)], [
                'region_code' => $p['region'] ?? 'cz1', 'purpose' => $p['purpose'] ?? 'vps', 'gateway' => $p['gateway'] ?? null, 'dns' => $p['dns'] ?? [], 'vlan' => $p['vlan'] ?? null, 'state' => 'active', 'reserve_count' => (int) ($p['reserve'] ?? 0),
            ]);
            $ipam->populate($pool, (int) ($p['populate_limit'] ?? 65536));
        }
    }

    private function templates(string $env): array
    {
        $decoded = json_decode((string) env($env, ''), true);

        return is_array($decoded) ? $decoded : ['debian-13' => (int) env('PROXMOX_CZ1_TEMPLATE_VMID', 9001)];
    }

    private function eggs(string $env): array
    {
        $decoded = json_decode((string) env($env, ''), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function list(string $env): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) env($env, '')))));
    }
}
