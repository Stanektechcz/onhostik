<?php

declare(strict_types=1);

use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RoleCatalog;
use Onhost\Domain\Identity\Authorization\TokenScopes;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Partners\Commands\PartnerPortalCommand;
use Onhost\Domain\Partners\Models\Partner;
use Onhost\Domain\Partners\Models\PartnerCommission;
use Onhost\Domain\Partners\Models\PartnerPayout;
use Onhost\Domain\Partners\Models\PartnerPayoutAccount;
use Onhost\Domain\Partners\PartnerService;
use Onhost\Domain\Partners\PayoutAccounts;
use Onhost\Domain\WalletLedger\Models\LedgerTransaction;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * TASK-0040 (permission program P0-13, IF-14; audit P1, P2, P5, TD-4): a partner payout is paid once, to the right account.
 *
 *  - the balance used to be checked outside the transaction that allocated it, so two requests racing for one balance both
 *    passed the check and the second payout claimed money nothing was allocated to (P1);
 *  - the IBAN was a field of the payout request and overwrote the partner's account with no step-up (P2);
 *  - a payout could be paid straight from `requested`, by whoever approved it, with one step-up (P8, TD-4);
 *  - every member with `organization.read` read the partner portal, client e-mails and who is overdue included (P5).
 */

beforeEach(function () {
    $this->seed([TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
    config(['onhost.vies.enabled' => false]);
});

/** Valid IBANs (ISO 13616 check digits) for the tests: A is the account a paid payout already went to. */
function pstIban(string $which): string
{
    return ['A' => 'CZ6508000000192000145399', 'B' => 'CZ6203000000000123456789', 'C' => 'CZ6701000000000987654321'][$which];
}

function pstPartnerOf(Organization $organization, string $terms = 'on_request'): Partner
{
    $partners = app(PartnerService::class);
    $partner = $partners->approve($partners->apply($organization, ['model' => 'share'], CommandContext::system('test')), CommandContext::system('test'));
    $partner->forceFill(['payout_terms' => $terms])->save();

    return $partner->fresh();
}

function pstEarn(Partner $partner, int $minor, ?string $payoutId = null): PartnerCommission
{
    return PartnerCommission::query()->create([
        'partner_id' => $partner->id, 'organization_id' => $partner->organization_id, 'invoice_id' => 'inv-'.uniqid('', true), 'period' => now()->format('Y-m'), 'kind' => 'share',
        'base_minor' => $minor * 5, 'rate_pct' => 20, 'amount_minor' => $minor, 'currency' => 'CZK', 'state' => $payoutId === null ? 'payable' : 'allocated', 'payout_id' => $payoutId, 'invoice_paid_at' => now()->subDays(2),
    ]);
}

/** A payout row as it may exist today (written before this task): state, IBAN and amount as given. */
function pstPayoutRow(Partner $partner, string $number, int $minor, ?string $iban, string $state, ?Carbon $at = null): PartnerPayout
{
    $at ??= now()->subDays(2);

    return PartnerPayout::query()->create([
        'partner_id' => $partner->id, 'number' => $number, 'amount_minor' => $minor, 'currency' => 'CZK', 'method' => 'bank_transfer', 'iban' => $iban, 'state' => $state,
        'self_billing' => ['number' => $number], 'requested_at' => $at, 'paid_at' => $state === 'paid' ? $at->copy()->addDay() : null,
    ]);
}

/** An IBAN a paid payout of the partner already went to: grandfathered as the partner's confirmed account (program D13). */
function pstPaidBefore(Partner $partner, string $iban): PartnerPayout
{
    return pstPayoutRow($partner, 'PO-HIST-'.strtoupper(substr(uniqid(), -6)), 100000, $iban, 'paid', now()->subMonths(2));
}

function pstAttach(Organization $organization, User $user, string $role): User
{
    app(OrganizationService::class)->attachMember($organization, $user, $role, CommandContext::system('test'), true);

    return $user;
}

/**
 * The second request of a race (the PostgreSQL model; the test database is one SQLite connection). It arrives the moment the
 * first request first reads the partner's commissions. If the first request holds the partner row by then — it read the
 * partner inside its own payout transaction, below the command bus's transaction (`FOR UPDATE` on PostgreSQL) — the second
 * one waits for it as it would on the lock and runs after the first returned; nothing else holds it, so otherwise it runs at
 * once, in the middle of the first request. It carries its own Idempotency-Key.
 *
 * @return Closure(): (array<string,mixed>|DomainError|null) runs a waiting second request and returns what it answered
 */
function pstRace(Organization $organization, User $owner, string $key, string $amount): Closure
{
    $base = DB::transactionLevel(); // the test's own transaction; the bus opens base + 1, the payout transaction base + 2
    $state = ['locked' => false, 'fired' => false, 'waiting' => false, 'result' => null];
    $second = function () use ($organization, $owner, $key, $amount, &$state): void {
        try {
            $state['result'] = app(CommandBus::class)->dispatch(
                new PartnerPortalCommand($organization->id, "partner.payout:{$organization->id}:{$key}", ['op' => 'payout.request', 'amount' => $amount, 'iban' => pstIban('A'), 'method' => 'bank_transfer']),
                new CommandContext('user', $owner->id, $organization->id, null, '127.0.0.1', 'pest', 'race-second-session', stepUpMethod: 'totp'),
            );
        } catch (DomainError $e) {
            $state['result'] = $e;
        }
    };
    DB::listen(function (QueryExecuted $query) use (&$state, $second, $base): void {
        if ($state['fired']) {
            return;
        }
        $sql = strtolower($query->sql);
        if (str_contains($sql, 'from "partners"') && DB::transactionLevel() >= $base + 2 && (DB::getDriverName() !== 'pgsql' || str_contains($sql, 'for update'))) {
            $state['locked'] = true;

            return;
        }
        if (str_contains($sql, 'from "partner_commissions"') && DB::transactionLevel() >= $base + 1) {
            $state['fired'] = true;
            if ($state['locked']) {
                $state['waiting'] = true;

                return;
            }
            $second();
        }
    });

    return function () use (&$state, $second) {
        if ($state['waiting']) {
            $state['waiting'] = false;
            $second();
        }

        return $state['result'];
    };
}

/** The digest the dry run of `onhost:partners:payout-anomalies` printed for its list (review round 1: what --apply must name). */
function pstDigest(string $output): string
{
    expect(preg_match('/List digest: ([0-9a-f]{12})/', $output, $match))->toBe(1);

    return $match[1];
}

/**
 * Two payments of one approved payout racing (review round 1, the pay-side twin of pstRace): the second arrives the moment
 * the first sums the payout's commissions. If the first read the payout row inside its own transaction by then (`FOR UPDATE`
 * on PostgreSQL) the second waits for it and runs after the first returned; otherwise it runs at once, in the middle.
 *
 * @return Closure(): (PartnerPayout|DomainError|null) runs a waiting second payment and returns what it answered
 */
function pstPayRace(PartnerPayout $payout): Closure
{
    $base = DB::transactionLevel();
    $state = ['locked' => false, 'fired' => false, 'waiting' => false, 'result' => null];
    $second = function () use ($payout, &$state): void {
        try {
            $state['result'] = app(PartnerService::class)->markPayoutPaid(PartnerPayout::query()->findOrFail($payout->id), 'BANK-SECOND', CommandContext::system('second-payer'));
        } catch (DomainError $e) {
            $state['result'] = $e;
        }
    };
    DB::listen(function (QueryExecuted $query) use (&$state, $second, $base): void {
        if ($state['fired']) {
            return;
        }
        $sql = strtolower($query->sql);
        if (str_contains($sql, 'from "partner_payouts"') && DB::transactionLevel() >= $base + 1 && (DB::getDriverName() !== 'pgsql' || str_contains($sql, 'for update'))) {
            $state['locked'] = true;

            return;
        }
        if (str_contains($sql, 'from "partner_commissions"') && DB::transactionLevel() >= $base + 1) {
            $state['fired'] = true;
            if ($state['locked']) {
                $state['waiting'] = true;

                return;
            }
            $second();
        }
    });

    return function () use (&$state, $second) {
        if ($state['waiting']) {
            $state['waiting'] = false;
            $second();
        }

        return $state['result'];
    };
}

/** @return array<string,int> payout number => (amount − what its commissions sum to) for every payout of the partner */
function pstUnbacked(Partner $partner): array
{
    $out = [];
    foreach (PartnerPayout::query()->where('partner_id', $partner->id)->where('number', 'not like', 'PO-HIST-%')->get() as $payout) {
        $out[$payout->number] = $payout->amount_minor - (int) PartnerCommission::query()->where('payout_id', $payout->id)->sum('amount_minor');
    }

    return $out;
}

// ── WP1: one balance, one payout (P1) ────────────────────────────────────────────────────────────────────────────────

it('pays a balance out once when two payout requests with different Idempotency-Keys race for it', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['name' => 'Agentura Závod s.r.o.']);
    $partner = pstPartnerOf($org);
    pstPaidBefore($partner, pstIban('A'));
    pstEarn($partner, 150000);
    pstEarn($partner, 50000);
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $this->actingAs($owner, 'sanctum');

    $finish = pstRace($org, $owner, 'race-second', '2000');
    $this->withHeaders(['X-Organization' => $org->id, 'Idempotency-Key' => 'race-first'])->postJson('/v1/partner/payouts', ['amount' => 2000, 'iban' => pstIban('A')])->assertCreated();
    $second = $finish();

    expect($second)->toBeInstanceOf(DomainError::class)->and($second->error)->toBe('payout_exceeds_balance');
    expect(PartnerPayout::query()->where('partner_id', $partner->id)->whereIn('state', ['requested', 'approved'])->count())->toBe(1)
        ->and(pstUnbacked($partner))->each->toBe(0);
});

