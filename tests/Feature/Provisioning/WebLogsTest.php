<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\IspConfig\IspConfigWebProvider;
use Onhost\Providers\Shell\ScriptedShell;

/*
 * A shared-hosting customer could not read their own access or error log in ONhost at all: the ISPConfig adapter
 * answered "the remote API has no log endpoint; use the log pipeline" — an answer for operators, not for the person
 * whose site is returning 500s. A managed (aaPanel) customer could. The node does keep the site's logs in `log/`
 * next to `web/`, which is exactly what the customer sees over SFTP, and the platform already reaches that directory
 * as the site's own agent user.
 */

beforeEach(fn () => Http::preventStrayRequests());
afterEach(fn () => IspConfigWebProvider::$shellFactory = null);

it('reads a shared site\'s own log the way its owner would over SFTP', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    $lines = "203.0.113.9 - - [22/Sep/2026:10:00:01 +0200] \"GET / HTTP/1.1\" 200 5120\n203.0.113.9 - - [22/Sep/2026:10:00:02 +0200] \"GET /a HTTP/1.1\" 404 120\n";
    $shell = new ScriptedShell(['/^cd ~ && pwd/' => [0, "/var/www/clients/client3/web7\n"], '/^tail -n/' => [0, $lines]]);
    IspConfigWebProvider::$shellFactory = fn () => $shell;

    $log = app(ServiceFeatures::class)->adapterFor($service)->tailLog(app(ServiceFeatures::class)->refFor($service), 'access', 50);

    expect($log)->toHaveCount(2)->and($log[0])->toContain('GET /');
    $command = collect($shell->calls)->pluck('command')->first(fn (string $c) => str_starts_with($c, 'tail'));
    expect($command)->toContain("'/var/www/clients/client3/web7/log/access.log'") // the site's own log directory, not the node's
        ->toContain('tail -n 50');
});

it('asks for the error log by its own name, and says plainly when the node has not written one yet', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    $shell = new ScriptedShell(['/^cd ~ && pwd/' => [0, "/var/www/clients/client3/web7\n"], '/^tail -n/' => [1, '', 'No such file or directory']]);
    IspConfigWebProvider::$shellFactory = fn () => $shell;
    $features = app(ServiceFeatures::class);

    expect(fn () => $features->adapterFor($service)->tailLog($features->refFor($service), 'error', 20))
        ->toThrow(ProviderException::class, 'error.log');
});

it('offers the log to the customer of a shared site, as it always did to a managed one', function () {
    [, $org] = $this->customerWithOrganization();
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok', 'data' => [], 'page' => '']));

    expect(app(ServiceFeatures::class)->features(featureWebService($org, 'ispconfig'))['logs']['enabled'])->toBeTrue();
});
