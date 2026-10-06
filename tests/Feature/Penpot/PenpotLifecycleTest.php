<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Notifications\Lexicon;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Penpot\PenpotHealth;
use Onhost\Domain\Services\Penpot\PenpotInstances;
use Onhost\Domain\Services\Penpot\PenpotSecrets;
use Onhost\Domain\Services\Penpot\PenpotSweep;
use Onhost\Domain\Services\ServiceHealthCheck;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Secrets\SecretStore;
use Onhost\Providers\Penpot\PenpotDockerProvider;

/*
 * TASK-0123 — Penpot for web hosting: one Docker Compose stack per service on a Penpot node, driven over SSH. Everything here
 * runs against a node double (no server is touched): the commands the adapter sends, the files it writes over SFTP, and what
 * the platform keeps — and does not keep — of the secrets.
 */

require_once __DIR__.'/../../Support/Penpot/PenpotDoubles.php';

beforeEach(function () {
    Http::preventStrayRequests();
});

afterEach(function () {
    PenpotDockerProvider::$shellFactory = null;
    PenpotDockerProvider::$transportFactory = null;
});

it('provisions a Penpot stack end to end: node, vault secrets, compose up, proxy site, owner profile, probe, ACTIVE', function () {
    $node = penpotLab();
    [$user, $org] = $this->customerWithOrganization(['email' => 'designer@studio.test']);

    $service = penpotProvisioned($org, $this->contextFor($user, $org));
    $operation = Operation::query()->where('service_id', $service->id)->where('kind', 'provision.penpot')->firstOrFail();

    expect($operation->state)->toBe(Operation::SUCCEEDED)->and($service->state)->toBe(ServiceStateMachine::ACTIVE)
        ->and($service->family)->toBe('penpot')->and($service->hostname)->toEndWith('.penpot.onhost.cz');
    $stack = PenpotInstances::stackName($service);
    $binding = ProviderBinding::query()->where('service_id', $service->id)->firstOrFail();
    expect($binding->remote_type)->toBe('stack')->and($binding->remote_id)->toBe($stack)->and((int) $binding->meta['port'])->toBe(19002); // 19001 is taken on the node
    expect(data_get($service->tags, 'penpot.url'))->toBe('https://'.$service->hostname)->and(data_get($service->tags, 'penpot.owner_email'))->toBe('designer@studio.test')
        ->and(data_get($service->tags, 'access.url'))->toBe('https://'.$service->hostname);

    // the stack's files on the node: secrets in .env (0600), never in the compose file; the official variable names
    $secrets = app(SecretStore::class)->read(PenpotSecrets::ref($service));
    expect(strlen($secrets['secret_key']))->toBeGreaterThanOrEqual(64)->and(strlen($secrets['db_password']))->toBeGreaterThanOrEqual(24);
    $env = PenpotFilesDouble::$files["/srv/onhost-penpot/{$stack}/.env"];
    $compose = PenpotFilesDouble::$files["/srv/onhost-penpot/{$stack}/docker-compose.yaml"];
    expect($env)->toContain('PENPOT_SECRET_KEY='.$secrets['secret_key'])->toContain('PENPOT_DATABASE_PASSWORD='.$secrets['db_password'])
        ->toContain('PENPOT_PUBLIC_URI=https://'.$service->hostname)->toContain('disable-registration')->toContain('enable-prepl-server')->toContain('ONHOST_PORT=19002');
    expect(PenpotFilesDouble::$modes["/srv/onhost-penpot/{$stack}/.env"])->toBe(0600);
    expect($compose)->not->toContain($secrets['secret_key'])->toContain('penpotapp/backend:${PENPOT_VERSION}')->toContain('127.0.0.1:${ONHOST_PORT}:8080')
        ->toContain('PENPOT_REDIS_URI: redis://penpot-valkey/0')->toContain('PENPOT_OBJECTS_STORAGE_FS_DIRECTORY: /opt/data/assets')->toContain('memory: 1728m');
    expect(PenpotFilesDouble::$files["/etc/caddy/onhost-penpot/{$stack}.caddy"])->toContain($service->hostname.' {')->toContain('reverse_proxy 127.0.0.1:19002');

    // the node heard compose up, the proxy reload and the owner profile — and no secret in any command line
    expect($node->matching(' up -d --remove-orphans'))->toHaveCount(1)->and($node->matching('systemctl reload caddy'))->not->toBeEmpty()
        ->and($node->matching('create-profile --email \'designer@studio.test\''))->toHaveCount(1);
    foreach ($node->commands as $command) {
        expect($command)->not->toContain($secrets['secret_key'])->not->toContain($secrets['db_password']);
    }
    // …nor anywhere the platform writes down what happened
    app(OutboxPublisher::class)->relayPending(1000);
    foreach (['operations', 'operation_attempts', 'audit_events', 'outbox_messages', 'provider_calls'] as $table) {
        $dump = json_encode(DB::table($table)->get());
        expect($dump)->not->toContain($secrets['secret_key'])->not->toContain($secrets['db_password']);
    }
    // the customer heard it is ready
    expect(Notification::query()->where('organization_id', $org->id)->where('event', 'penpot.instance.ready')->value('title'))->toBe('Penpot je připraven');
});

