<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Services\SuspensionDepth;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Providers\Contracts\Naming;

/*
 * A suspended site is more than a stopped vhost: its cron jobs kept running (a quarantined site went on sending mail from
 * its cron) and its files stayed writable by FTP. The suspension switches those off too and remembers which ones; the
 * resume switches exactly those on again. And a machine its owner had switched off is not started by the payment that
 * lifts a suspension.
 */

beforeEach(fn () => Http::preventStrayRequests());

it('switches a suspended site\'s cron jobs and FTP accounts off, and on again — only those it had switched off', function () {
    [, $org] = $this->customerWithOrganization();
    $site = featureWebService($org, 'aapanel');
    $prefix = Naming::prefix($site->id);
    // the panel as it stands: two cron jobs (one the customer had already turned off), two FTP accounts (one already off)
    $panel = ['site' => 1, 'cron' => [21 => 1, 22 => 0], 'ftp' => [31 => 1, 32 => 0], 'calls' => []];
    Http::fake(function (Request $r) use (&$panel, $site, $prefix) {
        $path = (string) parse_url($r->url(), PHP_URL_PATH).'?'.(string) parse_url($r->url(), PHP_URL_QUERY);
        $body = $r->data();
        $panel['calls'][] = [$path, $body];

        return Http::response(match (true) {
            str_contains($path, 'site?action=SiteStop') => (function () use (&$panel) {
                $panel['site'] = 0;

                return ['status' => true, 'msg' => 'stopped'];
            })(),
            str_contains($path, 'site?action=SiteStart') => (function () use (&$panel) {
                $panel['site'] = 1;

                return ['status' => true, 'msg' => 'started'];
            })(),
            str_contains($path, 'crontab?action=GetCrontab') => array_map(fn ($id) => ['id' => $id, 'name' => Naming::cronLabel($site->id, "job{$id}"), 'type' => 'minute-n', 'where1' => '5', 'where_hour' => '0', 'where_minute' => '5', 'sBody' => 'php artisan schedule:run', 'status' => $panel['cron'][$id]], array_keys($panel['cron'])),
            str_contains($path, 'crontab?action=set_cron_status') => (function () use (&$panel, $body) {
                $panel['cron'][(int) $body['id']] = $panel['cron'][(int) $body['id']] === 1 ? 0 : 1; // the panel's switch toggles

                return ['status' => true, 'msg' => 'ok'];
            })(),
            str_contains($path, 'data?action=getData&table=ftps') => ['data' => array_map(fn ($id) => ['id' => $id, 'name' => "{$prefix}_ftp{$id}", 'path' => '/www/wwwroot/shop.cz', 'status' => (string) $panel['ftp'][$id]], array_keys($panel['ftp']))],
            str_contains($path, 'ftp?action=SetStatus') => (function () use (&$panel, $body) {
                $panel['ftp'][(int) $body['id']] = (int) $body['status'];

                return ['status' => true, 'msg' => 'ok'];
            })(),
            default => ['status' => true, 'msg' => 'ok', 'data' => []],
        });
    });
    $services = app(ServiceService::class);
    $system = CommandContext::system('dunning')->withScope($org->id);

    $suspend = driveOperation($services->requestAction($site, 'suspend', $system, 'depth-suspend', ['reason' => 'dunning']));
    expect($suspend->state)->toBe(Operation::SUCCEEDED)->and($site->refresh()->state)->toBe(ServiceStateMachine::SUSPENDED);
    // the site is down — and so is everything that would have kept running around it
    expect($panel['site'])->toBe(0)->and($panel['cron'])->toBe([21 => 0, 22 => 0])->and($panel['ftp'])->toBe([31 => 0, 32 => 0])
        ->and(data_get($site->tags, SuspensionDepth::TAG))->toBe(['cron' => ['21'], 'ftp' => ['31']]);
    // a job is switched, never re-saved: re-saving would rewrite "every five minutes" as what the listing can express
    expect(collect($panel['calls'])->contains(fn ($c) => str_contains($c[0], 'modify_crond')))->toBeFalse();

    $resume = driveOperation($services->requestAction($site, 'resume', CommandContext::system('dunning resolved')->withScope($org->id), 'depth-resume', ['reason' => 'dunning resolved', 'lift' => 'payment']));
    expect($resume->state)->toBe(Operation::SUCCEEDED)->and($site->refresh()->state)->toBe(ServiceStateMachine::ACTIVE);
    // exactly what the suspension had switched off is on again; what the customer had turned off themselves stays off
    expect($panel['site'])->toBe(1)->and($panel['cron'])->toBe([21 => 1, 22 => 0])->and($panel['ftp'])->toBe([31 => 1, 32 => 0])
        ->and(data_get($site->tags, SuspensionDepth::TAG))->toBeNull();
});

it('does not start a machine its owner had switched off when a suspension is lifted', function () {
    [, $org] = $this->customerWithOrganization();
    $instance = pveLab();
    $vm = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'VPS', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'entitlements' => [],
        'desired_spec' => ['executor' => 'proxmox', 'family' => 'cloud'], 'sla_class' => 'standard', 'activated_at' => now()->subMonth(), 'tags' => [], 'health' => []]);
    ProviderBinding::query()->create(['service_id' => $vm->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => '1042', 'remote_node' => 'prg1-n2', 'meta' => [], 'ownership' => [], 'idempotency_key' => 'b-depth', 'adapter_version' => '1.0.0']);
    $calls = [];
    Http::fake(function (Request $r) use (&$calls) {
        $path = (string) parse_url($r->url(), PHP_URL_PATH);
        $calls[] = $r->method().' '.$path;

        return match (true) {
            str_ends_with($path, '/status/current') => Http::response(['data' => ['status' => 'stopped']]), // the customer shut it down last week
            str_ends_with($path, '/qemu/1042/config') => $r->method() === 'GET' ? Http::response(pveVmConfig()) : Http::response(['data' => null]),
            str_ends_with($path, '/status/start') => Http::response(['data' => 'UPID:prg1-n2:2:2:2:qmstart:1042:onhost@pve!cp:']),
            str_contains($path, '/tasks/') => Http::response(['data' => ['status' => 'stopped', 'exitstatus' => 'OK']]),
            default => Http::response(['data' => null]),
        };
    });
    $services = app(ServiceService::class);

    driveOperation($services->requestAction($vm, 'suspend', CommandContext::system('dunning')->withScope($org->id), 'vm-suspend', ['reason' => 'dunning']));
    expect($vm->refresh()->state)->toBe(ServiceStateMachine::SUSPENDED)->and(data_get($vm->tags, 'suspension_was_running'))->toBeFalse();

    driveOperation($services->requestAction($vm, 'resume', CommandContext::system('dunning resolved')->withScope($org->id), 'vm-resume', ['reason' => 'dunning resolved', 'lift' => 'payment']));
    expect($vm->refresh()->state)->toBe(ServiceStateMachine::ACTIVE)->and(data_get($vm->tags, 'suspension_was_running'))->toBeNull();
    // the lock is lifted (config), the machine stays as its owner left it: it used to be started
    expect(collect($calls)->contains(fn ($c) => str_ends_with($c, '/status/start')))->toBeFalse()
        ->and(collect($calls)->contains(fn ($c) => str_starts_with($c, 'PUT') && str_ends_with($c, '/qemu/1042/config')))->toBeTrue();
});