it('refuses the second of two payout requests for the whole balance, and every payout is exactly what it allocated', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $partner = pstPartnerOf($org);
    pstPaidBefore($partner, pstIban('A'));
    pstEarn($partner, 120000);
    pstEarn($partner, -20000); // a credit note reversed part of it
    pstEarn($partner, 60000);
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $this->actingAs($owner, 'sanctum');
    $h = ['X-Organization' => $org->id];

    $this->withHeaders($h + ['Idempotency-Key' => 'seq-1'])->postJson('/v1/partner/payouts', ['amount' => 1600, 'iban' => pstIban('A')])->assertCreated()->assertJsonPath('amount.minor', 160000);
    $this->withHeaders($h + ['Idempotency-Key' => 'seq-2'])->postJson('/v1/partner/payouts', ['amount' => 1000, 'iban' => pstIban('A')])->assertUnprocessable()->assertJsonPath('error', 'payout_exceeds_balance');
    expect(pstUnbacked($partner))->toHaveCount(1)->each->toBe(0)
        ->and((int) PartnerCommission::query()->where('partner_id', $partner->id)->where('state', 'payable')->sum('amount_minor'))->toBe(0);
});

// ── WP2: the IBAN comes from the confirmed payout account only (P2) ─────────────────────────────────────────────────

