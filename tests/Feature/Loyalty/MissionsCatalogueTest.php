<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Loyalty\LoyaltyService;
use Onhost\Domain\Loyalty\MissionService;
use Onhost\Domain\Loyalty\Models\LoyaltyBadge;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\OperationService;
use Onhost\Domain\Provisioning\Workflows\GameMigrationWorkflow;
use Onhost\Platform\Commands\CommandContext;

/*
 * The missions catalogue in settings (audit §5k-5): staff replace the five built-in missions with their own table —
 * titles, points, the check behind each, a season — and seasonal missions count only inside their season.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('lets staff edit the catalogue with seasonal missions and evaluates the new checks', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureGameService($org);
    $missions = app(MissionService::class);
    expect($missions->catalogue())->toHaveCount(5);

    $staff = $this->staff('platform_owner');
    $this->actingAs($staff, 'sanctum');
    $initial = $this->getJson('/v1/staff/loyalty/missions')->assertOk()->json('data');
    expect($initial['custom'])->toBeFalse()->and($initial['checks'])->toContain('backup_done')->and($initial['defaults'])->toHaveCount(5);
    $this->putJson('/v1/staff/loyalty/missions', ['missions' => [['key' => 'x', 'check' => 'nonsense']]])->assertStatus(422);
    $this->putJson('/v1/staff/loyalty/missions', ['missions' => [['key' => 'dup', 'check' => 'profile'], ['key' => 'dup', 'check' => 'profile']]])->assertStatus(422);
    $this->putJson('/v1/staff/loyalty/missions', ['missions' => [['key' => 'season', 'check' => 'profile', 'active_from' => '2026-12-01', 'active_to' => '2026-11-01']]])->assertStatus(422);
    $table = $this->putJson('/v1/staff/loyalty/missions', ['missions' => [
        ['key' => 'backup', 'cs' => 'Záloha tento měsíc', 'en' => 'A backup this month', 'points' => 25, 'check' => 'backup_done', 'hint_cs' => 'Spusťte zálohu.'],
        ['key' => 'two-services', 'cs' => 'Dvě běžící služby', 'points' => 15, 'check' => 'services_min', 'params' => ['min' => 2]],
        ['key' => 'quiet', 'cs' => 'Měsíc bez tiketu', 'points' => 5, 'check' => 'ticket_free', 'badge' => 'zen'],
        ['key' => 'summer', 'cs' => 'Letní mise', 'points' => 100, 'check' => 'profile', 'active_from' => '2000-06-01', 'active_to' => '2000-08-31'], // long over
    ], 'reason' => 'sezónní katalog'])->assertOk()->json('missions');
    expect($table)->toHaveCount(4)->and(collect($table)->firstWhere('key', 'summer')['in_season'])->toBeFalse()->and(collect($table)->firstWhere('key', 'two-services')['params'])->toBe(['min' => 2]);
    expect($this->getJson('/v1/staff/loyalty/missions')->assertOk()->json('data.custom'))->toBeTrue();

    // the customer sees three missions (the summer one is out of season); backup and quiet are done, two services are not
    app(OperationService::class)->start(GameMigrationWorkflow::class, 'bk-1', ['action' => 'backup'], CommandContext::system('test'), $service->id, $org->id, null, null, null, false)
        ->forceFill(['kind' => 'service.backup', 'state' => Operation::SUCCEEDED, 'finished_at' => now()])->save();
    $summary = $missions->summary($org->refresh(), CarbonImmutable::now(), 'cs');
    expect($summary['total'])->toBe(3)->and(collect($summary['missions'])->pluck('key')->all())->toBe(['backup', 'two-services', 'quiet'])
        ->and(collect($summary['missions'])->firstWhere('key', 'backup')['done'])->toBeTrue()->and(collect($summary['missions'])->firstWhere('key', 'two-services'))->toMatchArray(['done' => false, 'progress' => '1/2'])
        ->and(collect($summary['missions'])->firstWhere('key', 'quiet')['done'])->toBeTrue()->and(collect($summary['missions'])->firstWhere('key', 'backup')['title'])->toBe('Záloha tento měsíc');
    $result = $missions->evaluate($org);
    expect($result['awarded'])->toEqualCanonicalizing(['backup', 'quiet'])->and($result['badge'])->toBeFalse();
    expect(app(LoyaltyService::class)->points($org->id))->toBe(30)->and(LoyaltyBadge::query()->where('organization_id', $org->id)->where('badge', 'mission:zen')->exists())->toBeTrue();

    // a second running service completes the month; an empty table restores the defaults
    featureGameService($org, [], 78, 'e4c1abce');
    $full = $missions->evaluate($org->refresh());
    expect($full['awarded'])->toBe(['two-services'])->and($full['badge'])->toBeTrue();
    $this->putJson('/v1/staff/loyalty/missions', ['missions' => []])->assertOk();
    expect($missions->catalogue())->toHaveCount(5)->and($this->getJson('/v1/staff/loyalty/missions')->assertOk()->json('data.custom'))->toBeFalse();
    $this->actingAs($owner, 'sanctum')->putJson('/v1/staff/loyalty/missions', ['missions' => []])->assertForbidden();
});
