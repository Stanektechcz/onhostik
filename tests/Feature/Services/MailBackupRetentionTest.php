<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Catalog\PlanPromises;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Mail\MailboxBackupPolicy;
use Onhost\Domain\Services\Mail\MailboxBackupRetentionPlan;
use Onhost\Domain\Services\Mail\MailDomains;
use Onhost\Domain\Services\Metering\MetricRegistry;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Services\Web\BackupOperationsCheck;
use Onhost\Domain\Services\Web\BackupScheduler;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * TASK-0024, owner decision 3: a mail plan sells `backup_days` (Mail Business 14, Mail Enterprise 30) and nothing ever set
 * the panel's mailbox backup retention from it. It is set now — but it changes what existing customers' mailboxes keep
 * (and a downgrade deletes backups), so all of it sits behind the default-off rule `mail.backup_retention`:
 *
 * - while the rule is on, a NEW mailbox of a mail plan is created with a daily backup and the plan's copies;
 * - a paid plan change applies the new number to the service's own mailboxes — more copies at once, fewer only when an
 *   operator says so (`onhost:mail:backup-retention --apply --allow-prune`), because fewer copies delete backups;
 * - a drift repair or an add-on resize never touches mailboxes; a customer cannot run the action at all;
 * - a mailbox that is not provably the platform's (another client's group, an address outside the domain) is never written.
 */

beforeEach(fn () => Http::preventStrayRequests());

afterEach(function () {
    unset($_ENV['ISPCONFIG_SHARED01_REMOTE_USER'], $_ENV['ISPCONFIG_SHARED01_REMOTE_PASSWORD']);
});

/**
 * Our ISPConfig mail domain shop.cz (id 5) of client 3 (`onh_…`, group 7). `$boxes` is the panel's mail_user table: a
 * write changes it, so the next read sees what the last one did. `$refuse` lists mailbox ids whose update the panel refuses.
 *
 * @param  list<string>  $calls
 * @param  array<int, array<string,mixed>>  $boxes
 * @param  list<int>  $refuse
 * @param  list<array<string,mixed>>  $updates
 * @param  (Closure(string, array<string,mixed>): mixed)|null  $fault  answers a call itself when it returns a response (a slow queue, a 5xx, a second domain)
 */
function mbrtPanel(array &$calls, array &$boxes, array &$updates = [], array $refuse = [], int $stored = 3, ?Closure $fault = null): void
{
    Http::fake(function (Request $request) use (&$calls, &$boxes, &$updates, $refuse, $stored, $fault) {
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        $body = $request->data();
        $calls[] = $function;
        $primary = $body['primary_id'] ?? null;
        $answered = $fault?->__invoke($function, $body);
        if ($answered !== null) {
            return $answered;
        }
        if ($function === 'mail_user_update' && in_array((int) $primary, $refuse, true)) {
            return Http::response(['code' => 'remote_fault', 'message' => 'backup_copies is invalid', 'response' => false]);
        }
        $answer = match ($function) {
            'login' => 'sess-mbrt',
            'logout' => true,
            'monitor_jobqueue_count' => 0,
            'client_get' => ['client_id' => 3, 'username' => 'onh_mbrt01'],
            'client_get_groupid' => 7,
            'mail_domain_get' => ['domain_id' => 5, 'domain' => 'shop.cz', 'sys_groupid' => 7, 'active' => 'y', 'dkim' => 'y'],
            'mail_user_get' => is_array($primary)
                ? array_values(array_filter($boxes, fn (array $b) => str_ends_with((string) $b['email'], (string) Str::after((string) ($primary['email'] ?? ''), '%'))))
                : ($boxes[(int) $primary] ?? []),
            'mail_user_update' => (function () use (&$boxes, &$updates, $primary, $body) {
                $updates[] = ['id' => (int) $primary, 'params' => (array) ($body['params'] ?? [])];
                $boxes[(int) $primary] = array_merge($boxes[(int) $primary] ?? [], (array) ($body['params'] ?? []));

                return true;
            })(),
            'mail_user_add' => (function () use (&$boxes, &$updates, $body) {
                $updates[] = ['id' => 0, 'add' => (array) ($body['params'] ?? [])];
                $boxes[51] = ['mailuser_id' => 51, 'sys_groupid' => 7] + (array) ($body['params'] ?? []);

                return 51;
            })(),
            'mail_user_backup_list' => array_map(fn (int $i) => ['backup_id' => 700 + $i, 'tstamp' => 1758000000 + $i, 'filesize' => 1024], range(1, $stored)),
            'mailquota_get_by_user' => [],
            default => 101,
        };

        return Http::response(['code' => 'ok', 'message' => '', 'response' => $answer]);
    });
}

