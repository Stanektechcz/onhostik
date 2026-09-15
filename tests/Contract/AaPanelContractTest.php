<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Models\Region;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Provisioning\Scheduling\NodeScheduler;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\AaPanel\AaPanelWebProvider;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\ResourceSpec;

function aaAdapter(): AaPanelWebProvider
{
    $_ENV['AAPANEL_MANAGED01_API_KEY'] = 'aa-key-123';
    $instance = ProviderInstance::query()->firstOrCreate(['key' => 'aapanel-managed01'], ['provider' => 'aapanel', 'name' => 'aaPanel managed01', 'base_url' => 'https://managed01.mgmt.test:8888', 'secret_ref' => 'env://AAPANEL_MANAGED01', 'state' => 'active', 'options' => ['verify_tls' => false]]);
    $registry = app(ProviderRegistry::class);
    $registry->register('aapanel', AaPanelWebProvider::class);

    return $registry->forInstance($instance);
}

it('signs requests with md5(time + md5(key)) and creates a site once', function () {
    Http::fake([
        'managed01.mgmt.test:8888/data?action=getData&table=sites' => Http::sequence()->push(['data' => [], 'page' => ''])->push(['data' => [['id' => 41, 'name' => 'shop.cz', 'path' => '/www/wwwroot/shop.cz', 'status' => '1']], 'page' => '']),
        'managed01.mgmt.test:8888/site?action=AddSite' => Http::response(['siteStatus' => true, 'ftpStatus' => false, 'databaseStatus' => false, 'siteId' => 41]),
    ]);
    $adapter = aaAdapter();
    $first = $adapter->provision(new ResourceSpec('srv_m1', 'website', 'ord-3:provision.managed:v1', ['domain' => 'shop.cz', 'php_version' => '8.3']));
    $second = $adapter->provision(new ResourceSpec('srv_m1', 'website', 'ord-3:provision.managed:v1', ['domain' => 'shop.cz', 'php_version' => '8.3']));
    expect($first->ref->remoteId)->toBe('41')->and($first->alreadyExisted)->toBeFalse()->and($second->alreadyExisted)->toBeTrue();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'action=AddSite')) {
            return false;
        }
        $time = (int) $request['request_time'];
        $expected = md5($time.md5('aa-key-123'));

        return $request['request_token'] === $expected && json_decode($request['webname'], true)['domain'] === 'shop.cz' && $request['version'] === '83';
    });
    expect(DB::table('provider_calls')->where('action', 'site.add')->value('request'))->not->toContain('aa-key-123');
});

it('normalises status:false into typed exceptions and bare-string bodies', function () {
    Http::fake([
        'managed01.mgmt.test:8888/site?action=SetPHPVersion' => Http::response(['status' => false, 'msg' => 'IP whitelist validation failed']),
        'managed01.mgmt.test:8888/files?action=GetFileBody' => Http::response(['status' => true, 'data' => "line1\nline2\nline3"]),
    ]);
    $adapter = aaAdapter();
    try {
        $adapter->setPhpVersion(new ResourceRef('site', '41', 'aapanel-managed01', ['name' => 'shop.cz']), '8.4');
        $this->fail('expected exception');
    } catch (ProviderException $e) {
        expect($e->errorCode)->toBe(ProviderErrorCode::AUTH);
    }
    expect($adapter->tailLog(new ResourceRef('site', '41', 'aapanel-managed01', ['name' => 'shop.cz']), 'access', 2))->toBe(['line2', 'line3']);
});

it('imports the aaPanel host as one managed node through the panel API; the scheduler error names the fix (audit §5z)', function () {
    Http::fake(['managed01.mgmt.test:8888/system?action=GetSystemTotal' => Http::response(['version' => '8.0.6', 'memTotal' => 15988, 'memRealUsed' => 7312])]);
    aaAdapter();
    Region::query()->firstOrCreate(['code' => 'cz1'], ['name' => 'Praha', 'country' => 'CZ', 'state' => 'active']);
    $this->artisan('onhost:nodes:discover aapanel-managed01')->expectsOutputToContain('--region')->assertExitCode(1);
    $this->artisan('onhost:nodes:discover aapanel-managed01 --region=cz1')->expectsOutputToContain('1 node(s) imported')->assertExitCode(0);
    $node = Node::query()->where('provider_instance_id', ProviderInstance::query()->where('key', 'aapanel-managed01')->value('id'))->sole();
    expect($node->role)->toBe('managed')->and($node->region_code)->toBe('cz1')->and($node->state)->toBe('active')->and((int) $node->capacity['ram_mb'])->toBe(15988);

    $instance = ProviderInstance::query()->where('key', 'aapanel-managed01')->firstOrFail();
    try {
        app(NodeScheduler::class)->pick(['role' => 'web', 'region' => 'cz1', 'provider' => 'aapanel', 'placement' => ['instance_id' => $instance->id, 'instance_key' => 'aapanel-managed01']]);
        $message = '';
    } catch (DomainError $e) {
        $message = $e->getMessage();
    }
    expect($message)->toContain('php artisan onhost:nodes:discover aapanel-managed01');
});