it('takes the IBAN from the confirmed payout account only, never from the payout request', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $partner = pstPartnerOf($org);
    pstPaidBefore($partner, pstIban('A')); // an IBAN a paid payout went to is grandfathered as confirmed
    pstEarn($partner, 300000);
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $this->actingAs($owner, 'sanctum');
    $h = ['X-Organization' => $org->id];

    $this->withHeaders($h + ['Idempotency-Key' => 'iban-1'])->postJson('/v1/partner/payouts', ['amount' => 1000, 'iban' => pstIban('B')])->assertStatus(409)->assertJsonPath('error', 'payout_account_mismatch');
    expect(PartnerPayout::query()->where('partner_id', $partner->id)->where('state', 'requested')->exists())->toBeFalse()
        ->and((string) $partner->fresh()->iban)->not->toBe(pstIban('B'));

    $payout = $this->withHeaders($h + ['Idempotency-Key' => 'iban-2'])->postJson('/v1/partner/payouts', ['amount' => 1000])->assertCreated();
    expect(PartnerPayout::query()->findOrFail($payout->json('id'))->iban)->toBe(pstIban('A'));
    $account = $this->withHeaders($h)->getJson('/v1/partner/payout-account')->assertOk()->json('data');
    expect($account['active'])->toMatchArray(['iban_masked' => 'CZ65…5399', 'source' => 'grandfathered'])->and($account['pending'])->toBeNull()->and($account['cooling_off_days'])->toBe(7);
});

it('changes the payout account only as its own step: the owner, a fresh step-up, a notice, and seven days before it is used', function () {
    [$owner, $org] = $this->customerWithOrganization(['email' => 'majitel@pixel.cz'], ['name' => 'Agentura Pixel s.r.o.', 'billing_email' => 'fakturace@pixel.cz']);
    $partner = pstPartnerOf($org);
    pstPaidBefore($partner, pstIban('A'));
    pstEarn($partner, 300000);
    $h = ['X-Organization' => $org->id];

    // an organization admin runs the organization, not where its commission is paid
    $admin = pstAttach($org, $this->customer(), 'org_admin');
    app(StepUpService::class)->grant($admin, 'totp', null, '127.0.0.1');
    $this->actingAs($admin, 'sanctum');
    $this->withHeaders($h + ['Idempotency-Key' => 'acc-admin'])->putJson('/v1/partner/payout-account', ['iban' => pstIban('B')])->assertForbidden();

    $this->actingAs($owner, 'sanctum');
    $this->withHeaders($h + ['Idempotency-Key' => 'acc-1'])->putJson('/v1/partner/payout-account', ['iban' => pstIban('B')])->assertForbidden()->assertJsonPath('error', 'step_up_required');
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $changed = $this->withHeaders($h + ['Idempotency-Key' => 'acc-2'])->putJson('/v1/partner/payout-account', ['iban' => 'CZ62 0300 0000 0001 2345 6789'])->assertOk();
    expect($changed->json('active.iban_masked'))->toBe('CZ65…5399')->and($changed->json('pending.iban_masked'))->toBe('CZ62…6789')
        ->and(substr((string) $changed->json('pending.usable_from'), 0, 10))->toBe(now()->addDays(7)->toDateString());
    app(OutboxPublisher::class)->relayPending();
    expect(MailOutbox::query()->where('template_key', 'legal-notice')->where('to', 'majitel@pixel.cz')->exists())->toBeTrue()
        ->and(MailOutbox::query()->where('template_key', 'legal-notice')->where('to', 'fakturace@pixel.cz')->exists())->toBeTrue()
        ->and(Notification::query()->where('organization_id', $org->id)->where('event', 'partner.payout_account.changed')->where('audience', 'customer')->exists())->toBeTrue()
        ->and(json_encode(MailOutbox::query()->where('template_key', 'legal-notice')->pluck('vars')))->not->toContain(pstIban('B'));

    // while the new account cools off, a payout still goes to the confirmed one, and the new IBAN is refused
    $this->withHeaders($h + ['Idempotency-Key' => 'acc-p1'])->postJson('/v1/partner/payouts', ['amount' => 1000, 'iban' => pstIban('B')])->assertStatus(409)->assertJsonPath('error', 'payout_account_mismatch');
    $first = $this->withHeaders($h + ['Idempotency-Key' => 'acc-p2'])->postJson('/v1/partner/payouts', ['amount' => 1000])->assertCreated();
    expect(PartnerPayout::query()->findOrFail($first->json('id'))->iban)->toBe(pstIban('A'));

    $this->travel(7)->days();
    $this->travel(1)->minutes();
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $later = $this->withHeaders($h + ['Idempotency-Key' => 'acc-p3'])->postJson('/v1/partner/payouts', ['amount' => 1000])->assertCreated();
    expect(PartnerPayout::query()->findOrFail($later->json('id'))->iban)->toBe(pstIban('B'))
        ->and($this->withHeaders($h)->getJson('/v1/partner/payout-account')->json('data.active'))->toMatchArray(['iban_masked' => 'CZ62…6789', 'source' => 'owner']);
});

