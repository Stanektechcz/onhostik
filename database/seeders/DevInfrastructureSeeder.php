<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Onhost\Domain\Provisioning\Ipam\IpamService;
use Onhost\Domain\Provisioning\Models\IpPool;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Models\Region;
use RuntimeException;

/**
 * Local/staging registry only: documentation address ranges (RFC 5737 / RFC 3849)
 * and `env://` secrets. Refuses to run in production.
 */
final class DevInfrastructureSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DevInfrastructureSeeder must never run in production.');
        }
        Region::query()->updateOrCreate(['code' => 'cz1'], ['name' => 'Praha (dev)', 'country' => 'CZ', 'datacenter' => 'lab', 'state' => 'active', 'meta' => ['dev' => true]]);
        $instances = [
            ['key' => 'proxmox-cz1', 'provider' => 'proxmox', 'name' => 'PVE lab', 'base_url' => env('PROXMOX_CZ1_URL', 'https://pve.lab.onhost.internal:8006'), 'secret_ref' => 'env://PROXMOX_CZ1', 'region_code' => 'cz1', 'capabilities' => ['vm.create' => true, 'compute' => true, 'console' => true, 'vm.backup' => true],
                'options' => ['default_node' => 'pve1', 'storage' => 'local-zfs', 'backup_storage' => 'pbs-cz1', 'template_node' => 'pve1', 'templates' => ['debian-13' => 9001, 'ubuntu-24.04' => 9002], 'os_disk' => 'scsi0', 'verify_tls' => false]],
            ['key' => 'pbs-cz1', 'provider' => 'pbs', 'name' => 'PBS lab', 'base_url' => env('PBS_CZ1_URL', 'https://pbs.lab.onhost.internal:8007'), 'secret_ref' => 'env://PBS_CZ1', 'region_code' => 'cz1', 'capabilities' => ['backup' => true], 'options' => ['datastore' => 'backup', 'verify_tls' => false]],
            ['key' => 'ispconfig-shared01', 'provider' => 'ispconfig', 'name' => 'ISPConfig lab', 'base_url' => env('ISPCONFIG_SHARED01_URL', 'https://shared01.lab.onhost.internal:8080'), 'secret_ref' => 'env://ISPCONFIG_SHARED01', 'region_code' => 'cz1', 'capabilities' => ['web.create' => true, 'mail.create' => true], 'options' => ['server_id' => 1, 'mail_server_id' => 1, 'public_ipv4' => '192.0.2.10', 'public_ipv6' => '2001:db8:10::10', 'verify_tls' => false]],
            ['key' => 'aapanel-managed01', 'provider' => 'aapanel', 'name' => 'aaPanel lab', 'base_url' => env('AAPANEL_MANAGED01_URL', 'https://managed01.lab.onhost.internal:7800'), 'secret_ref' => 'env://AAPANEL_MANAGED01', 'region_code' => 'cz1', 'capabilities' => ['web.create' => true], 'options' => ['public_ipv4' => '192.0.2.11', 'verify_tls' => false]],
            ['key' => 'pterodactyl-games01', 'provider' => 'pterodactyl', 'name' => 'Pterodactyl lab', 'base_url' => env('PTERODACTYL_GAMES01_URL', 'https://panel.lab.onhost.internal'), 'secret_ref' => 'env://PTERODACTYL_GAMES01', 'region_code' => 'cz1', 'capabilities' => ['game.create' => true, 'console' => true], 'options' => ['eggs' => ['minecraft-paper' => ['nest' => 1, 'egg' => 3], 'cs2' => ['nest' => 2, 'egg' => 7]]]],
            ['key' => 'powerdns-hidden01', 'provider' => 'powerdns', 'name' => 'PowerDNS lab', 'base_url' => env('POWERDNS_HIDDEN01_URL', 'http://pdns.lab.onhost.internal:8081'), 'secret_ref' => 'env://POWERDNS_HIDDEN01', 'region_code' => null, 'capabilities' => ['dns' => true, 'dnssec' => true], 'options' => ['nameservers' => ['ns1.onhost.cz', 'ns2.onhost.cz']]],
            ['key' => 'wedos-main', 'provider' => 'wedos', 'name' => 'WEDOS WAPI (test mode)', 'base_url' => config('onhost.wapi.endpoint'), 'secret_ref' => 'env://WEDOS_MAIN', 'region_code' => null, 'capabilities' => ['registrar' => true, 'poll' => true, 'credit' => true], 'options' => []],
            ['key' => 'subreg-main', 'provider' => 'subreg', 'name' => 'Subreg (demoreg sandbox)', 'base_url' => 'https://demoreg.net', 'secret_ref' => 'env://SUBREG_MAIN', 'region_code' => null, 'capabilities' => ['registrar' => true, 'poll' => true], 'options' => ['demo' => true]],
            ['key' => 'rke2-apps-cz1', 'provider' => 'kubernetes', 'name' => 'RKE2 lab', 'base_url' => env('RKE2_CZ1_API_URL', 'https://rke2.lab.onhost.internal:6443'), 'secret_ref' => 'env://RKE2_CZ1', 'region_code' => 'cz1', 'capabilities' => ['namespace.tenant' => true, 'deploy' => true, 'build' => true], 'options' => ['ingress_class' => 'traefik', 'build_namespace' => 'onhost-build', 'registry' => 'registry.lab.onhost.internal', 'verify_tls' => false]],
        ];
        foreach ($instances as $i) {
            ProviderInstance::query()->updateOrCreate(['key' => $i['key']], array_merge($i, ['state' => 'active', 'adapter_version' => '1.0.0']));
        }
        $nodes = [
            ['proxmox-cz1', 'pve1', 'compute', ['cpu_cores' => 64, 'ram_mb' => 262144, 'disk_gb' => 4000], 'rack-a', null, []],
            ['proxmox-cz1', 'pve2', 'compute', ['cpu_cores' => 64, 'ram_mb' => 262144, 'disk_gb' => 4000], 'rack-b', null, []],
            ['pbs-cz1', 'pbs1', 'backup', ['disk_gb' => 20000], 'rack-c', null, []],
            ['ispconfig-shared01', 'shared01', 'web', ['cpu_cores' => 32, 'ram_mb' => 131072, 'disk_gb' => 2000], 'rack-a', null, ['public_ipv4' => '192.0.2.10', 'public_ipv6' => '2001:db8:10::10']],
            ['ispconfig-shared01', 'mail01', 'mail', ['cpu_cores' => 8, 'ram_mb' => 32768, 'disk_gb' => 1000], 'rack-a', null, []],
            ['aapanel-managed01', 'managed01', 'managed', ['cpu_cores' => 32, 'ram_mb' => 131072, 'disk_gb' => 2000], 'rack-b', null, ['public_ipv4' => '192.0.2.11']],
            ['pterodactyl-games01', 'games01', 'game', ['cpu_cores' => 32, 'ram_mb' => 131072, 'disk_gb' => 2000], 'rack-b', 1, []],
        ];
        foreach ($nodes as [$instanceKey, $name, $role, $capacity, $fd, $remoteId, $tags]) {
            $instance = ProviderInstance::query()->where('key', $instanceKey)->firstOrFail();
            Node::query()->updateOrCreate(['provider_instance_id' => $instance->id, 'name' => $name], ['region_code' => 'cz1', 'role' => $role, 'state' => 'active', 'capacity' => $capacity, 'usage' => ['cpu_pct' => 10, 'ram_used_mb' => (int) ($capacity['ram_mb'] * 0.1), 'disk_used_gb' => (int) ($capacity['disk_gb'] * 0.1), 'io_wait_pct' => 1], 'failure_domain' => $fd, 'remote_id' => $remoteId, 'tags' => $tags]);
        }
        $ipam = app(IpamService::class);
        foreach ([
            ['family' => 4, 'cidr' => '192.0.2.0/24', 'purpose' => 'vps', 'gateway' => '192.0.2.1', 'dns' => ['9.9.9.9', '149.112.112.112'], 'reserve' => 4],
            ['family' => 6, 'cidr' => '2001:db8:1::/48', 'purpose' => 'vps', 'gateway' => '2001:db8:1::1', 'dns' => ['2620:fe::fe'], 'reserve' => 4, 'limit' => 256],
        ] as $p) {
            $pool = IpPool::query()->updateOrCreate(['cidr' => $p['cidr'], 'family' => $p['family']], ['region_code' => 'cz1', 'purpose' => $p['purpose'], 'gateway' => $p['gateway'], 'dns' => $p['dns'], 'state' => 'active', 'reserve_count' => $p['reserve']]);
            $ipam->populate($pool, (int) ($p['limit'] ?? 65536));
        }
    }
}
