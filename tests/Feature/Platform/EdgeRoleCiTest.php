<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

/*
 * Edge role in CI (audit §5n-3): the role, its handlers, the molecule scenario and the lint profile parse; every task is
 * named and every handler a task notifies exists; the workflow lints and converges the role in a container.
 */

it('ships a lint profile, a molecule scenario and a workflow that exercises the edge role', function () {
    $roleDir = base_path('infra/ansible/roles/onhost_edge');
    $tasks = Yaml::parseFile($roleDir.'/tasks/main.yml');
    $handlers = Yaml::parseFile($roleDir.'/handlers/main.yml');
    $defaults = Yaml::parseFile($roleDir.'/defaults/main.yml');
    expect($tasks)->toBeArray()->and(count($tasks))->toBeGreaterThanOrEqual(5)->and($handlers)->toBeArray()->and($defaults)->toHaveKeys(['onhost_app_dir', 'onhost_php', 'onhost_edge_flavour', 'onhost_edge_upstream', 'onhost_edge_conf_dir', 'onhost_edge_render_path']);
    $handlerNames = array_map(fn ($h) => (string) ($h['name'] ?? ''), $handlers);
    foreach ($tasks as $task) {
        expect($task)->toHaveKey('name');
        foreach ((array) ($task['notify'] ?? []) as $notified) {
            expect($handlerNames)->toContain($notified);
        }
        foreach (array_keys($task) as $module) {
            if (str_contains((string) $module, '.')) {
                expect((string) $module)->toStartWith('ansible.builtin.'); // fully qualified builtin modules, as ansible-lint wants them
            }
        }
    }
    expect(array_column($tasks, 'name'))->toContain('Render the edge configuration from the platform');

    // the molecule scenario: docker driver, the three playbooks, a stub php that renders --out, a verifier that validates with caddy
    $scenario = $roleDir.'/molecule/default';
    $molecule = Yaml::parseFile($scenario.'/molecule.yml');
    expect($molecule['driver']['name'])->toBe('docker')->and($molecule['provisioner']['playbooks'])->toBe(['prepare' => 'prepare.yml', 'converge' => 'converge.yml', 'verify' => 'verify.yml'])
        ->and($molecule['provisioner']['inventory']['group_vars']['all']['onhost_php'])->toBe('/usr/local/bin/php');
    $prepare = Yaml::parseFile($scenario.'/prepare.yml');
    $stub = collect($prepare[0]['tasks'])->firstWhere('name', 'Install a stub php that renders the template to --out');
    expect($stub['ansible.builtin.copy']['dest'])->toBe('/usr/local/bin/php')->and($stub['ansible.builtin.copy']['content'])->toContain('--out=');
    $converge = Yaml::parseFile($scenario.'/converge.yml');
    expect($converge[0]['roles'][0]['role'])->toBe('onhost_edge');
    $verify = Yaml::parseFile($scenario.'/verify.yml');
    $asserts = collect($verify[0]['tasks'])->firstWhere('name', 'The file carries the on-demand TLS ask and the upstream');
    expect(implode(' ', $asserts['ansible.builtin.assert']['that']))->toContain('on_demand')->toContain('v1/status/host-check');
    expect(collect($verify[0]['tasks'])->map(fn ($t) => $t['ansible.builtin.command'] ?? null)->filter()->first())->toContain('caddy validate'); // pluck() would read the dots as a path

    // the lint profile and the workflow
    $lint = Yaml::parseFile(base_path('infra/ansible/.ansible-lint'));
    expect($lint['profile'])->toBe('moderate')->and($lint['exclude_paths'])->toContain('roles/onhost_edge/molecule/');
    $workflow = Yaml::parseFile(base_path('.github/workflows/edge-role.yml'));
    expect($workflow['jobs'])->toHaveKeys(['lint', 'molecule'])->and($workflow['jobs']['molecule']['needs'])->toBe('lint');
    $steps = implode("\n", array_map(fn ($s) => (string) ($s['run'] ?? ''), array_merge($workflow['jobs']['lint']['steps'], $workflow['jobs']['molecule']['steps'])));
    expect($steps)->toContain('ansible-lint')->toContain('molecule test');
    expect((string) file_get_contents(base_path('.github/workflows/edge-role.yml')))->toContain("paths: ['infra/ansible/**', 'infra/edge/**'"); // YAML reads `on:` as a boolean key
});