it('lets the owner call off an account change before it is used, and refuses an IBAN that fails its check digits', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $partner = pstPartnerOf($org);
    pstEarn($partner, 300000);
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $this->actingAs($owner, 'sanctum');
    $h = ['X-Organization' => $org->id];

    // no paid payout, no account: a bank transfer needs the account first, whatever IBAN the request names
    $this->withHeaders($h + ['Idempotency-Key' => 'none-1'])->postJson('/v1/partner/payouts', ['amount' => 1000, 'iban' => pstIban('A')])->assertStatus(409)->assertJsonPath('error', 'payout_account_missing');
    $this->withHeaders($h + ['Idempotency-Key' => 'none-2'])->putJson('/v1/partner/payout-account', ['iban' => 'CZ6508000000192000145398'])->assertUnprocessable()->assertJsonPath('error', 'payout_iban_invalid');
    $this->withHeaders($h + ['Idempotency-Key' => 'none-3'])->putJson('/v1/partner/payout-account', ['iban' => pstIban('C')])->assertOk()->assertJsonPath('active', null);
    $this->withHeaders($h + ['Idempotency-Key' => 'none-4'])->deleteJson('/v1/partner/payout-account/pending')->assertOk()->assertJsonPath('pending', null);
    $this->travel(8)->days();
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $this->withHeaders($h + ['Idempotency-Key' => 'none-5'])->postJson('/v1/partner/payouts', ['amount' => 1000])->assertStatus(409)->assertJsonPath('error', 'payout_account_missing');
});

it('requests automatic payouts to a confirmed account only, never to an IBAN a request once wrote on the partner', function () {
    [, $org] = $this->customerWithOrganization();
    $partner = pstPartnerOf($org, 'monthly');
    $partner->forceFill(['iban' => pstIban('B')])->save(); // what an unpaid request of the old code wrote there
    pstEarn($partner, 150000);
    $partners = app(PartnerService::class);

    expect($partners->autoPayouts())->toBe(['requested' => 0, 'skipped' => 1]);
    pstPaidBefore($partner, pstIban('A'));
    expect($partners->autoPayouts())->toBe(['requested' => 1, 'skipped' => 0])
        ->and(PartnerPayout::query()->where('partner_id', $partner->id)->where('state', 'requested')->sole()->iban)->toBe(pstIban('A'));
});

// ── WP3: paid only from `approved`, by somebody else, with a second person (P8, TD-4) ───────────────────────────────

it('pays only an approved payout, and not twice', function () {
    [, $org] = $this->customerWithOrganization();
    $partner = pstPartnerOf($org);
    pstPaidBefore($partner, pstIban('A'));
    pstEarn($partner, 200000);
    $partners = app(PartnerService::class);
    $payout = $partners->requestPayout($partner, Money::minor(150000, 'CZK'), pstIban('A'), CommandContext::system('test')->withScope($org->id));

    expect(fn () => $partners->markPayoutPaid($payout, 'BANK-EARLY', CommandContext::system('test')))->toThrow(DomainError::class, 'approved');
    $partners->approvePayout($payout->fresh(), CommandContext::system('test'));
    $partners->markPayoutPaid($payout->fresh(), 'BANK-1', CommandContext::system('test'));
    expect(fn () => $partners->markPayoutPaid($payout->fresh(), 'BANK-2', CommandContext::system('test')))->toThrow(DomainError::class);
    expect(LedgerTransaction::query()->where('kind', 'partner_payout')->count())->toBe(1);
});

it('pays out only with a second person, and never by whoever approved or requested the payout', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $partner = pstPartnerOf($org);
    pstPaidBefore($partner, pstIban('A'));
    pstEarn($partner, 200000);
    $payout = app(PartnerService::class)->requestPayout($partner, Money::minor(150000, 'CZK'), pstIban('A'), $this->contextFor($owner, $org));
    $anna = $this->steppedUpStaff('billing_finance_admin');
    $petr = $this->steppedUpStaff('billing_finance_admin');

    $this->actingAs($anna, 'sanctum');
    $this->postJson("/v1/staff/partners/payouts/{$payout->id}/approve")->assertOk()->assertJsonPath('state', 'approved');
    $asked = $this->postJson("/v1/staff/partners/payouts/{$payout->id}/pay", ['reference' => 'BANK-ANNA'])->assertForbidden()->assertJsonPath('error', 'approval_required');
    secondPersonApproves((string) $asked->json('approval_id'), $petr);
    // Petr signed the payment, but Anna approved the payout herself: the payment needs yet another pair of eyes
    $this->postJson("/v1/staff/partners/payouts/{$payout->id}/pay", ['reference' => 'BANK-ANNA', 'approval_ids' => [$asked->json('approval_id')]])->assertStatus(409)->assertJsonPath('error', 'payout_same_person');
    expect($payout->fresh()->state)->toBe('approved');

    $this->actingAs($petr, 'sanctum');
    $again = $this->postJson("/v1/staff/partners/payouts/{$payout->id}/pay", ['reference' => 'BANK-PETR'])->assertForbidden()->assertJsonPath('error', 'approval_required');
    secondPersonApproves((string) $again->json('approval_id'), $anna);
    $this->postJson("/v1/staff/partners/payouts/{$payout->id}/pay", ['reference' => 'BANK-PETR', 'approval_ids' => [$again->json('approval_id')]])->assertOk()->assertJsonPath('state', 'paid');
    expect(LedgerTransaction::query()->where('kind', 'partner_payout')->count())->toBe(1);

    // the person who asked for a payout does not pay it either (a partner member who is also staff)
    pstEarn($partner, 150000);
    $mine = app(PartnerService::class)->requestPayout($partner, Money::minor(100000, 'CZK'), pstIban('A'), $this->contextFor($owner, $org));
    app(PartnerService::class)->approvePayout($mine, CommandContext::system('test'));
    expect(fn () => app(PartnerService::class)->markPayoutPaid($mine->fresh(), 'BANK-SELF', $this->contextFor($owner)))->toThrow(DomainError::class);
    expect($mine->fresh()->state)->toBe('approved');
});