/** @return array<int, array<string,mixed>> mailbox 31 is ours; 33 answers the same LIKE query but belongs to another client */
function mbrtBoxes(string $interval = 'none', int $copies = 1): array
{
    return [
        31 => ['mailuser_id' => 31, 'email' => 'info@shop.cz', 'name' => 'Info', 'password' => '$6$hash', 'quota' => 1073741824, 'sys_groupid' => 7, 'backup_interval' => $interval, 'backup_copies' => $copies],
        33 => ['mailuser_id' => 33, 'email' => 'old@shop.cz', 'name' => 'Old', 'password' => '$6$old', 'quota' => 1073741824, 'sys_groupid' => 9, 'backup_interval' => 'none', 'backup_copies' => 1],
    ];
}

/** A Mail Business / Enterprise service on our shop.cz mail domain. */
function mbrtService(Organization $org, int $backupDays = 14, array $tags = []): Service
{
    $service = featureMailService($org);
    $service->forceFill(['entitlements' => array_merge((array) $service->entitlements, ['backup_days' => $backupDays]), 'tags' => $tags])->save();
    // one mail domain row per service (the binding is unique per panel id); the panel double answers every id as shop.cz
    ProviderBinding::query()->where('service_id', $service->id)->update(['remote_id' => (string) (1000 + ProviderBinding::query()->count())]);

    return $service->fresh();
}

function mbrtRule(bool $on): void
{
    app(AutomationLedger::class)->setEnabled(MailboxBackupPolicy::RULE, $on, 'test');
}

/** @param  list<array<string,mixed>>  $updates @return list<array<string,mixed>> */
function mbrtRetentionWrites(array $updates): array
{
    return array_values(array_filter($updates, fn (array $u) => isset($u['params']['backup_copies']) || isset($u['params']['backup_interval'])));
}

it('creates mailboxes exactly as before while the rule is off', function () {
    $calls = [];
    $boxes = mbrtBoxes();
    $updates = [];
    mbrtPanel($calls, $boxes, $updates);
    [$user, $org] = $this->customerWithOrganization();
    $service = mbrtService($org);

    expect(app(AutomationLedger::class)->enabled(MailboxBackupPolicy::RULE))->toBeFalse();
    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'mailbox.create', $this->contextFor($user, $org), 'mbrt-create-off', ['address' => 'nova@shop.cz', 'password' => 'Correct-Horse-Battery-9', 'quota_mb' => 1024]));

    $add = collect($updates)->firstWhere('id', 0)['add'] ?? null;
    expect($operation->state)->not->toBe(Operation::FAILED, (string) data_get($operation->error, 'message', ''))
        ->and($add)->not->toBeNull()->not->toHaveKey('backup_interval')->not->toHaveKey('backup_copies');
});

it('creates a new mailbox with the plan\'s daily copies while the rule is on, whatever the customer sends', function () {
    $calls = [];
    $boxes = mbrtBoxes();
    $updates = [];
    mbrtPanel($calls, $boxes, $updates);
    mbrtRule(true);
    [$user, $org] = $this->customerWithOrganization();
    $service = mbrtService($org, 14);

    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'mailbox.create', $this->contextFor($user, $org), 'mbrt-create-on',
        ['address' => 'nova@shop.cz', 'password' => 'Correct-Horse-Battery-9', 'quota_mb' => 1024, 'backup_copies' => 99, 'backup_interval' => 'hourly']));

    $add = collect($updates)->firstWhere('id', 0)['add'] ?? [];
    expect($operation->state)->not->toBe(Operation::FAILED, (string) data_get($operation->error, 'message', ''))
        ->and($add)->toMatchArray(['email' => 'nova@shop.cz', 'backup_interval' => 'daily', 'backup_copies' => 14]);
});

it('reads backup_days as mailbox copies only for mail plans', function () {
    expect(MailboxBackupPolicy::copiesFor('mail', ['backup_days' => 30]))->toBe(30)
        ->and(MailboxBackupPolicy::copiesFor('mail', ['backup_days' => 0]))->toBeNull()
        ->and(MailboxBackupPolicy::copiesFor('mail', []))->toBeNull()
        ->and(MailboxBackupPolicy::copiesFor('web', ['backup_days' => 30]))->toBeNull() // a web plan's backup_days are its site sets
        ->and(MailboxBackupPolicy::copiesFor('data', ['backup_days' => 14]))->toBeNull();
});

