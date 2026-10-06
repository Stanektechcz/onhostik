<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Invoicing\Models\LegalEntity;
use Onhost\Domain\Loyalty\Models\LoyaltyPoint;
use Onhost\Domain\Notifications\Models\WebhookEndpoint;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Platform\GoLiveChecks;
use Onhost\Domain\Services\CustomIso\ClamdIsoScanner;
use Onhost\Domain\Services\CustomIso\IsoScanner;
use Onhost\Platform\Files\VirusScanner;

/*
 * G10 (TASK-0119): the go-live doctor rows of phase G — the VAT payer mode and its legal entity, the virus scan and the storage of
 * customers' own ISO images, the loyalty programme (expiry scheduler, debts), and the overlap of rotated webhook secrets.
 * Each row names its remedy; none calls out (the scanner self-test is faked) and none prints a secret.
 */

beforeEach(fn () => Http::preventStrayRequests());

function g10Row(string $check, ?GoLiveChecks $checks = null): array
{
    return (array) collect(($checks ?? new GoLiveChecks)->rows())->firstWhere('check', $check);
}

function g10Scanner(bool $ok, string $detail = 'EICAR found'): void
{
    app()->instance(IsoScanner::class, new class($ok, $detail) implements IsoScanner
    {
        public function __construct(private readonly bool $ok, private readonly string $detail) {}

        public function scan($stream): array
        {
            return ['result' => self::UNAVAILABLE, 'signature' => null];
        }

        public function selfTest(): array
        {
            return ['ok' => $this->ok, 'detail' => $this->detail];
        }
    });
}

it('says the VAT mode and fails a payer whose legal entity has no real VAT number', function () {
    $this->seed([LegalEntitySeeder::class]);
    $name = 'VAT payer mode agrees with the legal entity';
    config(['vat.payer' => true]);

    $placeholder = g10Row($name);
    expect($placeholder['ok'])->toBeFalse()->and($placeholder['detail'])->toContain('payer')
        ->and($placeholder['remedy'])->toContain('onhost:production:prepare')->and($placeholder['blocking'])->toBeTrue();

    LegalEntity::query()->update(['dic' => 'CZ12345678', 'vat_id' => 'CZ12345678']);
    $real = g10Row($name);
    expect($real['ok'])->toBeTrue()->and($real['remedy'])->toBe('')->and($real['detail'])->toContain('payer');
});

it('names the way out when the declared VAT mode is not the legal entity mode', function () {
    $this->seed([LegalEntitySeeder::class]);
    LegalEntity::query()->update(['dic' => 'CZ12345678', 'vat_id' => 'CZ12345678']);
    config(['vat.payer' => false]);

    $row = g10Row('VAT payer mode agrees with the legal entity');

    expect($row['ok'])->toBeFalse()->and($row['remedy'])->toContain('vat-payer-mode')->and($row['remedy'])->toContain('second person');
});

it('accepts a non-payer without a VAT number', function () {
    $this->seed([LegalEntitySeeder::class]);
    LegalEntity::query()->update(['vat_payer' => false, 'dic' => '', 'vat_id' => '']);
    config(['vat.payer' => false]);

    $row = g10Row('VAT payer mode agrees with the legal entity');

    expect($row['ok'])->toBeTrue()->and($row['detail'])->toContain('not a VAT payer');
});

it('asks nothing of the virus scan while no plan sells custom ISO and nobody has an image', function () {
    g10Scanner(false, 'no clamd is configured');
    $row = g10Row('custom ISO virus scan passes its self-test');

    expect($row['ok'])->toBeTrue()->and($row['blocking'])->toBeFalse()->and($row['detail'])->toContain('no plan sells');
});

it('fails the virus scan row once a plan sells custom ISO and the self-test does not pass', function () {
    g10Scanner(false, 'clamd passes a file it could not read in full');
    $checks = new GoLiveChecks(customIsoSold: true);
    $row = g10Row('custom ISO virus scan passes its self-test', $checks);

    expect($row['ok'])->toBeFalse()->and($row['blocking'])->toBeTrue()->and($row['detail'])->toContain('clamd')
        ->and($row['remedy'])->toContain('onhost:isos:scanner-check')->toContain('AlertExceedsMax yes');

    g10Scanner(true);
    expect(g10Row('custom ISO virus scan passes its self-test', $checks)['ok'])->toBeTrue();
});

it('detects that a plan sells custom ISO from the catalogue itself', function () {
    $this->seed([LegalEntitySeeder::class, CatalogSeeder::class]);
    g10Scanner(false, 'no clamd is configured');
    expect(g10Row('custom ISO virus scan passes its self-test')['ok'])->toBeTrue();

    expect(Artisan::call('onhost:catalog:revise', ['revision' => '2026-10-custom-iso', '--apply' => true, '--yes' => true]))->toBe(0);

    $row = g10Row('custom ISO virus scan passes its self-test');
    expect($row['ok'])->toBeFalse()->and($row['detail'])->toContain('no clamd is configured');
});

