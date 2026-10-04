<?php

declare(strict_types=1);

use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\CapabilityMatrix;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RoleCatalog;
use Onhost\Domain\Identity\Authorization\RoleResolver;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Services\Access\AccessLevels;
use Onhost\Domain\Services\Access\ServiceAccessService;
use Onhost\Domain\Services\Commands\ServiceActionCommand;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;
use Tests\TestCase;

/*
 * TASK-0043 (permission program S1-03, D4; audit PA-05/G3/G6, SE-1 in part): a customer chooses what a person may do from a
 * closed family × level matrix — never from permission keys, never as a free custom role (owner default O6). "Operate" is the
 * new narrow level (restart, PHP version, caches, certificates) with no file writes, cron, databases, logins or shell;
 * "delete data" is its own tick; every permission and every cell is said in plain words, cs and en.
 */

beforeEach(function () {
    $this->seed(NotificationTemplateSeeder::class);
    Http::preventStrayRequests();
    Queue::fake(); // what an action does on a panel is not the subject here
});

/** What operate may run, written out: anything else reaching this level is a matrix mistake that widens rights. */
const CMX_OPERATE = ['power', 'php.set', 'wp.cache', 'cdn.purge', 'ssl.issue', 'https.force'];

/** The per-object deletes of data that the `data_delete` tick carries (copies stay the owner's: backup.delete & co., D29.2). */
const CMX_DATA_DELETE = ['site.delete', 'database.delete', 'file.delete', 'mailbox.delete', 'gamedb.delete', 'gfile.delete', 'staging.delete'];

/** A person already in the organization (a guest) with one service shared with the given capability ticks. */
function cmxGuest(TestCase $test, User $owner, Organization $org, Service $service, string $email, array $capabilities): User
{
    $guest = User::query()->create(['email' => $email, 'name' => 'Kolega', 'password' => 'Correct-Horse-Battery-9', 'state' => 'active']);
    app(OrganizationService::class)->attachMember($org, $guest, 'guest', CommandContext::system('test'), true);
    app(ServiceAccessService::class)->share($org, $service, $email, $capabilities, cmxOwnerContext($owner, $org));
    app(Authorizer::class)->forget($guest);

    return $guest;
}

/** The owner in the portal with a fresh step-up (TestCase::contextFor() is protected). */
function cmxOwnerContext(User $owner, Organization $org): CommandContext
{
    return new CommandContext('user', $owner->id, $org->id, null, '127.0.0.1', 'pest', 'test-session', stepUpMethod: 'totp');
}

function cmxMay(User $user, Service $service, string $action): bool
{
    return app(Authorizer::class)->can($user, ServiceActionCommand::permissionFor($action), CommandScope::resource($service->id, $service->organization_id, $service->project_id));
}

it('decides every family × level cell: a grant from the existing presets or a stated reason, nothing else', function () {
    expect(CapabilityMatrix::FAMILIES)->toBe(['web', 'mail', 'dns', 'compute', 'game', 'database', 'apps'])
        ->and(CapabilityMatrix::LEVELS)->toBe(['view', 'operate', 'manage', 'console', 'data_delete'])
        ->and(CapabilityMatrix::cells())->toHaveCount(35);

    foreach (CapabilityMatrix::cells() as $key => $cell) {
        expect($cell['grant'] === null)->toBe($cell['reason'] !== null, "{$key}: a grant or a reason, never both");
        if ($cell['grant'] === null) {
            continue;
        }
        foreach ($cell['grant']['roles'] as $role) {
            expect(RoleResolver::exists($role))->toBeTrue("{$key}: {$role} is a catalogue role")
                ->and(RoleCatalog::all()[$role]['staff'])->toBeFalse("{$key}: never a staff role");
        }
        // service families are shared per service (O7: sharing granularity is service/project); domains stay organization-wide
        $resource = $cell['family'] !== 'dns';
        expect($cell['grant']['scope'])->toBe($resource ? 'resource' : 'organization', $key);
        foreach ($cell['grant']['roles'] as $role) {
            expect(RoleCatalog::isResourceRole($role))->toBe($resource, "{$key}: {$role}");
        }
    }
    // the levels a family has no use for are closed with a reason, not offered empty
    expect(CapabilityMatrix::reason('mail', 'console'))->not->toBeNull()
        ->and(CapabilityMatrix::reason('dns', 'console'))->not->toBeNull()
        ->and(CapabilityMatrix::grant('compute', 'console'))->not->toBeNull()
        ->and(CapabilityMatrix::grant('dns', 'manage'))->toBe(['scope' => 'organization', 'roles' => ['domain_manager']]);
    // the service family of every product maps to at most one matrix family
    expect(CapabilityMatrix::familyOf('managed'))->toBe('web')->and(CapabilityMatrix::familyOf('cloud'))->toBe('compute')
        ->and(CapabilityMatrix::familyOf('data'))->toBe('database')->and(CapabilityMatrix::familyOf('domain'))->toBe('dns')
        ->and(CapabilityMatrix::familyOf('addon'))->toBeNull();
});