it('applies more copies to the service\'s own mailboxes after a plan change and leaves a stranger\'s alone', function () {
    $calls = [];
    $boxes = mbrtBoxes('daily', 14);
    $updates = [];
    mbrtPanel($calls, $boxes, $updates);
    mbrtRule(true);
    [, $org] = $this->customerWithOrganization();
    $service = mbrtService($org, 14, ['mail_backup' => ['interval' => 'daily', 'copies' => 14]]);

    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'resize', CommandContext::system('test plan change'), 'mbrt-resize-up',
        ['entitlements' => array_merge((array) $service->entitlements, ['backup_days' => 30, 'mailboxes' => 100]), 'apply_mailbox_backup' => true]));

    $service->refresh();
    $writes = mbrtRetentionWrites($updates);
    expect($operation->state)->toBe(Operation::SUCCEEDED, (string) data_get($operation->error, 'message', ''))
        ->and($service->state)->toBe(ServiceStateMachine::ACTIVE)
        ->and($service->entitlements['backup_days'])->toBe(30)
        ->and($writes)->toHaveCount(1)
        ->and($writes[0]['id'])->toBe(31)
        ->and($writes[0]['params'])->toMatchArray(['backup_interval' => 'daily', 'backup_copies' => 30, 'email' => 'info@shop.cz'])->not->toHaveKey('password')
        ->and($boxes[33]['backup_copies'])->toBe(1) // the other client's mailbox is untouched
        ->and($service->tags['mail_backup'])->toMatchArray(['interval' => 'daily', 'copies' => 30, 'set' => 1, 'foreign' => 1, 'held' => 0, 'failed' => []])
        ->and(MailboxBackupPolicy::behind($service))->toBeFalse();
});

it('never touches mailboxes on a resize that is not a plan change, or while the rule is off', function (bool $ruleOn, bool $flag) {
    $calls = [];
    $boxes = mbrtBoxes();
    $updates = [];
    mbrtPanel($calls, $boxes, $updates);
    mbrtRule($ruleOn);
    [, $org] = $this->customerWithOrganization();
    $service = mbrtService($org, 14);

    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'resize', CommandContext::system('test drift repair'), 'mbrt-resize-'.(int) $ruleOn.(int) $flag,
        ['entitlements' => (array) $service->entitlements] + ($flag ? ['apply_mailbox_backup' => true] : [])));

    expect($operation->state)->toBe(Operation::SUCCEEDED, (string) data_get($operation->error, 'message', ''))
        ->and(mbrtRetentionWrites($updates))->toBe([])
        ->and($calls)->not->toContain('mail_user_update')
        ->and($service->fresh()->tags['mail_backup'] ?? null)->toBeNull();
})->with([
    'drift repair, rule on' => [true, false],
    'plan change, rule off' => [false, true],
]);

it('holds a downgrade of copies instead of deleting backups, and says so', function () {
    $calls = [];
    $boxes = mbrtBoxes('daily', 30);
    $updates = [];
    mbrtPanel($calls, $boxes, $updates);
    mbrtRule(true);
    [, $org] = $this->customerWithOrganization();
    $service = mbrtService($org, 30, ['mail_backup' => ['interval' => 'daily', 'copies' => 30]]);

    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'resize', CommandContext::system('test plan change'), 'mbrt-resize-down',
        ['entitlements' => array_merge((array) $service->entitlements, ['backup_days' => 14]), 'apply_mailbox_backup' => true]));

    $service->refresh();
    expect($operation->state)->toBe(Operation::SUCCEEDED, (string) data_get($operation->error, 'message', ''))
        ->and(mbrtRetentionWrites($updates))->toBe([])
        ->and($boxes[31]['backup_copies'])->toBe(30)
        ->and($service->tags['mail_backup'])->toMatchArray(['copies' => 14, 'set' => 0, 'held' => 1])
        ->and(MailboxBackupPolicy::behind($service))->toBeTrue();
});

it('does not fail a paid plan change when the panel refuses one mailbox, and records it', function () {
    $calls = [];
    $boxes = mbrtBoxes() + [32 => ['mailuser_id' => 32, 'email' => 'shop@shop.cz', 'name' => 'Shop', 'password' => '$6$s', 'sys_groupid' => 7, 'backup_interval' => 'none', 'backup_copies' => 1]];
    $updates = [];
    mbrtPanel($calls, $boxes, $updates, refuse: [32]);
    mbrtRule(true);
    [, $org] = $this->customerWithOrganization();
    $service = mbrtService($org, 14);

    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'resize', CommandContext::system('test plan change'), 'mbrt-resize-refused',
        ['entitlements' => array_merge((array) $service->entitlements, ['backup_days' => 30]), 'apply_mailbox_backup' => true]));

    $service->refresh();
    expect($operation->state)->toBe(Operation::SUCCEEDED, (string) data_get($operation->error, 'message', ''))
        ->and($service->state)->toBe(ServiceStateMachine::ACTIVE)
        ->and($boxes[31]['backup_copies'])->toBe(30)
        ->and($service->tags['mail_backup']['set'])->toBe(1)
        ->and(collect($service->tags['mail_backup']['failed'])->pluck('remote_id')->all())->toBe(['32'])
        ->and(MailboxBackupPolicy::behind($service))->toBeTrue();

    $row = collect(app(BackupOperationsCheck::class)->rows())->firstWhere('check', MailboxBackupPolicy::DOCTOR_CHECK);
    expect($row)->not->toBeNull()->and($row['ok'])->toBeFalse()->and($row['blocking'])->toBeFalse()->and($row['detail'])->toContain('1 mail service(s)');
});