it('lets a sole operator pay a payout they approved, after the time lock', function () {
    config(['onhost.identity.four_eyes' => false]);
    [, $org] = $this->customerWithOrganization();
    $partner = pstPartnerOf($org);
    pstPaidBefore($partner, pstIban('A'));
    pstEarn($partner, 200000);
    $payout = app(PartnerService::class)->requestPayout($partner, Money::minor(150000, 'CZK'), pstIban('A'), CommandContext::system('test')->withScope($org->id));
    $solo = $this->steppedUpStaff('billing_finance_admin');
    $this->actingAs($solo, 'sanctum');

    $this->postJson("/v1/staff/partners/payouts/{$payout->id}/approve")->assertOk();
    $this->soloAfterTimeLock($solo, fn () => $this->postJson("/v1/staff/partners/payouts/{$payout->id}/pay", ['reference' => 'BANK-SOLO']))->assertOk()->assertJsonPath('state', 'paid');
});

// ── WP4: the look at existing payouts — dry run first, then only the anomalous ones are frozen ──────────────────────

it('lists anomalous open payouts in a dry run and freezes only those once the owner said go', function () {
    [, $org] = $this->customerWithOrganization();
    $partner = pstPartnerOf($org);
    pstPaidBefore($partner, pstIban('A'));
    $above = pstPayoutRow($partner, 'PO-ANOM-1', 300000, pstIban('A'), 'requested'); // more than it allocated: the race of old
    pstEarn($partner, 100000, $above->id);
    $moved = pstPayoutRow($partner, 'PO-ANOM-2', 100000, pstIban('B'), 'approved'); // an IBAN no paid payout went to
    pstEarn($partner, 100000, $moved->id);
    $clean = pstPayoutRow($partner, 'PO-ANOM-3', 100000, pstIban('A'), 'requested');
    pstEarn($partner, 100000, $clean->id);
    [, $org2] = $this->customerWithOrganization();
    $partner2 = pstPartnerOf($org2);
    $first = pstPayoutRow($partner2, 'PO-ANOM-4', 100000, pstIban('C'), 'requested'); // the partner's first payout: nothing to compare with
    pstEarn($partner2, 100000, $first->id);

    expect(Artisan::call('onhost:partners:payout-anomalies'))->toBe(0);
    $out = Artisan::output();
    expect($out)->toContain('PO-ANOM-1')->toContain('amount_above_allocated')->toContain('PO-ANOM-2')->toContain('iban_changed')->toContain('PO-ANOM-4')
        ->not->toContain('PO-ANOM-3')->not->toContain(pstIban('B'))->toContain('CZ62…6789')->toContain('Nothing was changed');
    expect(PartnerPayout::query()->whereNotNull('frozen_at')->count())->toBe(0);

    // review round 1: --apply freezes the list the owner saw, named by its digest — never whatever a new scan finds
    expect(Artisan::call('onhost:partners:payout-anomalies', ['--apply' => true]))->toBe(1)
        ->and(Artisan::output())->toContain('--digest');
    expect(PartnerPayout::query()->whereNotNull('frozen_at')->count())->toBe(0);
    expect(Artisan::call('onhost:partners:payout-anomalies', ['--apply' => true, '--digest' => pstDigest($out)]))->toBe(0);
    expect(PartnerPayout::query()->whereNotNull('frozen_at')->orderBy('number')->pluck('number')->all())->toBe(['PO-ANOM-1', 'PO-ANOM-2']);
    Artisan::call('onhost:partners:payout-anomalies'); // a second run finds nothing new
    expect(Artisan::call('onhost:partners:payout-anomalies', ['--apply' => true, '--digest' => pstDigest(Artisan::output())]))->toBe(0);
    expect(PartnerPayout::query()->whereNotNull('frozen_at')->count())->toBe(2);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('event', 'partner.payout.frozen')->where('audience', 'internal')->count())->toBe(2);

    // a frozen payout is neither approved nor paid; finance rejects it (the commissions go back) or releases it with a reason
    $finance = $this->steppedUpStaff('billing_finance_admin');
    $this->actingAs($finance, 'sanctum');
    $this->postJson("/v1/staff/partners/payouts/{$above->id}/approve")->assertStatus(409)->assertJsonPath('error', 'payout_frozen');
    $this->postJson("/v1/staff/partners/payouts/{$above->id}/reject", ['reason' => 'Částka nad přidělenou provizí'])->assertOk()->assertJsonPath('state', 'rejected');
    $this->postJson("/v1/staff/partners/payouts/{$moved->id}/unfreeze", ['reason' => 'krátce'])->assertUnprocessable();
    $this->postJson("/v1/staff/partners/payouts/{$moved->id}/unfreeze", ['reason' => 'Partner potvrdil nový účet telefonicky i písemně'])->assertOk()->assertJsonPath('frozen', false);
    $this->postJson("/v1/staff/partners/payouts/{$clean->id}/freeze", ['reason' => 'Kontrola účtu na žádost partnera'])->assertOk()->assertJsonPath('frozen', true);
});

// ── WP5: portal reads need partner.portal.read; client contacts and dunning are masked (P5, program §10 O9) ────────

