<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Service;

/*
 * Declarative spec (audit §5e-6): GET returns the configurable state of a site as one document, PUT converges the
 * site on the sections given — only what differs becomes an action, unchanged sections are reported as such,
 * sections the plan does not offer are skipped, unknown ones refused.
 */

beforeEach(fn () => Http::preventStrayRequests());

it('reads the spec of a site and converges it: php, proxies, index and redirect change through ordinary actions, the rest is reported unchanged', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $service->forceFill(['desired_spec' => array_merge((array) $service->desired_spec, ['php_version' => '8.2']), 'actual_spec' => array_merge((array) $service->actual_spec, ['php_version' => '8.2'])])->save();
    $proxies = [];
    $index = 'index.php,index.html';
    $redirect = null;
    Http::fake(function ($request) use (&$proxies, &$index, &$redirect) {
        if (! str_starts_with($request->url(), AAP)) {
            return null;
        }
        $q = (string) parse_url($request->url(), PHP_URL_QUERY);
        $body = $request->data();

        return match (true) {
            str_contains($q, 'action=GetPHPVersion') => Http::response([['version' => '82', 'name' => 'PHP-8.2'], ['version' => '83', 'name' => 'PHP-8.3']]),
            str_contains($q, 'action=GetProxyList') => Http::response(array_values($proxies)),
            str_contains($q, 'action=CreateProxy') => (function () use (&$proxies, $body) {
                $proxies[$body['proxyname']] = ['proxyname' => $body['proxyname'], 'proxydir' => $body['proxydir'], 'proxysite' => $body['proxysite'], 'type' => 1, 'cache' => (int) $body['cache'], 'todomain' => $body['todomain']];

                return Http::response(['status' => true, 'msg' => 'ok']);
            })(),
            str_contains($q, 'action=RemoveProxy') => (function () use (&$proxies, $body) {
                unset($proxies[$body['proxyname']]);

                return Http::response(['status' => true, 'msg' => 'ok']);
            })(),
            str_contains($q, 'action=GetIndex') => Http::response(['status' => true, 'msg' => '"'.$index.'"']),
            str_contains($q, 'action=SetIndex') => (function () use (&$index, $body) {
                $index = $body['Index'];

                return Http::response(['status' => true, 'msg' => 'ok']);
            })(),
            str_contains($q, 'action=GetRedirectList') => Http::response($redirect === null ? [] : [['redirectname' => 'onhost', 'tourl' => $redirect['target'], 'redirecttype' => $redirect['type']]]),
            str_contains($q, 'action=CreateNewRedirect') => (function () use (&$redirect, $body) {
                $redirect = ['target' => $body['tourl'] ?? ($body['redirect_url'] ?? ''), 'type' => (string) ($body['redirecttype'] ?? '301')];

                return Http::response(['status' => true, 'msg' => 'ok']);
            })(),
            str_contains($q, 'action=DeleteRedirect') => (function () use (&$redirect) {
                $redirect = null;

                return Http::response(['status' => true, 'msg' => 'ok']);
            })(),
            default => Http::response(['status' => true, 'msg' => 'ok']),
        };
    });
    $this->actingAs($user, 'sanctum');

    $spec = $this->getJson("/v1/services/{$service->id}/spec")->assertOk()->json('data');
    expect($spec['php'])->toBe('8.2')->and($spec['proxies'])->toBe([])->and($spec['index'])->toBe(['index.php', 'index.html'])->and($spec['redirect']['target'])->toBe('')
        ->and($spec['features'])->toContain('php', 'proxies', 'index', 'redirect', 'security', 'cron', 'monitoring')->and($spec['monitoring'])->toHaveKey('enabled');

    // converge: three sections change, index stays (same list), an unknown section is refused
    $this->putJson("/v1/services/{$service->id}/spec", ['spec' => ['nope' => 1]])->assertStatus(422)->assertJsonPath('error', 'spec_section_unknown');
    $result = $this->putJson("/v1/services/{$service->id}/spec", ['spec' => [
        'php' => '8.3',
        'proxies' => [['name' => 'api', 'target' => 'http://127.0.0.1:3000/', 'path' => 'api']],
        'index' => ['index.php', 'index.html'],
        'redirect' => ['target' => 'https://novy.cz/', 'type' => '302'],
    ]])->assertOk()->json();
    expect(collect($result['operations'])->pluck('action')->all())->toBe(['php.set', 'proxies.set', 'redirect.set'])->and($result['unchanged'])->toBe(['index'])->and($result['skipped'])->toBe([]);
    foreach ($result['operations'] as $op) {
        expect(driveOperation(Operation::query()->findOrFail($op['operation_id']))->state)->toBe(Operation::SUCCEEDED);
    }
    expect(Service::query()->findOrFail($service->id)->desired_spec['php_version'])->toBe('8.3')->and(array_keys($proxies))->toBe(['api'])->and($redirect['target'])->toBe('https://novy.cz/');

    // the same document again: nothing to do
    Service::query()->whereKey($service->id)->update(['actual_spec' => json_encode(array_merge((array) $service->actual_spec, ['php_version' => '8.3']))]);
    $again = $this->putJson("/v1/services/{$service->id}/spec", ['spec' => ['php' => '8.3', 'proxies' => [['name' => 'API', 'target' => 'http://127.0.0.1:3000', 'path' => '/api/']], 'redirect' => ['target' => 'https://novy.cz/', 'type' => '302']]])->assertOk()->json();
    expect($again['operations'])->toBe([])->and($again['unchanged'])->toBe(['php', 'proxies', 'redirect']);
    $spec = $this->getJson("/v1/services/{$service->id}/spec")->assertOk()->json('data');
    expect($spec['php'])->toBe('8.3')->and($spec['proxies'][0])->toMatchArray(['name' => 'api', 'target' => 'http://127.0.0.1:3000', 'path' => '/api'])->and($spec['redirect'])->toBe(['target' => 'https://novy.cz/', 'type' => '302']);
});