it('applies the plan\'s copies after a paid plan change from Mail Business to Mail Enterprise', function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    $calls = [];
    $boxes = mbrtBoxes('daily', 14);
    $updates = [];
    mbrtPanel($calls, $boxes, $updates);
    mbrtRule(true);
    [$owner, $org] = $this->customerWithOrganization([], ['street' => 'Dlouhá 1', 'city' => 'Praha', 'postal_code' => '11000', 'billing_email' => 'billing@example.cz']);
    app(WalletService::class)->topup($org, Money::decimal('9000', 'CZK'), 'card', 'seed', $this->contextFor($owner, $org), bankProvider: 'comgate');
    $catalog = app(CatalogService::class);
    $business = $catalog->resolve('mail', 'mail-business', 'CZK', 'month');
    $service = featureMailService($org);
    $service->forceFill(['product_key' => 'mail', 'plan_version_id' => $business['version']->id, 'entitlements' => $business['version']->entitlements, 'tags' => ['mail_backup' => ['interval' => 'daily', 'copies' => 14]]])->save();
    $subscription = Subscription::query()->create([
        'organization_id' => $org->id, 'service_id' => $service->id, 'plan_version_id' => $business['version']->id, 'price_id' => $business['price']->id, 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => $business['price']->renewalAmount()->minor,
        'state' => Subscription::ACTIVE, 'current_period_start' => now()->subDays(15), 'current_period_end' => now()->addDays(15), 'next_renewal_at' => now()->addDays(8), 'auto_renew' => true, 'renewal_priority' => 'normal',
    ]);
    $service->forceFill(['subscription_id' => $subscription->id])->save();
    $this->actingAs($owner, 'sanctum');

    $this->withHeader('Idempotency-Key', (string) Str::ulid())->putJson('/v1/cart', ['items' => [['product_key' => 'mail', 'plan_key' => 'mail-enterprise', 'qty' => 1, 'period' => 'month', 'config' => ['upgrade_of' => $service->id]]], 'commit_months' => 1, 'currency' => 'CZK'])->assertOk();
    $quote = $this->withHeader('Idempotency-Key', (string) Str::ulid())->postJson('/v1/cart/quote')->assertOk()->json('data');
    $placed = $this->withHeader('Idempotency-Key', (string) Str::ulid())->postJson('/v1/orders', ['quote_id' => $quote['quote_id'], 'consents' => ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => [], 'sla' => []], 'payment' => ['mode' => 'wallet'], 'source' => 'panel'])->assertCreated()->json();
    app(OutboxPublisher::class)->relayPending();
    driveOperations();

    $service->refresh();
    $resize = Operation::query()->where('service_id', $service->id)->where('desired->action', 'resize')->first();
    expect(Order::query()->findOrFail($placed['order_id'])->state)->toBe(OrderStateMachine::ACTIVE)
        ->and(data_get($resize?->desired, 'apply_mailbox_backup'))->toBeTrue()
        ->and($service->entitlements['backup_days'])->toBe(30)
        ->and($boxes[31]['backup_copies'])->toBe(30)
        ->and($boxes[33]['backup_copies'])->toBe(1)
        ->and($service->tags['mail_backup']['copies'])->toBe(30);
});

it('does not let a customer run the retention action', function () {
    $calls = [];
    $boxes = mbrtBoxes();
    mbrtPanel($calls, $boxes);
    mbrtRule(true);
    [$owner, $org] = $this->customerWithOrganization();
    $service = mbrtService($org, 14);
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $this->actingAs($owner, 'sanctum');

    $this->withHeader('Idempotency-Key', (string) Str::ulid())->postJson("/v1/services/{$service->id}/actions", ['action' => 'mailbox.backup_retention', 'params' => ['allow_prune' => true]])
        ->assertForbidden()->assertJsonPath('error', 'access_not_approved'); // the bus refuses first: backup.policy.manage is staff's (TASK-0029); CustomerActionParams stays the second lock
    expect(Operation::query()->where('service_id', $service->id)->exists())->toBeFalse()
        ->and($calls)->not->toContain('mail_user_update');
});