it('shows the partner portal only to members who hold partner.portal.read', function () {
    [$owner, $org] = $this->customerWithOrganization();
    pstPartnerOf($org);
    $h = ['X-Organization' => $org->id];
    $paths = ['/v1/partner/overview', '/v1/partner/clients', '/v1/partner/commissions', '/v1/partner/payouts', '/v1/partner/payout-account'];

    foreach (['viewer', 'developer', 'support_contact'] as $role) {
        $this->actingAs(pstAttach($org, $this->customer(), $role), 'sanctum');
        foreach ($paths as $path) {
            $this->withHeaders($h)->getJson($path)->assertForbidden();
        }
    }
    foreach (['billing_admin', 'partner', 'org_admin'] as $role) {
        $this->actingAs(pstAttach($org, $this->customer(), $role), 'sanctum');
        foreach ($paths as $path) {
            $this->withHeaders($h)->getJson($path)->assertOk();
        }
    }
    $this->actingAs($owner, 'sanctum');
    $this->withHeaders($h)->getJson('/v1/partner/clients')->assertOk();

    expect(PermissionCatalog::all()['partner.portal.read'])->toMatchArray(['risk' => PermissionCatalog::NORMAL, 'audience' => 'customer'])
        ->and(PermissionCatalog::all()['partner.payout_account.manage'])->toMatchArray(['risk' => PermissionCatalog::HIGH, 'audience' => 'customer'])
        ->and(array_keys(array_filter(RoleCatalog::all(), fn (array $r) => in_array('partner.payout_account.manage', $r['permissions'], true))))->toBe(['owner']);
});

it('masks client contacts and dunning in the partner view, and keeps them for staff', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['name' => 'Agentura Pixel s.r.o.']);
    $partner = pstPartnerOf($org);
    [, $client] = $this->customerWithOrganization(['email' => 'petra@klient.cz'], ['name' => 'Klient s.r.o.', 'billing_email' => 'ucetni@klient.cz']);
    app(PartnerService::class)->attribute($client, $partner->code, CommandContext::system('test'));
    $invoices = app(InvoiceService::class);
    $draft = $invoices->draft($client, 'invoice', 'CZK', [['sku' => 'web', 'description' => 'Web', 'qty' => 1, 'unit' => 'ks', 'unit_net' => 10000, 'discount' => 0, 'net' => 10000, 'tax_rate' => '21', 'tax_category' => 'S', 'tax' => 2100, 'total' => 12100]], CommandContext::system('test'), null, ['postpaid' => true]);
    $invoices->issue($draft, CommandContext::system('test'))->forceFill(['due_at' => now()->subDays(5)])->save(); // overdue: the client is in dunning territory

    $this->actingAs($owner, 'sanctum');
    $rows = $this->withHeader('X-Organization', $org->id)->getJson('/v1/partner/clients')->assertOk()->json('data');
    expect($rows[0])->toMatchArray(['name' => 'Klient s.r.o.', 'contact' => null, 'contact_masked' => true, 'st' => 'ok']);
    expect(json_encode($this->withHeader('X-Organization', $org->id)->getJson('/v1/partner/overview')->assertOk()->json('data')))->not->toContain('petra@klient.cz')->not->toContain('ucetni@klient.cz');

    $this->actingAs($this->steppedUpStaff('billing_finance_admin'), 'sanctum');
    expect($this->getJson("/v1/staff/partners/{$partner->id}")->assertOk()->json('data.clients.0'))->toMatchArray(['contact' => 'petra@klient.cz', 'st' => 'due']);
});

it('tells every active partner once that client contacts and dunning are no longer shown', function () {
    [, $one] = $this->customerWithOrganization();
    [, $two] = $this->customerWithOrganization();
    [, $applied] = $this->customerWithOrganization();
    pstPartnerOf($one);
    pstPartnerOf($two);
    app(PartnerService::class)->apply($applied, ['model' => 'share'], CommandContext::system('test'));

    expect(Artisan::call('onhost:partners:masking-notice'))->toBe(0)->and(Artisan::output())->toContain('2 active partner(s)')->toContain('Nothing was sent');
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('event', 'partner.client_data.masked')->count())->toBe(0);

    expect(Artisan::call('onhost:partners:masking-notice', ['--send' => true]))->toBe(0);
    expect(Artisan::call('onhost:partners:masking-notice', ['--send' => true]))->toBe(0); // once per partner
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('event', 'partner.client_data.masked')->where('audience', 'customer')->pluck('organization_id')->sort()->values()->all())
        ->toBe(collect([$one->id, $two->id])->sort()->values()->all());
});

// ── review round 1: the pay step checks where the money goes; only IBANs paid before the cut-over are grandfathered ──