it('takes the stack back and fails the service when compose cannot bring it up', function () {
    $node = penpotLab();
    $node->upFails = true;
    [$user, $org] = $this->customerWithOrganization();

    $service = penpotProvisioned($org, $this->contextFor($user, $org));

    expect($service->state)->toBe(ServiceStateMachine::FAILED);
    expect(Operation::query()->where('service_id', $service->id)->where('kind', 'provision.penpot')->value('state'))->toBe(Operation::FAILED);
    expect($node->matching('rm -rf --'))->not->toBeEmpty()->and($node->dirs)->toBe([]); // the half-made stack directory does not stay on the node
});

it('suspends, resumes, backs up and terminates through the ordinary service actions', function () {
    $node = penpotLab();
    [$user, $org] = $this->customerWithOrganization();
    $service = penpotProvisioned($org, $this->contextFor($user, $org));
    $stack = PenpotInstances::stackName($service);
    $services = app(ServiceService::class);
    $system = CommandContext::system('test');

    driveOperation($services->requestAction($service->refresh(), 'suspend', $system, 'pp-suspend', ['reason' => 'test']));
    expect($service->refresh()->state)->toBe(ServiceStateMachine::SUSPENDED)->and($node->stacks[$stack])->toBe('stopped');

    driveOperation($services->requestAction($service->refresh(), 'resume', $system, 'pp-resume', ['reason' => 'test', 'lift' => 'review']));
    expect($service->refresh()->state)->toBe(ServiceStateMachine::ACTIVE)->and($node->stacks[$stack])->toBe('running');

    $backupOp = driveOperation($services->requestAction($service->refresh(), 'backup', $system, 'pp-backup', ['kind' => 'manual']));
    expect($backupOp->state)->toBe(Operation::SUCCEEDED);
    $backup = Backup::query()->where('service_id', $service->id)->where('kind', 'manual')->firstOrFail();
    expect($backup->state)->toBe('completed')->and($backup->remote_id)->toBe($node->backups[$stack][0])->and($node->matching('pg_dump -U penpot'))->toHaveCount(1)
        ->and($node->matching(':/opt/data/assets - | gzip'))->toHaveCount(1);

    $terminate = driveOperation($services->requestAction($service->refresh(), 'terminate', $system, 'pp-terminate', ['reason' => 'test']));
    expect($terminate->state)->toBe(Operation::SUCCEEDED);
    $final = Backup::query()->where('service_id', $service->id)->where('kind', 'final')->firstOrFail();
    expect(data_get($final->meta, 'family'))->toBe('penpot');
    expect(count($node->backups[$stack]))->toBe(2); // the final archive took a fresh dump and pulled it off the node

    $this->travelTo($service->refresh()->terminate_at->copy()->addMinute()); // the restore window is over
    $purge = driveOperation($services->requestAction($service->refresh(), 'purge', $system, 'pp-purge', ['reason' => 'test']));
    expect($purge->state)->toBe(Operation::SUCCEEDED)->and($service->refresh()->state)->toBe(ServiceStateMachine::TERMINATED);
    expect($node->matching('down --volumes --remove-orphans'))->toHaveCount(1)->and(isset($node->stacks[$stack]))->toBeFalse();
    expect($node->matching("rm -f '/etc/caddy/onhost-penpot/{$stack}.caddy'"))->not->toBeEmpty();
    expect(app(SecretStore::class)->exists(PenpotSecrets::ref($service)))->toBeTrue(); // until the termination is delivered…
    app(OutboxPublisher::class)->relayPending(1000);
    expect(app(SecretStore::class)->exists(PenpotSecrets::ref($service)))->toBeFalse(); // …then the keys leave the vault
});