it('warns when the custom ISO disk has less room than one organization may fill', function () {
    config(['onhost.custom_iso.org_quota_mb' => 20480]);
    $name = 'custom ISO storage has room';
    $gib = 1024 ** 3;

    $tight = g10Row($name, new GoLiveChecks(customIsoSold: true, customIsoFreeBytes: 5 * $gib));
    expect($tight['ok'])->toBeFalse()->and($tight['detail'])->toContain('5.0 GiB free')->and($tight['remedy'])->toContain('ONHOST_CUSTOM_ISO_ROOT')
        ->and($tight['blocking'])->toBeFalse();

    expect(g10Row($name, new GoLiveChecks(customIsoSold: true, customIsoFreeBytes: 200 * $gib))['ok'])->toBeTrue();
    expect(g10Row($name, new GoLiveChecks(customIsoSold: false, customIsoFreeBytes: 1))['ok'])->toBeTrue();
});

it('fails the storage row when the custom ISO root is inside the web root', function () {
    config(['filesystems.disks.custom_isos.root' => public_path('isos')]);

    $row = g10Row('custom ISO storage has room', new GoLiveChecks(customIsoSold: true, customIsoFreeBytes: 500 * 1024 ** 3));

    expect($row['ok'])->toBeFalse()->and($row['blocking'])->toBeTrue()->and($row['detail'])->toContain('web root');
});

it('checks the loyalty expiry is scheduled and no balance or debt is negative', function () {
    $this->seed([LegalEntitySeeder::class]);
    $organization = Organization::query()->create(['slug' => 'loyal', 'name' => 'Loyal s.r.o.', 'owner_user_id' => 'usr_l', 'country' => 'CZ']);
    $name = 'loyalty expiry runs and balances are sane';

    $ok = g10Row($name);
    expect($ok['ok'])->toBeTrue()->and($ok['detail'])->toContain('scheduled');

    LoyaltyPoint::query()->create(['organization_id' => $organization->id, 'rule' => 'welcome', 'reference' => 'r1', 'points' => 100]);
    LoyaltyPoint::query()->create(['organization_id' => $organization->id, 'rule' => 'clawback.debt', 'reference' => 'd1', 'points' => -150]);
    $bad = g10Row($name);
    expect($bad['ok'])->toBeFalse()->and($bad['detail'])->toContain('1 organization')->and($bad['remedy'])->toContain('loyalty');
});

it('fails the loyalty row when points past their 24 months are still on the balance', function () {
    $this->seed([LegalEntitySeeder::class]);
    config(['loyalty.expiry.counted_from' => '2024-01-01']);
    $organization = Organization::query()->create(['slug' => 'old', 'name' => 'Old s.r.o.', 'owner_user_id' => 'usr_o', 'country' => 'CZ']);
    $point = LoyaltyPoint::query()->create(['organization_id' => $organization->id, 'rule' => 'welcome', 'reference' => 'r1', 'points' => 100]);
    $point->forceFill(['created_at' => now()->subMonths(30)])->save();

    $row = g10Row('loyalty expiry runs and balances are sane');
    expect($row['ok'])->toBeFalse()->and($row['detail'])->toContain('past their')->and($row['remedy'])->toContain('onhost:loyalty:expire');

    Artisan::call('onhost:loyalty:expire');
    expect(g10Row('loyalty expiry runs and balances are sane')['ok'])->toBeTrue();
});

it('counts the webhook secrets still signing and flags an overlap longer than the limit, never printing one', function () {
    $name = 'rotated webhook secrets overlap only briefly';
    expect(g10Row($name)['ok'])->toBeTrue()->and(g10Row($name)['detail'])->toContain('0 ');

    $live = new WebhookEndpoint;
    $live->forceFill(['organization_id' => 'org_x', 'url' => 'https://example.test/h', 'secret' => 'whsec_current_fixture', 'previous_secret' => 'whsec_previous_fixture',
        'previous_secret_expires_at' => now()->addMinutes(30), 'events' => ['*'], 'state' => 'active'])->save();
    $row = g10Row($name);
    expect($row['ok'])->toBeTrue()->and($row['detail'])->toContain('1 ')->and(json_encode($row))->not->toContain('whsec_');

    $live->forceFill(['previous_secret_expires_at' => now()->addDays(30)])->save();
    $long = g10Row($name);
    expect($long['ok'])->toBeFalse()->and($long['remedy'])->toContain('ONHOST_WEBHOOK_SECRET_OVERLAP_MINUTES')->and(json_encode($long))->not->toContain('whsec_');
});

it('keeps the new rows in the doctor report', function () {
    Artisan::call('onhost:doctor', ['--json' => true]);
    $report = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);
    $titles = collect($report['checks'])->pluck('check');

    expect($titles)->toContain('VAT payer mode agrees with the legal entity')->toContain('custom ISO virus scan passes its self-test')
        ->toContain('custom ISO storage has room')->toContain('loyalty expiry runs and balances are sane')->toContain('rotated webhook secrets overlap only briefly');
});

