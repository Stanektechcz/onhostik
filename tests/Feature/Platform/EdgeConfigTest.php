<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Onhost\Domain\Incidents\OrganizationStatusService;

/* The packaged edge configuration for the customers' status hosts (audit §5l-3): both flavours render with the portal host and the ask endpoint. */

it('renders the Caddy and nginx edge configuration with the portal host and the ask endpoint', function () {
    $host = OrganizationStatusService::customCname();
    expect(Artisan::call('onhost:edge:config', ['--format' => 'caddy', '--upstream' => '10.0.0.5:8080']))->toBe(0);
    $caddy = Artisan::output();
    expect($caddy)->toContain("ask https://{$host}/v1/status/host-check")->toContain('on_demand')->toContain('reverse_proxy 10.0.0.5:8080')->toContain("ask endpoint: https://{$host}/v1/status/host-check?host=<host>")->not->toContain('{{PORTAL_HOST}}');
    expect(Artisan::call('onhost:edge:config', ['--format' => 'nginx']))->toBe(0);
    expect(Artisan::output())->toContain('proxy_pass         http://127.0.0.1:8000')->toContain("https://{$host}/v1/status/host-check?host=<host>");
    expect(Artisan::call('onhost:edge:config', ['--format' => 'haproxy']))->toBe(1);
    $out = base_path('infra/edge/caddy.generated');
    @unlink($out);
    expect(Artisan::call('onhost:edge:config', ['--format' => 'caddy', '--write' => true]))->toBe(0);
    expect(is_file($out))->toBeTrue()->and((string) file_get_contents($out))->toContain("ask https://{$host}/v1/status/host-check")->not->toContain('{{PORTAL_HOST}}');
    @unlink($out);

    // edge as code (audit §5m-3): the Ansible role renders to a path of its own and ships the file
    $dir = storage_path('app/edge-test-'.uniqid());
    expect(Artisan::call('onhost:edge:config', ['--format' => 'nginx', '--upstream' => '10.0.0.9:8000', '--out' => $dir.'/status-hosts.conf']))->toBe(0);
    expect((string) file_get_contents($dir.'/status-hosts.conf'))->toContain('proxy_pass         http://10.0.0.9:8000')->and(Artisan::output())->toContain('written '.$dir.'/status-hosts.conf');
    @unlink($dir.'/status-hosts.conf');
    @rmdir($dir);
    foreach (['infra/ansible/roles/onhost_edge/tasks/main.yml', 'infra/ansible/roles/onhost_edge/handlers/main.yml', 'infra/ansible/roles/onhost_edge/defaults/main.yml', 'infra/ansible/edge.yml'] as $file) {
        expect(is_file(base_path($file)))->toBeTrue();
    }
    $tasks = (string) file_get_contents(base_path('infra/ansible/roles/onhost_edge/tasks/main.yml'));
    expect($tasks)->toContain('onhost:edge:config')->toContain('--out=')->toContain('caddy validate')->toContain('nginx -t');
});