it('lets the owner set the Penpot password with a fresh step-up, and forgets it afterwards', function () {
    $node = penpotLab();
    [$user, $org] = $this->customerWithOrganization(['email' => 'owner@studio.test']);
    $service = penpotProvisioned($org, $this->contextFor($user, $org));
    $headers = ['X-Organization' => $org->id];
    $ownerPassword = implode('-', ['Navrhar', 'Heslo', '2026']); // a test value, built so no scanner takes it for a credential

    $card = $this->actingAs($user, 'sanctum')->getJson("/v1/services/{$service->id}/penpot", $headers)->assertOk();
    expect($card->json('data.open_url'))->toBe('https://'.$service->hostname)->and($card->json('data.owner_email'))->toBe('owner@studio.test')
        ->and($card->json('data.owner_password_set'))->toBeFalse()->and($card->json('data.limits.ram_mb'))->toBe(4096);
    expect(json_encode($card->json()))->not->toContain('secret_key')->not->toContain('db_password');

    $this->actingAs($user, 'sanctum')->postJson("/v1/services/{$service->id}/penpot/owner-password", ['password' => $ownerPassword], $headers + ['Idempotency-Key' => 'pp-pw-1'])
        ->assertForbidden()->assertJsonPath('error', 'step_up_required');

    app(StepUpService::class)->grant($user, 'totp', null, '127.0.0.1');
    $this->actingAs($user, 'sanctum')->postJson("/v1/services/{$service->id}/penpot/owner-password", ['password' => 'krátké'], $headers + ['Idempotency-Key' => 'pp-pw-2'])->assertStatus(422);
    $response = $this->actingAs($user, 'sanctum')->postJson("/v1/services/{$service->id}/penpot/owner-password", ['password' => $ownerPassword], $headers + ['Idempotency-Key' => 'pp-pw-3'])->assertStatus(202);
    $operation = driveOperation(Operation::query()->findOrFail($response->json('operation_id')));

    expect($operation->state)->toBe(Operation::SUCCEEDED)->and($node->matching("update-profile --email 'owner@studio.test' --password '{$ownerPassword}'"))->toHaveCount(1);
    expect(data_get($service->refresh()->tags, 'penpot.owner_password_set'))->toBeTrue();
    expect(json_encode($operation->refresh()->desired))->not->toContain($ownerPassword); // OperationSecrets forgot it
    app(OutboxPublisher::class)->relayPending(1000);
    foreach (['audit_events', 'outbox_messages', 'idempotency_keys'] as $table) {
        expect(json_encode(DB::table($table)->get()))->not->toContain($ownerPassword);
    }
    expect(Notification::query()->where('organization_id', $org->id)->where('event', 'penpot.owner_password.changed')->exists())->toBeTrue();

    // another organization does not find the service at all
    [$stranger, $other] = $this->customerWithOrganization(['email' => 'stranger@else.test']);
    $this->actingAs($stranger, 'sanctum')->getJson("/v1/services/{$service->id}/penpot", ['X-Organization' => $other->id])->assertNotFound();
});

