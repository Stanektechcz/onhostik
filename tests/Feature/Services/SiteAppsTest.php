<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Services\SuspensionDepth;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\AaPanel\AaPanelWebProvider;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Shell\ScriptedShell;

/*
 * A site's Node.js app on aaPanel belongs to the panel, not to the site (Brain cards H505, H500, H440). `DeleteSite`
 * took the files and left the project: started at every boot, its port reserved, the panel still routing the site's
 * domains to that port — so the next customer given the same port would receive this customer's traffic.
 *
 * And the ownership test itself was a bare path prefix: /www/wwwroot/shop.cz "owned" the apps of /www/wwwroot/shop.cz.eu.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    AaPanelWebProvider::$shellFactory = fn () => new ScriptedShell(['/^id -u /' => "1042\n"]);
});
afterEach(fn () => AaPanelWebProvider::$shellFactory = null);

/**
 * The panel's app list, shared by every site on the node: ours, a neighbour whose name starts like ours, and one elsewhere.
 *
 * @param  array<string, array{path:string, run:bool}>  $apps
 * @param  list<string>  $calls
 */
function appsPanelFake(array &$apps, array &$calls): void
{
    Http::fake(function ($request) use (&$apps, &$calls) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH).'?'.(string) parse_url($request->url(), PHP_URL_QUERY);
        $body = $request->data();
        $calls[] = trim($path, '?').(isset($body['project_name']) ? ' '.$body['project_name'] : '');
        if (str_contains($path, 'project/nodejs/get_project_list')) {
            return Http::response(['data' => array_map(fn (string $name, array $a) => ['name' => $name, 'path' => $a['path'], 'run' => $a['run'], 'project_config' => ['port' => 3000]], array_keys($apps), $apps)]);
        }
        if (str_contains($path, 'project/nodejs/stop_project')) {
            $apps[$body['project_name']]['run'] = false;

            return Http::response(['status' => true, 'msg' => 'stopped']);
        }
        if (str_contains($path, 'project/nodejs/start_project')) {
            $apps[$body['project_name']]['run'] = true;

            return Http::response(['status' => true, 'msg' => 'started']);
        }
        if (str_contains($path, 'project/nodejs/remove_project')) {
            unset($apps[$body['project_name']]);

            return Http::response(['status' => true, 'msg' => 'removed']);
        }
        if (str_contains($path, 'crontab?action=GetCrontab')) {
            return Http::response([]);
        }
        if (str_contains($path, 'data?action=getData&table=sites')) {
            return Http::response(['data' => [['id' => 41, 'name' => 'shop.cz', 'path' => '/www/wwwroot/shop.cz']]]);
        }
        if (str_contains($path, 'data?action=getData&table=ftps')) {
            return Http::response(['data' => []]);
        }

        return Http::response(['status' => true, 'msg' => 'ok']);
    });
}

it('lists only the apps under the site\'s own root — not those of a site whose name merely starts the same', function () {
    $apps = ['shop_api' => ['path' => '/www/wwwroot/shop.cz/api', 'run' => true], 'eu_app' => ['path' => '/www/wwwroot/shop.cz.eu/app', 'run' => true], 'root_app' => ['path' => '/www/wwwroot/shop.cz', 'run' => false]];
    $calls = [];
    appsPanelFake($apps, $calls);
    $site = new ResourceRef('site', '41', 'aapanel-managed01', ['name' => 'shop.cz', 'path' => '/www/wwwroot/shop.cz'], 'srv_tools');

    $listed = collect(aaToolsAdapter()->nodeProjects($site))->pluck('remote_id')->all();

    // `str_starts_with('/www/wwwroot/shop.cz.eu/app', '/www/wwwroot/shop.cz')` is true: the neighbour's app used to be ours
    expect($listed)->toBe(['shop_api', 'root_app']);
    expect(fn () => aaToolsAdapter()->nodeProjectAction($site, 'eu_app', 'stop'))->toThrow(ProviderException::class);
});

it('stops and removes the site\'s apps when the site goes, and leaves the neighbour\'s alone', function () {
    $apps = ['shop_api' => ['path' => '/www/wwwroot/shop.cz/api', 'run' => true], 'eu_app' => ['path' => '/www/wwwroot/shop.cz.eu/app', 'run' => true]];
    $calls = [];
    appsPanelFake($apps, $calls);
    $site = new ResourceRef('site', '41', 'aapanel-managed01', ['name' => 'shop.cz', 'path' => '/www/wwwroot/shop.cz'], 'srv_tools');

    $result = aaToolsAdapter()->terminate($site);

    expect($result->data['apps_removed'])->toBe(1)->and(array_keys($apps))->toBe(['eu_app']);
    // stopped BEFORE it was removed, and both before the site itself went (H505: the listener is released once it has stopped)
    $order = array_values(array_filter($calls, fn (string $c) => str_contains($c, 'shop_api') || str_contains($c, 'DeleteSite')));
    expect($order[0])->toContain('stop_project shop_api')->and($order[1])->toContain('remove_project shop_api')->and($order[2])->toContain('DeleteSite');
});

it('stops a suspended site\'s app and starts exactly that one again on resume', function () {
    [, $org] = $this->customerWithOrganization();
    $site = featureWebService($org, 'aapanel');
    // one app running, one the customer had stopped themselves
    $apps = ['shop_api' => ['path' => '/www/wwwroot/shop.cz/api', 'run' => true], 'shop_admin' => ['path' => '/www/wwwroot/shop.cz/admin', 'run' => false]];
    $calls = [];
    appsPanelFake($apps, $calls);
    $services = app(ServiceService::class);
    $system = CommandContext::system('dunning')->withScope($org->id);

    expect(driveOperation($services->requestAction($site, 'suspend', $system, 'apps-suspend', ['reason' => 'dunning']))->state)->toBe(Operation::SUCCEEDED);
    // the vhost was stopped and the app went on answering for the site's domains through its own proxy
    expect($apps['shop_api']['run'])->toBeFalse()->and($apps['shop_admin']['run'])->toBeFalse()
        ->and(data_get($site->refresh()->tags, SuspensionDepth::TAG.'.app'))->toBe(['shop_api']);

    expect(driveOperation($services->requestAction($site, 'resume', $system, 'apps-resume', ['reason' => 'paid', 'lift' => 'payment']))->state)->toBe(Operation::SUCCEEDED);
    expect($apps['shop_api']['run'])->toBeTrue()->and($apps['shop_admin']['run'])->toBeFalse(); // what the customer had stopped stays stopped
});