it('counts mail backup_days as kept only while its rule is on, and managed databases only under backups.compute', function () {
    expect(MetricRegistry::isKept('backup_days', 'mail'))->toBeFalse()
        ->and(MetricRegistry::isKept('backup_days', 'data'))->toBeFalse()
        ->and(PlanPromises::KNOWN_GAPS)->toHaveKey('backup_days');

    mbrtRule(true);
    expect(MetricRegistry::isKept('backup_days', 'mail'))->toBeTrue()
        ->and(MetricRegistry::isKept('backup_days', 'data'))->toBeFalse()
        ->and(MetricRegistry::isKept('backup_days', 'game'))->toBeFalse();

    app(AutomationLedger::class)->setEnabled(BackupScheduler::COMPUTE_RULE, true, 'test');
    expect(MetricRegistry::isKept('backup_days', 'data'))->toBeTrue();

    mbrtRule(false);
    expect(MetricRegistry::isKept('backup_days', 'mail'))->toBeFalse();
});

it('tells the doctor whose mail backups are not set while the rule is off, without blocking', function () {
    [, $org] = $this->customerWithOrganization();
    mbrtService($org, 14);

    $row = collect(app(BackupOperationsCheck::class)->rows())->firstWhere('check', MailboxBackupPolicy::DOCTOR_CHECK);

    expect($row)->not->toBeNull()->and($row['ok'])->toBeFalse()->and($row['blocking'])->toBeFalse()
        ->and($row['detail'])->toContain('rule '.MailboxBackupPolicy::RULE.' off')->toContain('1 mail service(s)')->toContain('onhost:mail:backup-retention');
});

it('marks a mail domain provisioned while the rule is on as set from its start', function () {
    [, $org] = $this->customerWithOrganization();
    $fresh = mbrtService($org, 30);

    MailboxBackupPolicy::stampProvisioned($fresh, alreadyExisted: false);
    expect($fresh->fresh()->tags['mail_backup'] ?? null)->toBeNull(); // rule off: nothing is recorded

    mbrtRule(true);
    MailboxBackupPolicy::stampProvisioned($fresh, alreadyExisted: true); // an adopted domain may hold mailboxes made before
    expect($fresh->fresh()->tags['mail_backup'] ?? null)->toBeNull();

    MailboxBackupPolicy::stampProvisioned($fresh, alreadyExisted: false);
    expect($fresh->fresh()->tags['mail_backup'])->toMatchArray(['interval' => 'daily', 'copies' => 30, 'source' => 'provision'])
        ->and(MailboxBackupPolicy::behind($fresh->fresh()))->toBeFalse();
});

/*
 * `onhost:mail:backup-retention` (TASK-0024, owner decision 3): the only way the plan's mailbox backup retention reaches
 * mailboxes that already exist. Without `--apply` it reads the panel and writes nothing — no operation, no tag, no panel
 * write. With `--apply` (and the rule `mail.backup_retention` on) it asks for one `mailbox.backup_retention` operation per
 * service that is behind. A downgrade (30 → 14 copies) deletes backups at the panel's next run, so the dry run says how
 * many and it is applied only with `--allow-prune`.

/** @return array{0:int, 1:string} exit code and output */
function mbrcRun(array $options = []): array
{
    $code = Artisan::call('onhost:mail:backup-retention', $options);

    return [$code, Artisan::output()];
}

it('lists what it would set and changes nothing without --apply', function () {
    $calls = [];
    $boxes = mbrtBoxes();
    $updates = [];
    mbrtPanel($calls, $boxes, $updates);
    mbrtRule(true);
    [, $org] = $this->customerWithOrganization();
    $service = mbrtService($org, 14);

    [$code, $out] = mbrcRun();

    expect($code)->toBe(0)
        ->and($out)->toContain('info@shop.cz')->toContain('would_set')->toContain('none/1')->toContain('daily/14')
        ->toContain('not_ours')->toContain('1 service(s) behind')->toContain('nothing was changed')
        ->and(Operation::query()->where('service_id', $service->id)->exists())->toBeFalse()
        ->and($service->fresh()->tags['mail_backup'] ?? null)->toBeNull()
        ->and($calls)->not->toContain('mail_user_update');
});