it('probes every running Penpot, tells the customer when one stops answering and starts the daily backup', function () {
    $node = penpotLab();
    [$user, $org] = $this->customerWithOrganization();
    $service = penpotProvisioned($org, $this->contextFor($user, $org));
    $sweep = app(PenpotSweep::class);

    $first = $sweep->run();
    expect($first['answering'])->toBe(1)->and($first['backups'])->toBe(1)->and(data_get($service->refresh()->tags, 'penpot.health.status'))->toBe('up');
    driveOperations();
    expect(Backup::query()->where('service_id', $service->id)->where('kind', 'daily')->value('state'))->toBe('completed');
    expect(PenpotHealth::finding($service->refresh())['level'])->toBe('ok');
    expect(collect(app(ServiceHealthCheck::class)->run($service)['findings'])->pluck('key')->all())->toContain('penpot')->toContain('backup');

    $node->http = 502;
    $sweep->run(false);
    expect(data_get($service->refresh()->tags, 'penpot.health.status'))->toBe('up'); // one miss is a restart, not an outage
    $sweep->run(false);
    expect(data_get($service->refresh()->tags, 'penpot.health.status'))->toBe('down')->and(PenpotHealth::finding($service)['level'])->toBe('bad');
    app(OutboxPublisher::class)->relayPending(1000);
    expect(Notification::query()->where('organization_id', $org->id)->where('event', 'penpot.instance.unreachable')->where('audience', 'customer')->exists())->toBeTrue();

    $node->http = 200;
    $sweep->run(false);
    app(OutboxPublisher::class)->relayPending(1000);
    expect(Notification::query()->where('organization_id', $org->id)->where('event', 'penpot.instance.recovered')->exists())->toBeTrue();
    expect($sweep->run()['backups'])->toBe(0); // today's backup is done
});

it('speaks English to an English organization', function () {
    penpotLab();
    [$user, $org] = $this->customerWithOrganization(['email' => 'lead@studio.uk', 'locale' => 'en'], ['locale' => 'en']);
    $service = penpotProvisioned($org, $this->contextFor($user, $org));
    app(OutboxPublisher::class)->relayPending(1000);

    $row = Notification::query()->where('organization_id', $org->id)->where('event', 'penpot.instance.ready')->firstOrFail();
    expect($row->title)->toBe('Penpot is ready')->and($row->body)->toContain('Sign-in e-mail: lead@studio.uk')->and($row->body)->toContain($service->hostname);
    expect(Lexicon::untranslated($row->title.' '.$row->body))->toBe([]);
});

it('lists the Penpot with the web services in the customer panel, as a row the workbench recognises', function () {
    penpotLab();
    [$user, $org] = $this->customerWithOrganization();
    $service = penpotProvisioned($org, $this->contextFor($user, $org));

    $js = (string) $this->actingAs($user)->get('/surfaces/onhost-panel.js')->assertOk()->getContent();
    $payload = json_decode(substr($js, (int) strpos($js, '{'), (int) strrpos($js, '}') - (int) strpos($js, '{') + 1), true) ?: [];
    $row = collect($payload['services']['web'] ?? [])->firstWhere('id', $service->id);
    expect($row)->not->toBeNull()->and($row['product'])->toBe('penpot')->and($row['apiState'])->toBe('ACTIVE');
    // the workbench module turns that row into the Penpot card (family by product, three tabs, its own endpoint)
    $module = (string) file_get_contents(base_path('apps/surfaces/api/onhost-panel-workbench.api.js'));
    expect($module)->toContain("if (sel && sel.product === 'penpot') return 'penpot';")->toContain("'/services/' + sel.id + '/penpot'")->toContain("'/penpot/owner-password'");
});
