<?php

declare(strict_types=1);

use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Domains\DomainStateMachine;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Incidents\Models\Maintenance;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Money\Money;

/*
 * Integration: the customer's dates — renewals, domain expiries, invoice due dates, maintenance windows touching their
 * services, the day the credit stops covering renewals — as JSON for the panel and as a signed ICS feed any calendar
 * subscribes to. Rotating the feed version revokes every link handed out before.
 */

it('publishes renewals, expiries, due dates, maintenance and the credit horizon as JSON and as a signed ICS feed', function () {
    $this->seed(TaxRuleSeeder::class);
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    Subscription::query()->create(['organization_id' => $org->id, 'service_id' => $service->id, 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => 100000, 'state' => 'active', 'auto_renew' => true, 'current_period_start' => now()->subDays(20), 'current_period_end' => now()->addDays(10), 'next_renewal_at' => now()->addDays(10)]);
    Domain::query()->create(['organization_id' => $org->id, 'fqdn_ascii' => 'shop.cz', 'fqdn_unicode' => 'shop.cz', 'tld' => 'cz', 'state' => DomainStateMachine::ACTIVE, 'expires_at' => now()->addDays(40), 'auto_renew' => false]);
    Invoice::query()->create(['legal_entity' => 'onhost-cz', 'series' => 'F', 'type' => 'invoice', 'organization_id' => $org->id, 'currency' => 'CZK', 'state' => Invoice::ISSUED, 'total_minor' => 121000, 'number' => 'F-2026-0042', 'issued_at' => now(), 'due_at' => now()->addDays(14)]);
    Maintenance::query()->create(['number' => 'MNT-2026-0007', 'title' => 'Výměna disků cz1', 'components' => ['web-cz1'], 'affected_services' => [$service->id], 'starts_at' => now()->addDays(3)->setTime(22, 0), 'ends_at' => now()->addDays(3)->setTime(23, 30), 'impact' => 'Krátké výpadky webů; e-mail běží dál.', 'state' => 'approved']);
    Maintenance::query()->create(['number' => 'MNT-2026-0008', 'title' => 'Jiný zákazník', 'components' => ['web-cz2'], 'affected_services' => ['srv_other'], 'starts_at' => now()->addDays(4), 'ends_at' => now()->addDays(4)->addHour(), 'state' => 'planned']);
    app(WalletService::class)->topup($org, Money::minor(50000, 'CZK'), 'manual', 'calendar-topup', new CommandContext('user', $user->id, $org->id, null, '127.0.0.1', 'test', null, 'test'));

    $this->actingAs($user, 'sanctum');
    $events = $this->getJson('/v1/calendar')->assertOk()->assertJsonPath('total', 5)->json('data');
    expect(array_column($events, 'kind'))->toBe(['maintenance', 'credit_depletion', 'renewal', 'invoice_due', 'domain_expiry'])
        ->and($events[0]['all_day'])->toBeFalse()->and($events[0]['title'])->toBe('Údržba: Výměna disků cz1')->and($events[0]['number'])->toBe('MNT-2026-0007')
        ->and($events[2]['amount']['minor'])->toBe(121000)->and($events[2]['title'])->toBe('Obnova: shop.cz')->and($events[2]['starts_at'])->toStartWith(now()->addDays(10)->toDateString())
        ->and($events[1]['description'])->toContain('710,00 CZK') // 1 210 Kč renewal against 500 Kč credit
        ->and($events[3]['title'])->toBe('Splatnost dokladu F-2026-0042')->and($events[4]['description'])->toContain('vypnutá');
    $this->getJson('/v1/calendar?days=7')->assertOk()->assertJsonPath('total', 1);

    // the feed link is signed; the calendar client needs no session
    $feed = $this->getJson('/v1/calendar/feed')->assertOk()->json('data');
    expect($feed['version'])->toBe(1)->and($feed['url'])->toContain('signature=')->toContain("/calendar/{$org->id}.ics");
    $response = $this->get($feed['url'])->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
    $ics = $response->getContent();
    expect($ics)->toStartWith("BEGIN:VCALENDAR\r\n")->toContain("\r\nEND:VCALENDAR\r\n")
        ->toContain('SUMMARY:Obnova: shop.cz')->toContain('SUMMARY:Expirace domény shop.cz')->toContain('SUMMARY:Splatnost dokladu F-2026-0042')
        ->toContain('SUMMARY:Údržba: Výměna disků cz1')->toContain('DTSTART;VALUE=DATE:'.now()->addDays(10)->format('Ymd'))
        ->toContain('DTSTART:'.now()->addDays(3)->setTime(22, 0)->utc()->format('Ymd\THis\Z'))->toContain('UID:maintenance-')->toContain('CATEGORIES:CREDIT-DEPLETION')
        ->not->toContain('Jiný zákazník');
    foreach (explode("\r\n", $ics) as $line) {
        expect(strlen($line))->toBeLessThanOrEqual(75); // RFC 5545 folding
    }
    expect(substr_count($ics, 'BEGIN:VEVENT'))->toBe(5);
    $this->get($feed['url'].'x')->assertForbidden();

    // rotating the version revokes the old link
    $rotated = $this->postJson('/v1/calendar/feed/rotate')->assertOk()->json('feed');
    expect($rotated['version'])->toBe(2);
    $this->get($feed['url'])->assertNotFound();
    $this->get($rotated['url'])->assertOk();
});
