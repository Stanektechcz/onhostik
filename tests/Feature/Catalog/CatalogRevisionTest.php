<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Catalog\CatalogRevisions;
use Onhost\Domain\Catalog\Commands\CatalogCommand;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Catalog\Models\Price;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\PlanVersioning;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Outbox\OutboxMessage;

/*
 * Owner decisions 2, 4, 5, 6 and 11 (2026-09-25): the plans stop promising what the platform does not provide — point-in-time
 * recovery and a connection count on the managed databases, a dedicated sending IP on Mail Enterprise, a dedicated database
 * on Managed WooCommerce and Shop Peak — and the backup interval "1h" (which the scheduler never knew) becomes "hourly".
 * The catalogue on production changes only through plan versions: the seeder is not deployed, and a version customers hold is
 * never edited. So the change is a code-defined revision (`CatalogRevisions`) that an operator previews and applies with
 * `onhost:catalog:revise`; everybody who bought a version keeps it.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class]);
    Http::preventStrayRequests();
});

function catalogRevisionPlan(string $product, string $plan): Plan
{
    return Plan::query()->where('product_id', Product::query()->where('key', $product)->value('id'))->where('key', $plan)->firstOrFail();
}

/** @return array<string,int> "CZK/month" => amount of the version's active prices */
function catalogRevisionPrices(PlanVersion $version): array
{
    return Price::query()->where('plan_version_id', $version->id)->where('state', 'active')->get()
        ->mapWithKeys(fn (Price $p) => [$p->currency.'/'.$p->period => $p->amount_minor.'/'.($p->renewal_amount_minor ?? $p->amount_minor).'/'.$p->setup_minor])->sort()->all();
}

/** A customer's managed database on the plan version it was sold with, and its subscription. */
function catalogRevisionHolder(Organization $org, PlanVersion $version, string $state = ServiceStateMachine::ACTIVE): Service
{
    $service = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'database', 'family' => 'data', 'name' => 'DB S', 'state' => $state, 'region_code' => 'cz1',
        'plan_version_id' => $version->id, 'entitlements' => (array) $version->entitlements, 'desired_spec' => ['family' => 'data', 'entitlements' => (array) $version->entitlements], 'sla_class' => 'standard', 'tags' => [],
    ]);
    $price = Price::query()->where('plan_version_id', $version->id)->where('currency', 'CZK')->where('period', 'month')->firstOrFail();
    Subscription::query()->create([
        'organization_id' => $org->id, 'service_id' => $service->id, 'plan_version_id' => $version->id, 'price_id' => $price->id, 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => $price->amount_minor,
        'state' => Subscription::ACTIVE, 'current_period_start' => now()->subDays(5), 'current_period_end' => now()->addDays(25), 'next_renewal_at' => now()->addDays(25), 'auto_renew' => true, 'renewal_priority' => 'normal',
    ]);

    return $service;
}

it('previews the revision without publishing anything: the plans, the keys each loses, who keeps the old version, a promo that would end', function () {
    [, $org] = $this->customerWithOrganization();
    $v1 = catalogRevisionPlan('database', 'db-s')->currentVersion();
    catalogRevisionHolder($org, $v1);
    Price::query()->where('plan_version_id', $v1->id)->where('currency', 'CZK')->where('period', 'month')->update(['promo_amount_minor' => 9900, 'promo_periods' => 3]);
    $versions = PlanVersion::query()->count();

    $this->artisan('onhost:catalog:revise')->assertSuccessful()
        ->expectsOutputToContain('2026-09-honest-promises')
        // one expectation per printed line (a line satisfies only the first expectation it matches)
        ->expectsOutputToContain('database/db-s v1 → v2: − entitlements.pitr_days, entitlements.connections')
        ->expectsOutputToContain('database/db-m v1 → v2: − entitlements.pitr_days, entitlements.connections')
        ->expectsOutputToContain('mail/mail-enterprise v1 → v2: − entitlements.dedicated_outbound_ip')
        ->expectsOutputToContain('wordpress/managed-woo v1 → v2: − entitlements.dedicated_db; backup_frequency 1h → hourly')
        ->expectsOutputToContain('eshop/shop-growth v1 → v2: backup_frequency 1h → hourly')
        ->expectsOutputToContain('eshop/shop-peak v1 → v2: − entitlements.dedicated_db')
        ->expectsOutputToContain('1 service(s) and 1 subscription(s) keep v1')
        ->expectsOutputToContain('promo price CZK/month ends for new orders')
        ->expectsOutputToContain('Dry run: nothing was published');

    expect(PlanVersion::query()->count())->toBe($versions)
        ->and(app(CatalogRevisions::class)->pendingKeys())->toEqualCanonicalizing(['pitr_days', 'connections', 'dedicated_outbound_ip', 'dedicated_db']);
});