it('keeps fam:mail:operate from deleting a mailbox, and operate anywhere from files, cron, databases, logins and a shell', function () {
    expect(AccessLevels::actions('mail', 'operate'))->toContain('power')->not->toContain('mailbox.delete')->not->toContain('mailbox.create');
    foreach (CapabilityMatrix::FAMILIES as $family) {
        if (CapabilityMatrix::grant($family, 'operate') === null || $family === 'dns') {
            continue;
        }
        $actions = AccessLevels::actions($family, 'operate');
        sort($actions);
        $expected = CMX_OPERATE;
        sort($expected);
        expect($actions)->toBe($expected, "{$family}:operate runs the operate list and nothing else");
    }
    expect(CapabilityMatrix::permissions('web', 'operate'))->not->toContain('service.manage')->not->toContain('service.console')->not->toContain('service.data.delete');
});

it('adds exactly the per-object data deletes with data_delete, and keeps them in manage as before', function () {
    $deletes = AccessLevels::actions('web', 'data_delete');
    sort($deletes);
    $expected = CMX_DATA_DELETE;
    sort($expected);
    expect($deletes)->toBe($expected)
        ->and(AccessLevels::actions('mail', 'data_delete'))->toContain('mailbox.delete')->not->toContain('mailbox.create')->not->toContain('backup.delete')
        ->and(CapabilityMatrix::reason('compute', 'data_delete'))->not->toBeNull(); // a VM's copies are the owner's; nothing else to delete
    // manage still deletes what it deleted (svc_manage is unchanged, PresetsUnchangedTest), console still includes manage
    expect(AccessLevels::actions('web', 'manage'))->toContain('mailbox.delete', 'file.delete', 'file.save', 'cron.create', 'power')
        ->and(AccessLevels::actions('web', 'console'))->toContain('command.run', 'file.save');
});

it('lets an operate guest restart and switch PHP, but not write a file, add cron or delete a mailbox; data_delete adds the delete', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $site = featureWebService($org, 'aapanel');
    $mail = featureMailService($org, 'posta-firma.cz');

    $operator = cmxGuest($this, $owner, $org, $site, 'operator@cmx.test', ['operate']);
    expect(cmxMay($operator, $site, 'power'))->toBeTrue()->and(cmxMay($operator, $site, 'php.set'))->toBeTrue()
        ->and(cmxMay($operator, $site, 'file.save'))->toBeFalse()->and(cmxMay($operator, $site, 'cron.create'))->toBeFalse()
        ->and(cmxMay($operator, $site, 'ftp.create'))->toBeFalse()->and(cmxMay($operator, $site, 'database.create'))->toBeFalse()
        ->and(cmxMay($operator, $site, 'command.run'))->toBeFalse()->and(cmxMay($operator, $site, 'file.delete'))->toBeFalse();
    // through the bus, as the portal asks it
    $h = ['X-Organization' => $org->id];
    $this->actingAs($operator, 'sanctum')->postJson("/v1/services/{$site->id}/actions", ['action' => 'php.set', 'params' => ['version' => '8.4']], $h + ['Idempotency-Key' => 'cmx-1'])->assertAccepted();
    $this->postJson("/v1/services/{$site->id}/actions", ['action' => 'file.save', 'params' => ['path' => '/index.php', 'content' => '<?php echo 1;']], $h + ['Idempotency-Key' => 'cmx-2'])->assertForbidden();
    $this->postJson("/v1/services/{$site->id}/actions", ['action' => 'cron.create', 'params' => ['command' => 'php x.php']], $h + ['Idempotency-Key' => 'cmx-3'])->assertForbidden();

    $mailOperator = cmxGuest($this, $owner, $org, $mail, 'posta@cmx.test', ['operate']);
    expect(cmxMay($mailOperator, $mail, 'mailbox.delete'))->toBeFalse()->and(cmxMay($mailOperator, $mail, 'mailbox.create'))->toBeFalse();
    $cleaner = cmxGuest($this, $owner, $org, $mail, 'uklid@cmx.test', ['operate', 'data_delete']);
    expect(cmxMay($cleaner, $mail, 'mailbox.delete'))->toBeTrue()->and(cmxMay($cleaner, $mail, 'mailbox.create'))->toBeFalse()
        ->and(cmxMay($cleaner, $mail, 'backup.delete'))->toBeFalse();
    expect(app(ServiceAccessService::class)->forService($mail))->toHaveCount(2);
});

