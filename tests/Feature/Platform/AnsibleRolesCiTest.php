<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

/*
 * Molecule for the other plays (audit §5o-3): every role the site playbook names exists with tasks, defaults and a
 * molecule scenario; the workflow runs them all; the vendor CLIs the roles call are stubbed where a container cannot
 * carry them (pveum, mysql) and real where it can (PowerDNS, the probe).
 */

it('ships every role of site.yml with a molecule scenario and runs them in the workflow matrix', function () {
    $site = Yaml::parseFile(base_path('infra/ansible/site.yml'));
    $roles = [];
    foreach ($site as $play) {
        foreach ((array) ($play['roles'] ?? []) as $role) {
            $roles[] = is_array($role) ? (string) $role['role'] : (string) $role;
        }
    }
    expect($roles)->toBe(['onhost_proxmox_api', 'onhost_ispconfig_remote', 'onhost_powerdns', 'onhost_node_activate', 'onhost_probe', 'onhost_clamav']);
    $activate = (string) file_get_contents(base_path('infra/ansible/roles/onhost_node_activate/tasks/main.yml'));
    expect($activate)->toContain('ansible.builtin.uri')->toContain('onhost_activate_token')->toContain('status_code: [202]')->toContain('no_log: true'); // §5p-7
    $all = array_merge(['onhost_edge'], $roles);
    foreach ($all as $role) {
        $dir = base_path("infra/ansible/roles/{$role}");
        $tasks = Yaml::parseFile($dir.'/tasks/main.yml');
        expect($tasks)->toBeArray()->and(count($tasks))->toBeGreaterThanOrEqual(3);
        foreach ($tasks as $task) {
            expect($task)->toHaveKey('name');
        }
        expect(Yaml::parseFile($dir.'/defaults/main.yml'))->toBeArray();
        $molecule = Yaml::parseFile($dir.'/molecule/default/molecule.yml');
        expect($molecule['driver']['name'])->toBe('docker')->and(Yaml::parseFile($dir.'/molecule/default/converge.yml')[0]['roles'][0]['role'])->toBe($role);
        $verify = Yaml::parseFile($dir.'/molecule/default/verify.yml');
        expect(collect($verify[0]['tasks'])->filter(fn ($t) => isset($t['ansible.builtin.assert']))->count())->toBeGreaterThanOrEqual(1);
    }

    // the vendor-CLI roles stub the binary and check idempotence; the real ones install packages
    expect(Yaml::parseFile(base_path('infra/ansible/roles/onhost_proxmox_api/molecule/default/prepare.yml'))[0]['tasks'][0]['ansible.builtin.copy']['dest'])->toBe('/usr/local/bin/pveum');
    expect(Yaml::parseFile(base_path('infra/ansible/roles/onhost_ispconfig_remote/molecule/default/prepare.yml'))[0]['tasks'][0]['ansible.builtin.copy']['dest'])->toBe('/usr/local/bin/mysql');
    $proxmoxTasks = (string) file_get_contents(base_path('infra/ansible/roles/onhost_proxmox_api/tasks/main.yml'));
    expect($proxmoxTasks)->toContain('role add {{ token_role }}')->toContain('user token add')->toContain('no_log: true');
    $ispTasks = (string) file_get_contents(base_path('infra/ansible/roles/onhost_ispconfig_remote/tasks/main.yml'));
    expect($ispTasks)->toContain('INSERT INTO remote_user')->toContain("password_hash('sha512')")->toContain('no_log: true');
    expect(Yaml::parseFile(base_path('infra/ansible/roles/onhost_powerdns/tasks/main.yml'))[0]['ansible.builtin.apt']['name'])->toContain('pdns-server');
    expect((string) file_get_contents(base_path('infra/ansible/roles/onhost_powerdns/templates/onhost-api.conf.j2')))->toContain('api=yes')->toContain('api-key={{ pdns_api_key }}');
    $probe = (string) file_get_contents(base_path('infra/ansible/roles/onhost_probe/templates/onhost-probe.sh.j2'));
    expect($probe)->toContain('dig +short')->toContain('curl -fsS -m 15 -X POST "$ONHOST_PROBE_API"')->toContain('Authorization: Bearer $ONHOST_PROBE_TOKEN')->toContain('\"results\":[');
    expect((string) file_get_contents(base_path('infra/ansible/roles/onhost_probe/templates/onhost-probe.timer.j2')))->toContain('OnUnitActiveSec={{ interval_seconds }}');

    // the workflow matrix and the lint profile know every role
    $workflow = Yaml::parseFile(base_path('.github/workflows/edge-role.yml'));
    expect(collect($workflow['jobs']['molecule']['strategy']['matrix']['role'])->sort()->values()->all())->toBe(collect($all)->sort()->values()->all())->and($workflow['jobs']['molecule']['steps'][4]['working-directory'])->toBe('infra/ansible/roles/${{ matrix.role }}');
    $lint = Yaml::parseFile(base_path('infra/ansible/.ansible-lint'));
    foreach ($all as $role) {
        expect($lint['exclude_paths'])->toContain("roles/{$role}/molecule/");
    }
});
