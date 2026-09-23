<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\Web\SiteIntegrityCheck;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Providers\AaPanel\AaPanelWebProvider;
use Onhost\Providers\Shell\ScriptedShell;

/*
 * The platform could quarantine a site for abuse and let it out again, but nothing ever noticed: a hacked WordPress
 * sending spam or serving malware was found by the people it was sent to — a blocklist, another host's abuse desk,
 * the customer's own visitors — and by then the node's address is on a list and every other customer on it pays for
 * it. This looks for the two marks a compromise leaves that can be read with what the platform already has on the
 * node, and it reports: acting stays with the abuse case, which has a statement of reasons and a way back.
 */

beforeEach(fn () => Http::preventStrayRequests());
afterEach(fn () => AaPanelWebProvider::$shellFactory = null);

/** A node answering the two looks: what is in the upload folders, and what carries a web shell's fingerprints. */
function integrityNode(string $uploads, string $shells): ScriptedShell
{
    $shell = new ScriptedShell(['/^id -u/' => [0, "5001\n"], '/^find /' => [0, $uploads], '/^grep -REl/' => [0, $shells], '/du -sb/' => [0, "1024\n10\n"]]);
    AaPanelWebProvider::$shellFactory = fn () => $shell;

    return $shell;
}

it('notices code where a site only keeps what visitors uploaded', function () {
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok', 'data' => [['id' => 41, 'name' => 'shop.cz', 'path' => '/www/wwwroot/shop.cz']], 'page' => '']));
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    integrityNode("/www/wwwroot/shop.cz/wp-content/uploads/2026/09/x.php\n/www/wwwroot/shop.cz/files/shell.phtml\n", '');

    $findings = app(SiteIntegrityCheck::class)->scan($service);

    expect($findings)->toBeArray()->toHaveCount(1)
        ->and($findings[0]['kind'])->toBe('php_in_uploads')
        ->and($findings[0]['files'])->toBe(['wp-content/uploads/2026/09/x.php', 'files/shell.phtml']) // named the way the customer knows them
        ->and($findings[0]['more'])->toBeFalse();
});

it('notices the fingerprints the ready-made shells carry, and tells the operators once a day', function () {
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok', 'data' => [['id' => 41, 'name' => 'shop.cz', 'path' => '/www/wwwroot/shop.cz']], 'page' => '']));
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $shell = integrityNode('', "/www/wwwroot/shop.cz/wp-includes/class-wp-db2.php\n");

    $stats = app(SiteIntegrityCheck::class)->run();

    expect($stats)->toMatchArray(['checked' => 1, 'suspicious' => 1, 'errors' => 0])
        ->and(data_get($service->fresh()->tags, 'integrity.findings.0.kind'))->toBe('web_shell_marks')
        ->and(OutboxMessage::query()->where('name', 'service.integrity.suspicious')->count())->toBe(1);

    // the look is repeated, the telling is not
    app(SiteIntegrityCheck::class)->run();
    expect(OutboxMessage::query()->where('name', 'service.integrity.suspicious')->count())->toBe(1);

    // what it asked the node: bounded, and never inside vendor or node_modules
    $grep = collect($shell->calls)->pluck('command')->first(fn (string $c) => str_starts_with($c, 'grep'));
    expect($grep)->toContain('--exclude-dir=')->toContain('node_modules')->toContain('head -')
        ->and(collect($shell->calls)->pluck('command')->first(fn (string $c) => str_starts_with($c, 'find')))->toContain('head -');
});

it('says nothing about a site that looks like itself', function () {
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok', 'data' => [['id' => 41, 'name' => 'shop.cz', 'path' => '/www/wwwroot/shop.cz']], 'page' => '']));
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    integrityNode('', '');

    expect(app(SiteIntegrityCheck::class)->run())->toMatchArray(['checked' => 1, 'suspicious' => 0])
        ->and(data_get($service->fresh()->tags, 'integrity.findings'))->toBe([])
        ->and(OutboxMessage::query()->where('name', 'service.integrity.suspicious')->exists())->toBeFalse();
});

it('takes nothing a shell says that is not a path in the site', function () {
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok', 'data' => [['id' => 41, 'name' => 'shop.cz', 'path' => '/www/wwwroot/shop.cz']], 'page' => '']));
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    // a banner, an error, another customer's path: none of it is a finding about this site
    integrityNode("grep: /root/.bashrc: Permission denied\n/www/wwwroot/jiny-zakaznik.cz/uploads/x.php\n", '');

    expect(app(SiteIntegrityCheck::class)->scan($service))->toBe([]);
});

it('never acts on what it found: it is a reason to look, not a reason to switch a business off', function () {
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok', 'data' => [['id' => 41, 'name' => 'shop.cz', 'path' => '/www/wwwroot/shop.cz']], 'page' => '']));
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    integrityNode("/www/wwwroot/shop.cz/uploads/x.php\n", "/www/wwwroot/shop.cz/index.php\n");

    app(SiteIntegrityCheck::class)->run();

    expect($service->fresh()->state)->toBe(ServiceStateMachine::ACTIVE)
        ->and($service->fresh()->suspended_at)->toBeNull();
});

it('shows the customer what was found, because they are the one who can clean it up', function () {
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok', 'data' => [['id' => 41, 'name' => 'shop.cz', 'path' => '/www/wwwroot/shop.cz']], 'page' => '']));
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    integrityNode("/www/wwwroot/shop.cz/uploads/x.php\n", '');

    app(SiteIntegrityCheck::class)->run();
    $security = app(ServiceFeatures::class)->features($service->fresh())['security']['options'];

    expect($security['integrity']['findings'][0]['files'])->toBe(['uploads/x.php'])
        ->and($security['integrity']['checked_at'])->not->toBeNull();
});