it('pays a bank transfer only to an account confirmed when it was asked for, and a released leftover never becomes the account', function () {
    [, $org] = $this->customerWithOrganization();
    $partner = pstPartnerOf($org, 'monthly');
    pstPaidBefore($partner, pstIban('A'));
    $moved = pstPayoutRow($partner, 'PO-LEG-1', 100000, pstIban('B'), 'approved'); // left open by the old code: its request typed B
    pstEarn($partner, 100000, $moved->id);
    [, $org2] = $this->customerWithOrganization();
    $partner2 = pstPartnerOf($org2, 'monthly');
    $first = pstPayoutRow($partner2, 'PO-LEG-2', 100000, pstIban('C'), 'approved'); // the partner's first payout: nothing confirmed C
    pstEarn($partner2, 100000, $first->id);
    $partners = app(PartnerService::class);
    $system = CommandContext::system('test');

    foreach ([$moved, $first] as $payout) {
        try {
            $partners->markPayoutPaid($payout->fresh(), 'BANK-LEG', $system);
            $this->fail("{$payout->number} was paid to an account nobody confirmed");
        } catch (DomainError $e) {
            expect($e->error)->toBe('payout_account_unconfirmed')->and($e->status)->toBe(409);
        }
        expect($payout->fresh()->state)->toBe('approved');
    }
    expect(LedgerTransaction::query()->where('kind', 'partner_payout')->count())->toBe(0);

    // finance held each, confirmed the account with the partner and released it with a reason: now it is paid …
    foreach ([$moved, $first] as $payout) {
        $partners->freezePayout($payout->fresh(), 'Kontrola účtu u partnera', $system);
        $partners->unfreezePayout($payout->fresh(), 'Partner potvrdil účet telefonicky i písemně', $system, confirmsAccount: true); // TASK-0041: says so
        expect($partners->markPayoutPaid($payout->fresh(), 'BANK-'.$payout->number, $system)->state)->toBe('paid');
    }

    // … and its IBAN does not become the partner's account: the next payouts, automatic ones included, go to A or wait for the owner
    expect(app(PayoutAccounts::class)->of($partner->fresh())['active'])->toMatchArray(['iban' => pstIban('A'), 'source' => 'grandfathered'])
        ->and(app(PayoutAccounts::class)->of($partner2->fresh())['active'])->toBeNull();
    pstEarn($partner, 150000);
    pstEarn($partner2, 150000);
    expect($partners->autoPayouts())->toBe(['requested' => 1, 'skipped' => 1])
        ->and(PartnerPayout::query()->where('partner_id', $partner->id)->where('state', 'requested')->sole()->iban)->toBe(pstIban('A'))
        ->and(PartnerPayout::query()->where('partner_id', $partner2->id)->where('state', 'requested')->exists())->toBeFalse();
});

it('releases an IBAN hold only with a second person, and only a release that says it confirmed the account counts', function () {
    // P0-16 red team (MEDIUM): one person holding partner.manage froze a payout to an IBAN nobody confirmed and unfroze it again —
    // and any unfreeze, whatever it was about, counted as "finance confirmed the account": the payment then went to that IBAN.
    [, $org] = $this->customerWithOrganization();
    $partner = pstPartnerOf($org, 'monthly');
    pstPaidBefore($partner, pstIban('A'));
    $moved = pstPayoutRow($partner, 'PO-REL-1', 100000, pstIban('B'), 'approved');
    pstEarn($partner, 100000, $moved->id);
    $partners = app(PartnerService::class);
    $system = CommandContext::system('test');
    $anna = $this->steppedUpStaff('billing_finance_admin');
    $petr = $this->steppedUpStaff('billing_finance_admin');
    $this->actingAs($anna, 'sanctum');
    $post = fn (string $step, array $body) => $this->postJson("/v1/staff/partners/payouts/{$moved->id}/{$step}", $body, ['Idempotency-Key' => uniqid('rel-', true)]);

    // a hold let go is not a confirmation of the account
    $post('freeze', ['reason' => 'Kontrola částky u partnera'])->assertOk()->assertJsonPath('frozen', true);
    $post('unfreeze', ['reason' => 'Částka sedí, uvolňuji ke kontrole'])->assertOk()->assertJsonPath('frozen', false);
    expect(fn () => $partners->markPayoutPaid($moved->fresh(), 'BANK-REL-0', $system))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('payout_account_unconfirmed'));

    // the release that confirms the account takes a second person; nobody confirms it alone, not even past the bus
    $post('freeze', ['reason' => 'Kontrola účtu u partnera'])->assertOk();
    $body = ['reason' => 'Partner potvrdil účet telefonicky i písemně', 'confirms_account' => true];
    $asked = $post('unfreeze', $body)->assertForbidden()->assertJsonPath('error', 'approval_required');
    expect($moved->fresh()->isFrozen())->toBeTrue();
    expect(fn () => $partners->unfreezePayout($moved->fresh(), 'Potvrdila jsem si to sama', $this->contextFor($anna), confirmsAccount: true))
        ->toThrow(fn (DomainError $e) => expect($e->error)->toBe('payout_release_second_person'));
    secondPersonApproves((string) $asked->json('approval_id'), $petr);
    $post('unfreeze', $body + ['approval_ids' => [$asked->json('approval_id')]])->assertOk()->assertJsonPath('frozen', false);

    expect($partners->markPayoutPaid($moved->fresh(), 'BANK-REL-1', $system)->state)->toBe('paid');
});

it('refuses to pay a payout whose IBAN is not the account row it was asked for, released or not', function () {
    [, $org] = $this->customerWithOrganization();
    $partner = pstPartnerOf($org);
    partnerConfirmedPayoutAccount($partner, pstIban('A'));
    pstEarn($partner, 200000);
    $partners = app(PartnerService::class);
    $system = CommandContext::system('test');
    $payout = $partners->requestPayout($partner, Money::minor(150000, 'CZK'), null, $system->withScope($org->id));
    expect($payout->payout_account_id)->not->toBeNull()->and($payout->iban)->toBe(pstIban('A'));
    $partners->approvePayout($payout, $system);
    $payout->fresh()->forceFill(['iban' => pstIban('B')])->save(); // a row changed past the domain

    expect(fn () => $partners->markPayoutPaid($payout->fresh(), 'BANK-X', $system))->toThrow(DomainError::class, 'confirmed');
    $partners->freezePayout($payout->fresh(), 'Kontrola účtu u partnera', $system);
    $partners->unfreezePayout($payout->fresh(), 'Partner potvrdil účet telefonicky i písemně', $system, confirmsAccount: true);
    expect(fn () => $partners->markPayoutPaid($payout->fresh(), 'BANK-Y', $system))->toThrow(DomainError::class, 'confirmed');
    expect($payout->fresh()->state)->toBe('approved')->and(LedgerTransaction::query()->where('kind', 'partner_payout')->count())->toBe(0);
});