it('says how many existing copies a downgrade would delete, and holds it', function () {
    $calls = [];
    $boxes = mbrtBoxes('daily', 30);
    $updates = [];
    mbrtPanel($calls, $boxes, $updates, stored: 20);
    [, $org] = $this->customerWithOrganization();
    mbrtService($org, 14);

    [$code, $out] = mbrcRun();

    expect($code)->toBe(0)
        ->and($out)->toContain('would_prune')->toContain('will prune 6 existing copies')->toContain('--allow-prune')
        ->and($out)->toContain('rule '.MailboxBackupPolicy::RULE.': off')
        ->and($calls)->not->toContain('mail_user_update');
});

it('refuses --apply while the rule is off', function () {
    $calls = [];
    $boxes = mbrtBoxes();
    mbrtPanel($calls, $boxes);
    [, $org] = $this->customerWithOrganization();
    $service = mbrtService($org, 14);

    [$code, $out] = mbrcRun(['--apply' => true]);

    expect($code)->toBe(1)->and($out)->toContain(MailboxBackupPolicy::RULE)
        ->and(Operation::query()->where('service_id', $service->id)->exists())->toBeFalse()
        ->and($calls)->not->toContain('mail_user_update');
});

it('asks for one operation per service that is behind, and none for the rest', function () {
    $calls = [];
    $boxes = mbrtBoxes();
    $updates = [];
    mbrtPanel($calls, $boxes, $updates);
    mbrtRule(true);
    [, $org] = $this->customerWithOrganization();
    [, $otherOrg] = $this->customerWithOrganization();
    [, $thirdOrg] = $this->customerWithOrganization();
    $behind = mbrtService($org, 14);
    $suspended = mbrtService($otherOrg, 14);
    $suspended->forceFill(['state' => ServiceStateMachine::SUSPENDED])->save();
    $web = featureWebService($thirdOrg, 'ispconfig');

    [$code, $out] = mbrcRun(['--apply' => true]);
    driveOperations();

    $ops = Operation::query()->where('desired->action', 'mailbox.backup_retention')->get();
    expect($code)->toBe(0, $out)
        ->and($ops)->toHaveCount(1)
        ->and($ops->first()->service_id)->toBe($behind->id)
        ->and($ops->first()->state)->toBe(Operation::SUCCEEDED, (string) data_get($ops->first()->error, 'message', ''))
        ->and($boxes[31])->toMatchArray(['backup_interval' => 'daily', 'backup_copies' => 14])
        ->and($boxes[33]['backup_copies'])->toBe(1)
        ->and($behind->fresh()->tags['mail_backup'])->toMatchArray(['copies' => 14, 'set' => 1, 'foreign' => 1])
        ->and(Operation::query()->where('service_id', $web->id)->exists())->toBeFalse()
        ->and(Operation::query()->where('service_id', $suspended->id)->exists())->toBeFalse();

    // the second run finds nothing left to do
    [$again, $out2] = mbrcRun(['--apply' => true]);
    expect($again)->toBe(0)->and($out2)->toContain('0 service(s) behind')
        ->and(Operation::query()->where('desired->action', 'mailbox.backup_retention')->count())->toBe(1);
});

it('applies a downgrade only with --allow-prune', function () {
    $calls = [];
    $boxes = mbrtBoxes('daily', 30);
    $updates = [];
    mbrtPanel($calls, $boxes, $updates);
    mbrtRule(true);
    [, $org] = $this->customerWithOrganization();
    $service = mbrtService($org, 14, ['mail_backup' => ['interval' => 'daily', 'copies' => 30]]);

    [$code, $out] = mbrcRun(['--apply' => true]);
    driveOperations();
    expect($code)->toBe(0)->and($out)->toContain('--allow-prune')
        ->and(Operation::query()->where('service_id', $service->id)->exists())->toBeFalse()
        ->and($boxes[31]['backup_copies'])->toBe(30);

    [$code, $out] = mbrcRun(['--apply' => true, '--allow-prune' => true]);
    driveOperations();
    $op = Operation::query()->where('service_id', $service->id)->first();
    expect($code)->toBe(0, $out)
        ->and($op?->state)->toBe(Operation::SUCCEEDED)
        ->and(data_get($op?->desired, 'allow_prune'))->toBeTrue()
        ->and($boxes[31]['backup_copies'])->toBe(14)
        ->and($service->fresh()->tags['mail_backup'])->toMatchArray(['copies' => 14, 'set' => 1, 'held' => 0]);
});