it('flags a webhook secret that is still stored after its overlap ended', function () {
    $endpoint = new WebhookEndpoint;
    $endpoint->forceFill(['organization_id' => 'org_y', 'url' => 'https://example.test/h2', 'secret' => 'whsec_current_fixture', 'previous_secret' => 'whsec_previous_fixture',
        'previous_secret_expires_at' => now()->subHour(), 'events' => ['*'], 'state' => 'active'])->save();

    $row = g10Row('rotated webhook secrets overlap only briefly');

    expect($row['ok'])->toBeFalse()->and($row['detail'])->toContain('still store a secret')->and($row['remedy'])->toContain('onhost-queue@webhooks');
});

it('still sees overdue loyalty expiry of an organization that sorts after hundreds of others', function () {
    $this->seed([LegalEntitySeeder::class]);
    config(['loyalty.expiry.counted_from' => '2024-01-01']);
    for ($i = 0; $i < 230; $i++) {
        $young = Organization::query()->create(['slug' => 'young-'.$i, 'name' => 'Young '.$i, 'owner_user_id' => 'usr_y'.$i, 'country' => 'CZ']);
        LoyaltyPoint::query()->create(['organization_id' => $young->id, 'rule' => 'welcome', 'reference' => 'r', 'points' => 10])->forceFill(['created_at' => now()->subMonths(30)])->save();
        LoyaltyPoint::query()->create(['organization_id' => $young->id, 'rule' => 'expiry', 'reference' => 'e', 'points' => -10]); // already expired: nothing due
    }
    $old = Organization::query()->create(['slug' => 'late-old', 'name' => 'Late old', 'owner_user_id' => 'usr_late', 'country' => 'CZ']);
    LoyaltyPoint::query()->create(['organization_id' => $old->id, 'rule' => 'welcome', 'reference' => 'r', 'points' => 100])->forceFill(['created_at' => now()->subMonths(30)])->save();

    $row = g10Row('loyalty expiry runs and balances are sane');

    expect($row['ok'])->toBeFalse()->and($row['detail'])->toContain('1 organization(s) hold 100 point(s) past their');
});

it('gives the scanner self-test its own short timeout and keeps the long one for images', function () {
    config(['onhost.custom_iso.scan_timeout_seconds' => 900]);
    $seen = [];
    $replies = ['stream: Eicar-Test-Signature FOUND', 'stream: Heuristics.Limits.Exceeded FOUND'];
    $scanner = new ClamdIsoScanner(app(VirusScanner::class), function (string $command, $stream, ?int $timeout = null) use (&$seen, &$replies) {
        $seen[] = $timeout;

        return array_shift($replies) ?? 'stream: OK';
    });
    Cache::forget(ClamdIsoScanner::SELF_TEST_KEY);

    expect($scanner->selfTest()['ok'])->toBeTrue()->and($seen)->toBe([15, 15]);

    $scanner->scan(fopen('php://memory', 'r+'));
    expect($seen[2])->toBe(900);
});

it('forgets the cached scanner self-test with --fresh', function () {
    $calls = 0;
    $replies = ['stream: Eicar-Test-Signature FOUND', 'stream: Heuristics.Limits.Exceeded FOUND'];
    app()->instance(IsoScanner::class, new ClamdIsoScanner(app(VirusScanner::class), function () use (&$calls, &$replies) {
        $calls++;
        $reply = array_shift($replies);
        if ($reply === null) {
            $replies = ['stream: Eicar-Test-Signature FOUND', 'stream: Heuristics.Limits.Exceeded FOUND'];
            $reply = array_shift($replies);
        }

        return $reply;
    }));
    Cache::forget(ClamdIsoScanner::SELF_TEST_KEY);

    expect(Artisan::call('onhost:isos:scanner-check'))->toBe(0)->and($calls)->toBe(2);
    expect(Artisan::call('onhost:isos:scanner-check'))->toBe(0)->and($calls)->toBe(2); // cached
    expect(Artisan::call('onhost:isos:scanner-check', ['--fresh' => true]))->toBe(0)->and($calls)->toBe(4);
});

it('names only staff routes in the first-day runbook that exist', function () {
    $text = (string) file_get_contents(base_path('docs/runbooks/first-day-production.md'));
    preg_match_all('/\b(GET|POST) (\/v1\/staff\/[A-Za-z0-9_\/{}\-]+)/', $text, $m, PREG_SET_ORDER);
    expect($m)->not->toBeEmpty();
    $routes = collect(app('router')->getRoutes()->getRoutes())->map(fn ($r) => [implode('|', $r->methods()), preg_replace('/\{[^}]+\??\}/', '{}', '/'.ltrim($r->uri(), '/'))]);
    foreach ($m as [$whole, $verb, $path]) {
        $normal = preg_replace('/\{[^}]+\}/', '{}', rtrim($path, '/'));
        $found = $routes->contains(fn ($r) => str_contains($r[0], $verb) && str_ends_with($r[1], $normal));
        expect($found)->toBeTrue("{$verb} {$path} is not a route");
    }
});
