<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\Authorization\RoleCatalog;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\DeploySource;
use Onhost\Platform\Commands\CommandContext;

/*
 * Reading the state of a service is not getting into it (Brain cards H334 and H344). A read-only role — a viewer, an
 * accountant, an auditor — sees that the site runs, its logs, its backups and its operations. It does not get a
 * console, a shell, the contents of files (they hold the site's credentials), revealed passwords, the values of the
 * build environment, or the customer's data as a backup or an export. Taking data away is a permission of its own.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok', 'data' => "<?php\n"]));
});

it('keeps the console with the roles that manage, and the download of data with the roles named for it', function () {
    $roles = RoleCatalog::all();
    $customer = array_filter($roles, fn (array $r) => ! $r['staff']);
    foreach ($customer as $key => $role) {
        // no role gets a console or a shell on the strength of reading alone
        if (in_array('service.console', $role['permissions'], true)) {
            expect(in_array('service.manage', $role['permissions'], true))->toBeTrue("role {$key} opens consoles without managing the service");
        }
    }
    $downloads = array_keys(array_filter($customer, fn (array $r) => in_array('backup.download', $r['permissions'], true)));
    sort($downloads);
    // `svc_backups` is the capability an owner ticks on purpose when sharing ONE service ("stahování záloh") — taking data away stays a decision of its own
    expect($downloads)->toBe(['cloud_operator', 'developer', 'game_operator', 'mail_manager', 'org_admin', 'owner', 'svc_backups']);
    // seeing the list of backups stays with everybody who reads; it no longer carries the data
    expect($roles['viewer']['permissions'])->toContain('backup.read')->not->toContain('backup.download')
        ->and($roles['billing_admin']['permissions'])->not->toContain('backup.download')
        ->and($roles['security_auditor']['permissions'])->not->toContain('backup.download')
        ->and($roles['support_contact']['permissions'])->not->toContain('backup.download');
});

it('lets a read-only member see the state and refuses everything that would let them in or take the data out', function (string $role) {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $backup = Backup::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'kind' => 'site', 'state' => 'completed', 'remote_id' => 'bk-7', 'size_bytes' => 1024, 'started_at' => now()->subHour(), 'finished_at' => now()->subHour()]);
    DeploySource::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'provider' => 'github', 'repository' => 'firma/shop', 'branch' => 'main', 'clone_url' => 'git@github.com:firma/shop.git', 'env' => ['APP_ENV' => 'production', 'STRIPE_KEY' => 'sk_live_example']]);
    $reader = $this->customer();
    app(OrganizationService::class)->attachMember($org, $reader, $role, CommandContext::system('test'), true);
    $this->actingAs($reader, 'sanctum');
    $base = "/v1/services/{$service->id}";

    // diagnostics: allowed
    $this->getJson($base)->assertOk();
    $this->getJson("{$base}/operations")->assertOk();
    expect(collect($this->getJson("{$base}/backups")->assertOk()->json('data'))->pluck('id')->all())->toContain($backup->id);
    $deploy = $this->getJson("{$base}/deploy")->assertOk()->json('data.source');
    expect($deploy['env'])->toBe(['APP_ENV' => null, 'STRIPE_KEY' => null])->and($deploy['env_hidden'])->toBeTrue()->and($deploy['repository'])->toBe('firma/shop'); // the names, not the values

    // getting in: refused
    $this->getJson("{$base}/console-token")->assertForbidden();
    $this->withHeader('Idempotency-Key', "ro-{$role}-1")->postJson("{$base}/actions", ['action' => 'command.run', 'params' => ['command' => 'id']])->assertForbidden();
    $this->withHeader('Idempotency-Key', "ro-{$role}-2")->postJson("{$base}/actions", ['action' => 'shell.create', 'params' => ['user' => 'x', 'password' => 'Correct-Horse-Battery-9']])->assertForbidden();
    // contents and secrets: refused
    $this->get("{$base}/files/download?path=wp-config.php")->assertForbidden();
    $this->getJson("{$base}/resources/databases?reveal=1")->assertForbidden();
    // the data leaving: refused
    $this->get("{$base}/backups/{$backup->id}/download")->assertForbidden();
    $this->get("{$base}/downloads/dl_abcdefghijklmnopqrstuvwx")->assertForbidden();
})->with(['viewer', 'billing_admin', 'security_auditor']);

it('gives the same doors to a role that manages the service', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $backup = Backup::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'kind' => 'site', 'state' => 'completed', 'remote_id' => 'bk-7', 'size_bytes' => 1024, 'started_at' => now()->subHour(), 'finished_at' => now()->subHour()]);
    DeploySource::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'provider' => 'github', 'repository' => 'firma/shop', 'branch' => 'main', 'clone_url' => 'git@github.com:firma/shop.git', 'env' => ['APP_ENV' => 'production']]);
    $developer = $this->customer();
    app(OrganizationService::class)->attachMember($org, $developer, 'developer', CommandContext::system('test'), true);
    $this->actingAs($developer, 'sanctum');
    $base = "/v1/services/{$service->id}";

    expect($this->getJson("{$base}/deploy")->assertOk()->json('data.source.env'))->toBe(['APP_ENV' => 'production']);
    // authorization lets them through; what the panel answers is another matter
    expect($this->get("{$base}/files/download?path=wp-config.php")->status())->not->toBe(403)
        ->and($this->get("{$base}/backups/{$backup->id}/download")->status())->not->toBe(403)
        ->and($this->get("{$base}/downloads/dl_abcdefghijklmnopqrstuvwx")->status())->toBe(410); // authorized, the export just is not there any more
});
