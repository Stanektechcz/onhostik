<?php

declare(strict_types=1);

use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\AutomationLedger;

/*
 * TASK-0031 WP A (D31.8): the doctor says whether the VIES check is on and configured, how many EU business customers with
 * a VAT ID still wait for an answer (they are charged destination VAT meanwhile), how old the last VIES answer is, and
 * whether reverse charge lapses because the monthly re-check is off. It never calls VIES itself.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    $this->seed([TaxRuleSeeder::class]);
});

/** @return array<string, array{area:string, check:string, status:string, detail:string}> the tax rows by check title */
function vatDoctorRows(): array
{
    expect(Artisan::call('onhost:doctor', ['--json' => true]))->toBeIn([0, 1]);
    $report = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    return collect($report['checks'])->where('area', 'tax')->keyBy('check')->all();
}

it('prints the four VIES rows and calls nobody', function () {
    config(['onhost.vies.enabled' => false, 'onhost.vies.requester_vat_id' => '']);
    Organization::query()->create(['slug' => 'waiting', 'name' => 'Waiting GmbH', 'owner_user_id' => 'usr_w', 'country' => 'DE', 'vat_id' => 'DE123456789', 'customer_class' => 'b2b']);
    Organization::query()->create(['slug' => 'lapsing', 'name' => 'Lapsing GmbH', 'owner_user_id' => 'usr_l', 'country' => 'AT', 'vat_id' => 'ATU12345678', 'customer_class' => 'b2b', 'vat_status' => 'valid', 'vat_status_source' => 'vies', 'vat_checked_number' => 'ATU12345678', 'vat_checked_at' => now()->subDays(27)]);

    $rows = vatDoctorRows();

    expect($rows)->toHaveCount(4);
    $titles = array_keys($rows);
    expect($rows[$titles[0]]['status'])->toBe('WARN')->and($rows[$titles[0]]['detail'])->toContain('ONHOST_VIES_ENABLED')->toContain('ONHOST_VIES_REQUESTER_VAT_ID')
        ->and($rows[$titles[1]]['status'])->toBe('WARN')->and($rows[$titles[1]]['detail'])->toContain('1')->toContain('onhost:vat:verify')
        ->and($rows[$titles[2]]['status'])->toBe('WARN')
        ->and($rows[$titles[3]]['status'])->toBe('WARN')->and($rows[$titles[3]]['detail'])->toContain('1');
    Http::assertNothingSent();
});

it('shows the rows green when VIES is configured with its monthly re-check, answered today and nobody waits', function () {
    config(['onhost.vies.enabled' => true, 'onhost.vies.requester_vat_id' => 'CZ12345678']);
    app(AutomationLedger::class)->setEnabled('tax.vies_recheck', true, 'test');

    $rows = vatDoctorRows();

    expect(array_column($rows, 'status'))->toBe(['OK', 'OK', 'OK', 'OK']);
    Http::assertNothingSent();
});

/*
 * Review round 1 (billing, MEDIUM): with VIES on and the re-check off, every customer verified at the order loses reverse charge at
 * its first renewal more than 30 days later — the go-live switch and the rule belong together, so the row is not green before
 * any customer has lapsed.
 */
it('warns while VIES is on and the monthly re-check is off, before anybody has lapsed', function () {
    config(['onhost.vies.enabled' => true, 'onhost.vies.requester_vat_id' => 'CZ12345678']);

    $rows = vatDoctorRows();
    $last = $rows[array_keys($rows)[3]];

    expect($last['status'])->toBe('WARN')->and($last['detail'])->toContain('tax.vies_recheck')->toContain('renewal');
    Http::assertNothingSent();
});