it('freezes with --apply only the list the dry run showed, and refuses when the list changed since', function () {
    [, $org] = $this->customerWithOrganization();
    $partner = pstPartnerOf($org);
    pstPaidBefore($partner, pstIban('A'));
    $moved = pstPayoutRow($partner, 'PO-DIG-1', 100000, pstIban('B'), 'requested');
    pstEarn($partner, 100000, $moved->id);
    Artisan::call('onhost:partners:payout-anomalies');
    $seen = pstDigest(Artisan::output());

    $later = pstPayoutRow($partner, 'PO-DIG-2', 100000, pstIban('C'), 'requested'); // appeared after the owner read the list
    pstEarn($partner, 100000, $later->id);
    expect(Artisan::call('onhost:partners:payout-anomalies', ['--apply' => true, '--digest' => $seen]))->toBe(1)
        ->and(Artisan::output())->toContain('changed since the dry run');
    expect(PartnerPayout::query()->whereNotNull('frozen_at')->count())->toBe(0);

    Artisan::call('onhost:partners:payout-anomalies');
    expect(Artisan::call('onhost:partners:payout-anomalies', ['--apply' => true, '--digest' => pstDigest(Artisan::output())]))->toBe(0);
    expect(PartnerPayout::query()->whereNotNull('frozen_at')->orderBy('number')->pluck('number')->all())->toBe(['PO-DIG-1', 'PO-DIG-2']);
});

it('pays a payout once when two payments of it race', function () {
    [, $org] = $this->customerWithOrganization();
    $partner = pstPartnerOf($org);
    pstPaidBefore($partner, pstIban('A'));
    pstEarn($partner, 200000);
    $partners = app(PartnerService::class);
    $payout = $partners->requestPayout($partner, Money::minor(150000, 'CZK'), null, CommandContext::system('test')->withScope($org->id));
    $partners->approvePayout($payout, CommandContext::system('test'));

    $finish = pstPayRace($payout);
    $partners->markPayoutPaid($payout->fresh(), 'BANK-FIRST', CommandContext::system('first-payer'));
    $second = $finish();

    expect($second)->toBeInstanceOf(DomainError::class)->and($second->error)->toBe('payout_not_approved');
    expect(LedgerTransaction::query()->where('kind', 'partner_payout')->count())->toBe(1)
        ->and($payout->fresh()->payment_reference)->toBe('BANK-FIRST');
});

// ── review round 1: who is refused the payout doors — API tokens on the portal, staff without partner.manage ─────────

it('keeps the partner portal and the payout account away from API tokens, whatever their scopes', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $partner = pstPartnerOf($org);
    pstPaidBefore($partner, pstIban('A'));
    pstEarn($partner, 300000);
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $token = $owner->createToken('partner-all', TokenScopes::ALL);
    $token->accessToken->forceFill(['organization_id' => $org->id])->save();
    app('auth')->forgetGuards();
    $bearer = $token->plainTextToken;
    $h = ['X-Organization' => $org->id];

    foreach (['/v1/partner/overview', '/v1/partner/clients', '/v1/partner/commissions', '/v1/partner/payouts', '/v1/partner/payout-account'] as $path) {
        $this->withToken($bearer)->withHeaders($h)->getJson($path)->assertForbidden();
    }
    $this->withToken($bearer)->withHeaders($h + ['Idempotency-Key' => 'tok-pay'])->postJson('/v1/partner/payouts', ['amount' => 1000])->assertForbidden();
    $this->withToken($bearer)->withHeaders($h + ['Idempotency-Key' => 'tok-set'])->putJson('/v1/partner/payout-account', ['iban' => pstIban('B')])->assertForbidden();
    $this->withToken($bearer)->withHeaders($h + ['Idempotency-Key' => 'tok-del'])->deleteJson('/v1/partner/payout-account/pending')->assertForbidden();

    expect(PartnerPayout::query()->where('partner_id', $partner->id)->where('state', 'requested')->exists())->toBeFalse()
        ->and(PartnerPayoutAccount::query()->where('partner_id', $partner->id)->exists())->toBeFalse()
        ->and(TokenScopes::for('partner.portal.read'))->toBeNull()->and(TokenScopes::for('partner.payout_account.manage'))->toBeNull();
});

it('refuses every payout step to staff without partner.manage', function () {
    [, $org] = $this->customerWithOrganization();
    $partner = pstPartnerOf($org);
    pstPaidBefore($partner, pstIban('A'));
    pstEarn($partner, 200000);
    $payout = app(PartnerService::class)->requestPayout($partner, Money::minor(150000, 'CZK'), null, CommandContext::system('test')->withScope($org->id));
    $steps = ['approve' => [], 'reject' => ['reason' => 'Nesprávná částka výplaty'], 'pay' => ['reference' => 'BANK-NOPE'],
        'freeze' => ['reason' => 'Kontrola účtu u partnera'], 'unfreeze' => ['reason' => 'Partner potvrdil účet písemně']];

    foreach (['support_l2', 'billing_operator', 'auditor_read_only'] as $role) {
        $this->actingAs($this->steppedUpStaff($role), 'sanctum');
        foreach ($steps as $step => $body) {
            $this->postJson("/v1/staff/partners/payouts/{$payout->id}/{$step}", $body)->assertForbidden()->assertJsonPath('error', 'access_not_approved');
        }
    }
    expect($payout->fresh())->state->toBe('requested')->frozen_at->toBeNull()
        ->and(LedgerTransaction::query()->where('kind', 'partner_payout')->count())->toBe(0);
});