it('limits the run to the services it is given', function () {
    $calls = [];
    $boxes = mbrtBoxes();
    $updates = [];
    mbrtPanel($calls, $boxes, $updates);
    mbrtRule(true);
    [, $org] = $this->customerWithOrganization();
    [, $otherOrg] = $this->customerWithOrganization();
    $first = mbrtService($org, 14);
    $second = mbrtService($otherOrg, 14);

    [$code] = mbrcRun(['--apply' => true, '--service' => [$second->id]]);

    expect($code)->toBe(0)
        ->and(Operation::query()->where('service_id', $second->id)->count())->toBe(1)
        ->and(Operation::query()->where('service_id', $first->id)->exists())->toBeFalse();
});

/*
 * Review round 1 (TASK-0024).
 */

/** @param  list<array<string,mixed>>  $updates @return array<int,int> mailbox id => retention writes it received */
function mbrtWritesPerMailbox(array $updates): array
{
    return array_count_values(array_map(fn (array $u) => (int) $u['id'], mbrtRetentionWrites($updates)));
}

it('asks once when --apply runs again while the first operation is still in flight', function () {
    $calls = [];
    $boxes = mbrtBoxes();
    $updates = [];
    $reads = 0; // the first run's list reads fine; its operation's own read times out, so the operation waits for a retry
    mbrtPanel($calls, $boxes, $updates, fault: function (string $function, array $body) use (&$reads) {
        if ($function !== 'mail_user_get' || ! is_array($body['primary_id'] ?? null)) {
            return null;
        }

        return ++$reads === 2 ? Http::response('Gateway Timeout', 504) : null;
    });
    mbrtRule(true);
    [, $org] = $this->customerWithOrganization();
    $service = mbrtService($org, 14);

    [$code, $out] = mbrcRun(['--apply' => true]);
    $first = Operation::query()->where('service_id', $service->id)->sole();
    expect($code)->toBe(0, $out)->and($first->isTerminal())->toBeFalse()->and($first->state)->not->toBe(Operation::FAILED)
        ->and($boxes[31]['backup_copies'])->toBe(1);

    $this->travel(5)->seconds(); // a new second: a new idempotency key, so only the busy-service guard can stop a second write
    [$again, $out2] = mbrcRun(['--apply' => true]);

    expect($again)->toBe(0)
        ->and($out2)->toContain('Another operation is still running')->toContain('0 operation(s) requested')
        ->and(Operation::query()->where('service_id', $service->id)->count())->toBe(1);

    driveOperations();
    expect($first->fresh()->state)->toBe(Operation::SUCCEEDED, (string) data_get($first->fresh()->error, 'message', ''))
        ->and(mbrtWritesPerMailbox($updates))->toBe([31 => 1]);
});

it('finishes every mail domain of a service after a panel timeout, writing each mailbox once', function (string $failing) {
    $calls = [];
    $boxes = mbrtBoxes() + [41 => ['mailuser_id' => 41, 'email' => 'info@eshop.cz', 'name' => 'Eshop', 'password' => '$6$e', 'sys_groupid' => 7, 'backup_interval' => 'none', 'backup_copies' => 1]];
    $updates = [];
    $timeouts = 1;
    mbrtPanel($calls, $boxes, $updates, fault: function (string $function, array $body) use (&$timeouts, $failing) {
        if ($function === 'mail_domain_get' && (int) ($body['primary_id'] ?? 0) === 2001) {
            return Http::response(['code' => 'ok', 'message' => '', 'response' => ['domain_id' => 2001, 'domain' => 'eshop.cz', 'sys_groupid' => 7, 'active' => 'y']]);
        }
        if ($function === 'mail_user_get' && is_array($body['primary_id'] ?? null) && ($body['primary_id']['email'] ?? '') === '%@'.$failing && $timeouts > 0) {
            $timeouts--;

            return Http::response('Service Unavailable', 503);
        }

        return null;
    });
    mbrtRule(true);
    [, $org] = $this->customerWithOrganization();
    $service = mbrtService($org, 14);
    $first = MailDomains::bindingsOf($service)->sole();
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $first->provider_instance_id, 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "mbrt-second:{$service->id}",
        'adapter_version' => '1.0.0', 'remote_type' => 'mail_domain', 'remote_id' => '2001', 'remote_node' => '1', 'meta' => ['client_id' => 3, 'domain' => 'eshop.cz'], 'created_at' => now()->addSecond()]);

    $operation = driveOperation(app(MailboxBackupRetentionPlan::class)->apply($service, 14, false));

    $tag = $service->fresh()->tags['mail_backup'];
    expect($operation->state)->toBe(Operation::SUCCEEDED, (string) data_get($operation->error, 'message', ''))
        ->and($timeouts)->toBe(0) // the timeout really happened
        ->and($boxes[31])->toMatchArray(['backup_interval' => 'daily', 'backup_copies' => 14])
        ->and($boxes[41])->toMatchArray(['backup_interval' => 'daily', 'backup_copies' => 14])
        ->and($boxes[33]['backup_copies'])->toBe(1)
        ->and(mbrtWritesPerMailbox($updates))->toBe([31 => 1, 41 => 1]) // nothing written twice on the retry
        ->and($tag['failed'])->toBe([])
        ->and($tag['set'] + $tag['unchanged'])->toBe(2)
        ->and(MailboxBackupPolicy::behind($service->fresh()))->toBeFalse();
})->with(['the first domain times out' => 'shop.cz', 'the second domain times out after the first was written' => 'eshop.cz']);

