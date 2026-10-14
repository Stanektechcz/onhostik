<?php

declare(strict_types=1);

use Onhost\Domain\Partners\Models\PartnerCommission;
use Onhost\Domain\Partners\PartnerService;
use Onhost\Platform\Commands\CommandContext;

/*
 * G6: a payout splits the last commission it takes so it matches the requested amount (PartnerPayouts::allocate copies the row:
 * a fragment of the same commission, the same invoice, the same base). The tier counted the base of every row, so the base of a
 * split commission was counted twice — a partner reached a higher tier, and a higher rate, on volume nobody paid. The monthly
 * commission table added the base twice in the same way. A fragment's base is its commission's: counted once.
 */

it('counts the base of a commission a payout split in two once, for the tier and for the monthly table', function () {
    [, $partnerOrg] = $this->customerWithOrganization();
    [, $client] = $this->customerWithOrganization();
    $partners = app(PartnerService::class);
    $partner = $partners->approve($partners->apply($partnerOrg, ['model' => 'share'], CommandContext::system('test')), CommandContext::system('test'));

    $rows = [];
    foreach ([1, 2, 3] as $m) { // 2 000 000 a month paid: bronze (silver starts at 2 500 000)
        $rows[$m] = PartnerCommission::query()->create(['partner_id' => $partner->id, 'organization_id' => $client->id, 'invoice_id' => "g6-hist-{$m}", 'period' => now()->subMonths($m)->format('Y-m'), 'kind' => 'share',
            'base_minor' => 2000000, 'rate_pct' => 15, 'amount_minor' => 300000, 'currency' => 'CZK', 'state' => 'payable', 'invoice_paid_at' => now()->subMonths($m)->startOfMonth()->addDays(5)]);
    }
    // a payout took 100 000 of the first commission: the rest is a fragment with the same invoice and base (as allocate() does it)
    $rest = $rows[1]->replicate(['id']);
    $rest->forceFill(['amount_minor' => 200000])->save();
    $rows[1]->forceFill(['amount_minor' => 100000, 'state' => 'allocated'])->save();
    expect($rest->fresh()->fragment)->toBeTrue();

    $partner = $partners->recomputeTier($partner);
    expect($partner->volume_3m_minor)->toBe(2000000)->and($partner->tier)->toBe('bronze')->and($partner->rate_pct)->toBe(15);

    $month = collect($partners->commissionMonths($partner))->firstWhere('period', now()->subMonth()->format('Y-m'));
    expect($month['base']->minor)->toBe(2000000)->and($month['amount']->minor)->toBe(300000);
});