it('publishes new versions without the promises, with the prices of the old ones, and leaves every customer on the version they bought', function () {
    [, $org] = $this->customerWithOrganization();
    $dbs = catalogRevisionPlan('database', 'db-s');
    $v1 = $dbs->currentVersion();
    $holder = catalogRevisionHolder($org, $v1);
    $before = ['db-s' => $v1->entitlements, 'prices' => catalogRevisionPrices($v1)];
    $untouched = catalogRevisionPlan('web-hosting', 'start')->currentVersion();

    $this->artisan('onhost:catalog:revise', ['--apply' => true, '--yes' => true])->assertSuccessful()
        ->expectsOutputToContain('database/db-s v1 → v2');

    $v2 = $dbs->refresh()->currentVersion();
    expect($v2->version)->toBe(2)
        ->and($v2->entitlements)->toBe(array_diff_key($before['db-s'], ['pitr_days' => 1, 'connections' => 1]))
        ->and($v2->features)->toBe($v1->fresh()->features)
        ->and(catalogRevisionPrices($v2))->toBe($before['prices']) // a revision never changes a price
        // the customer keeps what was sold: the version, its entitlements and the prices renewals read
        ->and($v1->fresh()->entitlements)->toBe($before['db-s'])->and(catalogRevisionPrices($v1->fresh()))->toBe($before['prices'])
        ->and($holder->fresh()->plan_version_id)->toBe($v1->id)->and($holder->fresh()->entitlements)->toHaveKey('pitr_days')
        ->and(Subscription::query()->where('service_id', $holder->id)->sole()->plan_version_id)->toBe($v1->id);

    $m = catalogRevisionPlan('database', 'db-m')->currentVersion();
    $mail = catalogRevisionPlan('mail', 'mail-enterprise')->currentVersion();
    $woo = catalogRevisionPlan('wordpress', 'managed-woo')->currentVersion();
    $growth = catalogRevisionPlan('eshop', 'shop-growth')->currentVersion();
    $peak = catalogRevisionPlan('eshop', 'shop-peak')->currentVersion();
    $previous = fn (PlanVersion $v) => PlanVersion::query()->where('plan_id', $v->plan_id)->where('version', 1)->sole();
    expect($m->version)->toBe(2)->and($m->entitlements)->not->toHaveKeys(['pitr_days', 'connections'])
        ->and($mail->entitlements)->toBe(array_diff_key((array) $previous($mail)->entitlements, ['dedicated_outbound_ip' => 1]))
        ->and($woo->entitlements)->toBe(array_replace(array_diff_key((array) $previous($woo)->entitlements, ['dedicated_db' => 1]), ['backup_frequency' => 'hourly']))
        ->and($growth->entitlements)->toBe(array_replace((array) $previous($growth)->entitlements, ['backup_frequency' => 'hourly']))
        ->and($peak->entitlements)->toBe(array_diff_key((array) $previous($peak)->entitlements, ['dedicated_db' => 1]))
        ->and($peak->entitlements['backup_frequency'])->toBe('15m') // only the value the scheduler never knew is corrected
        ->and(catalogRevisionPrices($peak))->toBe(catalogRevisionPrices($previous($peak)));
    // a plan the revision does not name keeps its version
    expect(catalogRevisionPlan('web-hosting', 'start')->currentVersion()->id)->toBe($untouched->id);
});

it('goes through the command bus: one audited publish and one finance event per plan, and the database description stops promising PITR', function () {
    // the description a running installation has (a fresh seed already writes the new one)
    Product::query()->where('key', 'database')->update(['description' => json_encode(['cs' => 'PostgreSQL, MariaDB a Redis jako služba — single-tenant KVM, zálohy a PITR.', 'en' => 'PostgreSQL, MariaDB and Redis as a service — single-tenant KVM, backups and PITR.'], JSON_UNESCAPED_UNICODE)]);
    expect(app(CatalogRevisions::class)->pending()['2026-09-honest-promises']['products'])->toHaveKey('database');
    Artisan::call('onhost:catalog:revise', ['--apply' => true, '--yes' => true]);

    expect(AuditEvent::query()->where('action', 'catalog.plan.publish')->where('result', 'succeeded')->where('actor_type', 'system')->where('resource_type', 'plan')->count())->toBe(6)
        ->and(OutboxMessage::query()->where('name', 'catalog.plan.version_published')->count())->toBe(6)
        ->and(AuditEvent::query()->where('action', 'catalog.product.describe')->where('result', 'succeeded')->count())->toBe(1);
    $description = Product::query()->where('key', 'database')->firstOrFail()->description;
    expect(json_encode($description, JSON_UNESCAPED_UNICODE))->not->toContain('PITR')->toContain('zálohy');
    $reasons = collect(OutboxMessage::query()->where('name', 'catalog.plan.version_published')->get())->pluck('payload.reason')->unique()->all();
    expect($reasons)->toHaveCount(1)->and($reasons[0])->toContain('PITR');
});