it('prunes every held downgrade of the run with --allow-prune and no --service', function () {
    $calls = [];
    $boxes = mbrtBoxes('daily', 30);
    $updates = [];
    mbrtPanel($calls, $boxes, $updates);
    mbrtRule(true);
    [, $org] = $this->customerWithOrganization();
    [, $otherOrg] = $this->customerWithOrganization();
    $first = mbrtService($org, 14, ['mail_backup' => ['interval' => 'daily', 'copies' => 30]]);
    $second = mbrtService($otherOrg, 14, ['mail_backup' => ['interval' => 'daily', 'copies' => 30]]);

    // all-or-nothing by design: one flag covers every service the run finds behind (docs/runbooks/backups.md says to scope it)
    [$code, $out] = mbrcRun(['--apply' => true, '--allow-prune' => true]);
    driveOperations();

    $ops = Operation::query()->where('desired->action', 'mailbox.backup_retention')->get();
    expect($code)->toBe(0, $out)
        ->and($out)->toContain('2 operation(s) requested')->toContain('--allow-prune without --service: the downgrade of all 2 service(s)')
        ->and($ops->pluck('service_id')->sort()->values()->all())->toBe(collect([$first->id, $second->id])->sort()->values()->all())
        ->and($ops->every(fn (Operation $op) => data_get($op->desired, 'allow_prune') === true))->toBeTrue()
        ->and($boxes[31]['backup_copies'])->toBe(14);
});

it('holds a downgrade whatever interval the mailbox keeps now', function (string $interval) {
    $calls = [];
    $boxes = mbrtBoxes($interval, 60);
    $updates = [];
    mbrtPanel($calls, $boxes, $updates, stored: 45);
    mbrtRule(true);
    [, $org] = $this->customerWithOrganization();
    $service = mbrtService($org, 30, ['mail_backup' => ['interval' => 'daily', 'copies' => 30]]);

    [, $dry] = mbrcRun();
    expect($dry)->toContain('would_prune')->toContain('will prune 15 existing copies')->not->toContain('would_set');

    [$code, $out] = mbrcRun(['--apply' => true]);
    driveOperations();
    expect($code)->toBe(0, $out)
        ->and(Operation::query()->where('service_id', $service->id)->exists())->toBeFalse()
        ->and($calls)->not->toContain('mail_user_update');

    // a paid plan change never prunes either
    $operation = driveOperation(app(ServiceService::class)->requestAction($service->fresh(), 'resize', CommandContext::system('test plan change'), 'mbrt-resize-'.$interval,
        ['entitlements' => (array) $service->entitlements, 'apply_mailbox_backup' => true]));
    expect($operation->state)->toBe(Operation::SUCCEEDED, (string) data_get($operation->error, 'message', ''))
        ->and($calls)->not->toContain('mail_user_update')
        ->and($boxes[31])->toMatchArray(['backup_interval' => $interval, 'backup_copies' => 60])
        ->and($service->fresh()->tags['mail_backup'])->toMatchArray(['held' => 1, 'set' => 0]);
})->with(['weekly', 'monthly', 'none']);

it('keeps what another writer put in the service tags while the retention is recorded', function () {
    [, $org] = $this->customerWithOrganization();
    $service = mbrtService($org, 14, ['backup_schedule' => ['paused' => false]]);
    $stale = Service::query()->findOrFail($service->id); // loaded before the panel round-trips

    Service::query()->findOrFail($service->id)->forceFill(['tags' => ['backup_schedule' => ['paused' => false], 'suspension' => ['cron' => ['7' => true]]]])->save();
    MailboxBackupPolicy::record($stale, ['copies' => 14, 'set' => 1]);

    $tags = $service->fresh()->tags;
    expect($tags['suspension'] ?? null)->toBe(['cron' => ['7' => true]])
        ->and($tags['backup_schedule'])->toBe(['paused' => false])
        ->and($tags['mail_backup'])->toMatchArray(['copies' => 14, 'set' => 1])
        ->and($stale->tags['mail_backup']['copies'] ?? null)->toBe(14);
});
