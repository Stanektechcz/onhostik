<?php

declare(strict_types=1);

use App\Http\Support\CatalogPresentation;
use Database\Seeders\CatalogSeeder;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\PlanPromises;
use Onhost\Domain\Services\Metering\MetricRegistry;

/*
 * Phase U, U4 = metering M4 (docs/proposals/metering-after-launch.md §2): the mail plans sell an antispam tier (`spam_filter`:
 * "rspamd" on Mail Business, "rspamd + policies" on Mail Enterprise) and no mail workflow writes an rspamd policy from it. The
 * owner's choice is open (a per-tier policy in ISPConfig would need staging); the default without that input is the wording: the
 * price list describes the server's antispam as what it is — one rspamd with the server's rules for every plan — and the key is
 * declared fair use (`PlanPromises::FAIR_USE`) with its reason, so it leaves `KNOWN_GAPS` without hiding anything.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class]);
});

it('declares spam_filter fair use with a reason and takes it off the gap ratchet', function () {
    expect(PlanPromises::FAIR_USE)->toHaveKey('spam_filter')
        ->and(PlanPromises::FAIR_USE['spam_filter'])->toContain('rspamd')
        ->and(array_key_exists('spam_filter', PlanPromises::KNOWN_GAPS))->toBeFalse('a fair-use key is not a gap')
        ->and(count(PlanPromises::KNOWN_GAPS))->toBeLessThanOrEqual(2)
        ->and(PlanPromises::actualGaps(PlanPromises::readInSource()))->not->toContain('spam_filter')
        ->and(MetricRegistry::get('spam_filter')['status'] ?? null)->toBe(MetricRegistry::GAP) // nothing measures or enforces it: the row says so
        ->and((string) (MetricRegistry::get('spam_filter')['reason'] ?? ''))->toContain('FAIR_USE');
});

it('words the antispam on the price list as a description of the server, not a per-plan policy', function () {
    $plans = [];
    foreach (['mail-business', 'mail-enterprise'] as $key) {
        $version = Plan::query()->where('key', $key)->firstOrFail()->currentVersion();
        $plans[] = ['name' => $key, 'entitlements' => (array) $version->entitlements];
        expect((string) $version->entitlements['spam_filter'])->toStartWith('rspamd'); // the catalogue value stays (held versions carry it)
    }

    foreach (['cs', 'en'] as $locale) {
        $bullets = array_merge(...array_map(fn (array $p) => CatalogPresentation::bullets($p['entitlements'], 'mail', $locale, 20), $plans));
        $rows = collect(CatalogPresentation::compareRows($plans, 'mail', $locale, 20))->keyBy(0);
        $text = json_encode([$bullets, $rows->all()], JSON_UNESCAPED_UNICODE);

        expect($text)->not->toContain('policies')->not->toContain('politik')
            ->and($rows->get('Antispam'))->toBe(['Antispam', ...array_fill(0, 2, $locale === 'cs' ? 'rspamd, pravidla serveru' : 'rspamd, server rules')]);
    }
    expect(json_encode(CatalogPresentation::bullets($plans[1]['entitlements'], 'mail', 'cs', 20), JSON_UNESCAPED_UNICODE))->toContain('Antispam rspamd s pravidly serveru (fair use)');
});