it('keeps I1 for the new ticks: nobody shares operate or data_delete above what they hold on the service', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $site = featureWebService($org, 'aapanel');
    // a member manager who may only operate the services (a role the catalogue does not have; the authorizer reads the rows)
    DB::table('role_permissions')->insert([['role_key' => 'cmx_operate_admin', 'permission_key' => 'organization.members.manage'], ['role_key' => 'cmx_operate_admin', 'permission_key' => 'service.read'], ['role_key' => 'cmx_operate_admin', 'permission_key' => 'backup.read'], ['role_key' => 'cmx_operate_admin', 'permission_key' => 'service.operate']]);
    $admin = User::query()->create(['email' => 'admin@cmx.test', 'name' => 'Admin', 'password' => 'Correct-Horse-Battery-9', 'state' => 'active']);
    app(OrganizationService::class)->attachMember($org, $admin, 'guest', CommandContext::system('test'), true);
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $admin->id, 'role_key' => 'cmx_operate_admin', 'scope_type' => 'organization', 'scope_id' => $org->id, 'organization_id' => $org->id]);
    app(Authorizer::class)->forget($admin);
    $access = app(ServiceAccessService::class);
    $context = $this->contextFor($admin, $org, 'totp');

    expect(fn () => $access->share($org, $site, 'kolega@cmx.test', ['data_delete'], $context))->toThrow(DomainError::class, 'service.data.delete')
        ->and(fn () => $access->share($org, $site, 'kolega@cmx.test', ['manage'], $context))->toThrow(DomainError::class, 'service.manage');
    expect($access->share($org, $site, 'kolega@cmx.test', ['operate'], $context)->capabilities)->toBe(['view', 'operate']);
    // and the owner, who holds everything, hands out both ticks
    expect($access->share($org, $site, 'dalsi@cmx.test', ['data_delete', 'operate'], $this->contextFor($owner, $org, 'totp'))->capabilities)->toBe(['view', 'operate', 'data_delete']);
});

it('says in plain words, cs and en, what every permission lets a person do, with a warning where it can hurt', function () {
    foreach (['cs', 'en'] as $locale) {
        foreach (PermissionCatalog::keys() as $permission) {
            $sentence = CapabilityMatrix::sentence($permission, $locale);
            expect($sentence['can'])->toBeString()->not->toBe('')->not->toBe($permission, "{$locale} {$permission}");
            if (PermissionCatalog::risk($permission) !== PermissionCatalog::NORMAL || in_array($permission, ['service.manage', 'service.console', 'service.data.delete'], true)) {
                expect($sentence['warning'])->toBeString()->not->toBe('', "{$locale} {$permission} needs a warning");
            }
        }
        // no sentence for a permission the catalogue does not know (a stale line would describe something nobody can hold)
        expect(array_diff(CapabilityMatrix::describedPermissions($locale), PermissionCatalog::keys()))->toBe([]);
    }
    expect(CapabilityMatrix::sentence('service.operate', 'cs')['can'])->not->toBe(CapabilityMatrix::sentence('service.operate', 'en')['can']);
});

it('describes every offered cell in cs and en, and tells why a cell is not offered', function () {
    foreach (['cs', 'en'] as $locale) {
        foreach (CapabilityMatrix::cells() as $key => $cell) {
            $text = CapabilityMatrix::describe($cell['family'], $cell['level'], $locale);
            expect($text['family'])->not->toBe($cell['family'])->and($text['level'])->not->toBe($cell['level']);
            if ($cell['grant'] !== null) {
                expect($text['can'])->toBeString()->not->toBe('', "{$locale} {$key}")->and($text['reason'])->toBeNull();
            } else {
                expect($text['reason'])->toBeString()->not->toBe('', "{$locale} {$key}")->and($text['can'])->toBeNull();
            }
        }
    }
});

it('offers the owner the levels of the service family, with the ticks and sentences, next to the share list', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $mail = featureMailService($org, 'posta-firma.cz');

    $response = $this->actingAs($owner, 'sanctum')->getJson("/v1/services/{$mail->id}/access?locale=en", ['X-Organization' => $org->id])->assertOk();
    expect(array_column($response->json('capabilities'), 'key'))->toBe(['view', 'operate', 'manage', 'console', 'data_delete', 'backups', 'restore', 'assistant'])
        ->and($response->json('capabilities.1.sentences.can'))->toBeString()->not->toBe('');
    $levels = collect($response->json('levels'))->keyBy('level');
    expect($response->json('family'))->toBe('mail')
        ->and($levels['operate']['capabilities'])->toBe(['view', 'operate'])
        ->and($levels['console']['offered'])->toBeFalse()->and($levels['console']['reason'])->toBeString()
        ->and($levels['data_delete']['capabilities'])->toBe(['view', 'data_delete']);
});