it('is idempotent and stateless: a second run finds nothing, a key staff already removed is skipped, a rollback makes it pending again', function () {
    $owner = $this->staff('platform_owner');
    $context = $this->contextFor($owner, null, 'totp');
    // staff already published db-m without PITR and Mail Enterprise without the dedicated IP, by hand
    app(PlanVersioning::class)->publish('database', 'db-m', ['entitlements' => ['pitr_days' => null], 'reason' => 'PITR neposkytujeme'], $context);
    app(PlanVersioning::class)->publish('mail', 'mail-enterprise', ['entitlements' => ['dedicated_outbound_ip' => null], 'reason' => 'IP neposkytujeme'], $context);

    $this->artisan('onhost:catalog:revise', ['--apply' => true, '--yes' => true])->assertSuccessful()
        ->expectsOutputToContain('database/db-m v2 → v3');
    expect(catalogRevisionPlan('database', 'db-m')->currentVersion())->version->toBe(3)
        ->and(catalogRevisionPlan('database', 'db-m')->currentVersion()->entitlements)->not->toHaveKeys(['pitr_days', 'connections'])
        ->and(catalogRevisionPlan('mail', 'mail-enterprise')->current_version)->toBe(2); // nothing left to drop: not refused, skipped

    $versions = PlanVersion::query()->count();
    $this->artisan('onhost:catalog:revise', ['--apply' => true, '--yes' => true])->assertSuccessful()->expectsOutputToContain('Nothing pending');
    expect(PlanVersion::query()->count())->toBe($versions)->and(app(CatalogRevisions::class)->pending())->toBe([]);

    // a rollback to the version with PITR (staff's decision) makes the revision pending again; applying it publishes anew
    app(PlanVersioning::class)->activate('database', 'db-s', 1, ['reason' => 'návrat na v1'], $context);
    expect(app(CatalogRevisions::class)->pendingKeys())->toEqualCanonicalizing(['pitr_days', 'connections']);
    $this->artisan('onhost:catalog:revise', ['--apply' => true, '--yes' => true])->assertSuccessful()->expectsOutputToContain('database/db-s v1 → v3');
    expect(catalogRevisionPlan('database', 'db-s')->current_version)->toBe(3);
});

it('asks before it publishes, and a no publishes nothing', function () {
    $versions = PlanVersion::query()->count();

    $this->artisan('onhost:catalog:revise', ['--apply' => true])
        ->expectsConfirmation('Publish these new plan versions now?', 'no')
        ->assertFailed();

    expect(PlanVersion::query()->count())->toBe($versions);
});

it('refuses a revision it does not know', function () {
    $this->artisan('onhost:catalog:revise', ['revision' => 'nope'])->assertFailed()->expectsOutputToContain('Unknown catalogue revision');
});

it('describes a product through the bus as a step-up change bound to the text it replaces', function () {
    $bus = app(CommandBus::class);
    $command = fn (array $extra = []) => new CatalogCommand('describe-'.uniqid(), array_replace(['op' => 'product.describe', 'product_key' => 'database', 'description' => ['cs' => 'Nový popis databáze.', 'en' => 'A new database description.']], $extra));

    expect(fn () => $bus->dispatch($command(['description' => ['cs' => '', 'en' => 'x']]), CommandContext::system('test')))->toThrow(DomainError::class, 'Czech');
    expect(fn () => $bus->dispatch($command(['base' => 'stale']), CommandContext::system('test')))->toThrow(DomainError::class, 'changed since');

    $bus->dispatch($command(), CommandContext::system('test'));
    expect(Product::query()->where('key', 'database')->firstOrFail()->description)->toBe(['cs' => 'Nový popis databáze.', 'en' => 'A new database description.']);
});

/*
 * The seeder is not part of a deployment, but a re-run of the installer seeds the whole database: it used to rewrite version 1
 * of every plan — the version customers hold — back to whatever the seeder file says, prices included.
 */
it('does not rewrite a plan version somebody holds when the catalogue is seeded again', function () {
    [, $org] = $this->customerWithOrganization();
    $held = catalogRevisionPlan('database', 'db-s')->currentVersion();
    $free = catalogRevisionPlan('database', 'db-m')->currentVersion();
    catalogRevisionHolder($org, $held);
    // what was sold differs from today's seeder file
    $held->forceFill(['entitlements' => array_replace((array) $held->entitlements, ['connections' => 150])])->save();
    Price::query()->where('plan_version_id', $held->id)->where('currency', 'CZK')->where('period', 'month')->update(['amount_minor' => 17900]);
    $free->forceFill(['entitlements' => array_replace((array) $free->entitlements, ['connections' => 999])])->save();

    $this->seed([CatalogSeeder::class]);

    expect($held->fresh()->entitlements['connections'])->toBe(150)
        ->and(Price::query()->where('plan_version_id', $held->id)->where('currency', 'CZK')->where('period', 'month')->sole()->amount_minor)->toBe(17900)
        ->and($free->fresh()->entitlements['connections'])->toBe(300); // a version nobody holds still follows the seeder
});
