<?php

declare(strict_types=1);

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Onhost\Domain\Identity\Authorization\ApprovalService;
use Onhost\Domain\Identity\Authorization\Models\Approval;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Partners\DuplicateCommissions;
use Onhost\Domain\Partners\Models\Partner;
use Onhost\Domain\Partners\Models\PartnerCommission;
use Onhost\Domain\Partners\PartnerService;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Owner decisions I-R6 and J-R3 (K5, TASK-0158): of a genuine double commission the NEWER row goes, by a new record — never an
 * amount edited by hand — and migration 000950, which refuses to build its unique index over such a group, is never edited.
 *
 *  - migration 000949 sorts before 000950 and sets each newer row aside (its invoice link moves to `duplicate_invoice_id`, an
 *    unpaid row becomes `duplicate`, never payable), so 000950 passes; a newer row in an open payout stops it before anything changes;
 *  - finance cancels a set-aside row through the bus (HIGH: a fresh step-up and a second person): an unpaid row is `cancelled` with
 *    a cancelled reversal, a paid one keeps `paid` and its reversal is a minus on the next payouts; a second cancellation is refused.
 */

beforeEach(function () {
    Http::preventStrayRequests();
});

/** @return array{0: Partner, 1: Organization, 2: Organization} an approved share partner, its client, the partner organization */
function cduPartner(object $test): array
{
    $organizationOf = fn (string $name): Organization => (fn () => $this->customerWithOrganization([], ['name' => $name])[1])->call($test);
    $partnerOrg = $organizationOf('Agentura Dvojí s.r.o.');
    User::query()->whereKey($partnerOrg->owner_user_id)->update(['email_verified_at' => now()]);
    $client = $organizationOf('Klient Dvojí s.r.o.');
    $partners = app(PartnerService::class);
    $partner = $partners->approve($partners->apply($partnerOrg, ['model' => 'share'], CommandContext::system('test')), CommandContext::system('test'));

    return [$partner->fresh(), $client, $partnerOrg];
}

/** A raw commission row as an older release wrote it (only the columns of 000440). @return array<string, mixed> */
function cduRow(string $id, string $invoice, int $amount, string $state, ?string $payout, int $minutesAgo, string $kind = 'share', string $partner = 'ptn_dup', string $client = 'org_dup'): array
{
    return [
        'id' => $id, 'partner_id' => $partner, 'organization_id' => $client, 'invoice_id' => $invoice, 'period' => '2026-09', 'kind' => $kind, 'base_minor' => 1000000, 'rate_pct' => 20,
        'amount_minor' => $amount, 'currency' => 'CZK', 'state' => $state, 'payout_id' => $payout, 'invoice_paid_at' => now()->subDays(10), 'created_at' => now()->subMinutes($minutesAgo), 'updated_at' => now(),
    ];
}

/** 000950 and 000949 rolled back: the table as the release before them left it. @return array{0: object, 1: object} */
function cduBeforeTheIndex(): array
{
    $grace = require database_path('migrations/0001_01_01_000950_partner_commissions_wait_out_their_grace.php');
    $aside = require database_path('migrations/0001_01_01_000949_partner_commission_duplicates_stand_aside.php');
    $grace->down();
    $aside->down();

    return [$aside, $grace];
}

/** A set-aside newer row on the current schema (what 000949 leaves), with the commission that stands. */
function cduSetAside(Partner $partner, Organization $client, string $state = PartnerCommission::DUPLICATE, int $amount = 200000): PartnerCommission
{
    $standing = PartnerCommission::query()->create([
        'partner_id' => $partner->id, 'organization_id' => $client->id, 'invoice_id' => 'inv_dup_'.$state, 'period' => '2026-09', 'kind' => 'share', 'base_minor' => 1000000, 'rate_pct' => 20,
        'amount_minor' => $amount, 'currency' => 'CZK', 'state' => 'payable', 'invoice_paid_at' => now()->subDays(40), 'payable_at' => now()->subDays(10),
    ]);

    return PartnerCommission::query()->create([
        'partner_id' => $partner->id, 'organization_id' => $client->id, 'invoice_id' => null, 'duplicate_invoice_id' => 'inv_dup_'.$state, 'duplicate_of_id' => $standing->id, 'period' => '2026-09', 'kind' => 'share',
        'base_minor' => 1000000, 'rate_pct' => 20, 'amount_minor' => $amount, 'currency' => 'CZK', 'state' => $state, 'payout_id' => $state === PartnerCommission::PAID ? 'po_dup' : null, 'invoice_paid_at' => now()->subDays(40), 'payable_at' => now()->subDays(10),
    ]);
}

it('sets the newer row of a genuine double aside before 000950, so the index is built and nothing is paid twice', function () {
    [$aside, $grace] = cduBeforeTheIndex();
    DB::table('partner_commissions')->insert([cduRow('pcm_split_a', 'inv_split', 150000, 'allocated', 'po_split', 10), cduRow('pcm_split_b', 'inv_split', 50000, 'payable', null, 5)]); // a payout remainder: 000950's
    DB::table('partner_commissions')->insert([cduRow('pcm_dbl_a', 'inv_dbl', 200000, 'payable', null, 10), cduRow('pcm_dbl_b', 'inv_dbl', 200000, 'payable', null, 5)]);       // a genuine double, nobody paid
    DB::table('partner_commissions')->insert([cduRow('pcm_paid_a', 'inv_paid', 200000, 'paid', 'po_old', 10), cduRow('pcm_paid_b', 'inv_paid', 200000, 'paid', 'po_old2', 5)]); // a double paid out twice
    DB::table('partner_commissions')->insert([cduRow('pcm_rev_a', 'cn_rev', -50000, 'payable', null, 10, 'reversal'), cduRow('pcm_rev_b', 'cn_rev', -50000, 'payable', null, 5, 'reversal')]); // a doubled reversal

    $aside->up();
    $grace->up(); // passes: the only groups left are payout remainders

    $row = fn (string $id) => DB::table('partner_commissions')->where('id', $id)->first();
    expect($row('pcm_dbl_b')->invoice_id)->toBeNull()->and($row('pcm_dbl_b')->duplicate_invoice_id)->toBe('inv_dbl')->and($row('pcm_dbl_b')->duplicate_of_id)->toBe('pcm_dbl_a')
        ->and($row('pcm_dbl_b')->state)->toBe(PartnerCommission::DUPLICATE)->and((int) $row('pcm_dbl_b')->amount_minor)->toBe(200000) // the amount is never edited
        ->and($row('pcm_dbl_a')->invoice_id)->toBe('inv_dbl')->and($row('pcm_dbl_a')->state)->toBe('payable')                       // the older row stands
        ->and($row('pcm_paid_b')->state)->toBe('paid')->and($row('pcm_paid_b')->duplicate_of_id)->toBe('pcm_paid_a')                  // the money left: it stays paid
        ->and($row('pcm_rev_b')->state)->toBe(PartnerCommission::DUPLICATE)->and($row('pcm_rev_b')->duplicate_invoice_id)->toBe('cn_rev')
        ->and($row('pcm_split_b')->duplicate_of_id)->toBeNull()->and((bool) $row('pcm_split_b')->fragment)->toBeTrue();              // remainders stay 000950's

    // the index 000950 built holds: a third commission of the same invoice and kind is refused by the database
    expect(fn () => DB::transaction(fn () => DB::table('partner_commissions')->insert(cduRow('pcm_dbl_c', 'inv_dbl', 1, 'payable', null, 1) + ['fragment' => false])))
        ->toThrow(UniqueConstraintViolationException::class);
    // no payout takes a set-aside row
    expect(PartnerCommission::query()->where('partner_id', 'ptn_dup')->where('state', 'payable')->pluck('id')->sort()->values()->all())->toBe(['pcm_dbl_a', 'pcm_rev_a', 'pcm_split_b']);
});

it('stops before it changes anything when the newer row of a double sits in an open payout', function () {
    [$aside] = cduBeforeTheIndex();
    DB::table('partner_commissions')->insert([cduRow('pcm_open_a', 'inv_open', 200000, 'payable', null, 10), cduRow('pcm_open_b', 'inv_open', 200000, 'allocated', 'po_open', 5)]);

    expect(fn () => $aside->up())->toThrow(RuntimeException::class, 'inv_open/share (payout po_open)');
    expect(Schema::hasColumn('partner_commissions', 'duplicate_of_id'))->toBeFalse()
        ->and(DB::table('partner_commissions')->where('id', 'pcm_open_b')->value('invoice_id'))->toBe('inv_open');

    DB::table('partner_commissions')->where('id', 'pcm_open_b')->update(['state' => 'payable', 'payout_id' => null]); // finance rejected the payout
    $aside->up();
    expect(DB::table('partner_commissions')->where('id', 'pcm_open_b')->value('state'))->toBe(PartnerCommission::DUPLICATE);
});

it('puts a set-aside row back on rollback', function () {
    [$aside] = cduBeforeTheIndex();
    DB::table('partner_commissions')->insert([cduRow('pcm_rb_a', 'inv_rb', 200000, 'payable', null, 10), cduRow('pcm_rb_b', 'inv_rb', 200000, 'payable', null, 5)]);
    $aside->up();
    $aside->down();

    expect(Schema::hasColumn('partner_commissions', 'duplicate_of_id'))->toBeFalse()
        ->and(DB::table('partner_commissions')->where('id', 'pcm_rb_b')->first())->invoice_id->toBe('inv_rb')->state->toBe('payable');
    $aside->up(); // and the deploy can run it again
    expect(DB::table('partner_commissions')->where('id', 'pcm_rb_b')->value('state'))->toBe(PartnerCommission::DUPLICATE);
});

it('cancels the newer row through the bus only with a fresh step-up and a second person, by a new reversal record', function () {
    [$partner, $client, $partnerOrg] = cduPartner($this);
    $row = cduSetAside($partner, $client);
    $url = "/v1/staff/partners/commissions/{$row->id}/cancel-duplicate";
    $body = ['reason' => 'I-R6: druhá provize k faktuře, zrušena novější'];

    $finance = $this->staff('billing_finance_admin');
    $this->actingAs($finance, 'sanctum');
    $this->postJson($url, ['reason' => 'krátce'])->assertStatus(422);
    $this->postJson($url, $body)->assertForbidden()->assertJsonPath('error', 'step_up_required');
    app(StepUpService::class)->grant($finance, 'totp', null, '127.0.0.1');
    $approval = $this->postJson($url, $body)->assertForbidden()->assertJsonPath('error', 'approval_required')->json('approval_id');
    expect($row->fresh()->state)->toBe(PartnerCommission::DUPLICATE); // one person alone changed nothing
    expect(fn () => app(ApprovalService::class)->decide(Approval::query()->findOrFail($approval), $finance, 'approved', null, $this->staffContextFor($finance, null, 'totp')))
        ->toThrow(DomainError::class, 'somebody other than the person who made it');

    $done = $this->postJson($url, $body + ['approval_ids' => [secondPersonApproves($approval)]])->assertOk()->assertJsonPath('outcome', 'cancelled')->json();
    app(OutboxPublisher::class)->relayPending();

    $reversal = PartnerCommission::query()->findOrFail($done['reversal']);
    expect($row->fresh())->state->toBe(PartnerCommission::CANCELLED)->amount_minor->toBe(200000)->cancelled_at->not->toBeNull()
        ->and($reversal)->kind->toBe('reversal')->cancels_id->toBe($row->id)->amount_minor->toBe(-200000)->base_minor->toBe(-1000000)->state->toBe(PartnerCommission::CANCELLED)->invoice_id->toBeNull()
        ->and(app(PartnerService::class)->balance($partner->fresh())['payable']->minor)->toBe(200000); // only the standing commission
    $audit = AuditEvent::query()->where('action', 'partner.commission.cancel_duplicate')->where('result', 'succeeded')->sole();
    expect($audit->approval_ids)->toBe([$approval])->and($audit->step_up_method)->toBe('totp')->and(json_encode($audit->detail))->toContain('I-R6: druh');
    expect(OutboxMessage::query()->where('name', 'partner.commission.duplicate_cancelled')->sole()->payload)->toMatchArray(['outcome' => 'cancelled'])
        ->and(Notification::query()->where('organization_id', $partnerOrg->id)->where('title', 'like', 'Zdvojená provize opravena%')->count())->toBe(1);

    // the staff partner detail no longer lists it as waiting
    $this->getJson("/v1/staff/partners/{$partner->id}")->assertOk()->assertJsonCount(0, 'data.duplicates');
});

it('refuses a second cancellation of the same row and anything that is not a set-aside duplicate', function () {
    [$partner, $client] = cduPartner($this);
    $row = cduSetAside($partner, $client);
    $duplicates = app(DuplicateCommissions::class);
    $refusal = function (Closure $call): ?string {
        try {
            $call();
        } catch (DomainError $e) {
            return $e->error;
        }

        return null;
    };

    expect($refusal(fn () => $duplicates->cancel($row->id)))->toBeNull()
        ->and($refusal(fn () => $duplicates->cancel($row->id)))->toBe('commission_already_cancelled')
        ->and(PartnerCommission::query()->where('cancels_id', $row->id)->count())->toBe(1);
    // the reversal of another row cannot claim the same commission: the database keeps one cancellation per commission
    expect(fn () => DB::transaction(fn () => PartnerCommission::query()->create(['partner_id' => $partner->id, 'organization_id' => $client->id, 'period' => '2026-10', 'kind' => 'reversal', 'base_minor' => -1, 'rate_pct' => 20, 'amount_minor' => -1, 'currency' => 'CZK', 'state' => 'cancelled', 'cancels_id' => $row->id])))
        ->toThrow(UniqueConstraintViolationException::class);

    $standing = PartnerCommission::query()->findOrFail($row->duplicate_of_id);
    expect($refusal(fn () => $duplicates->cancel($standing->id)))->toBe('commission_not_duplicate')
        ->and($refusal(fn () => $duplicates->cancel('pcm_nothing')))->toBe('not_found')
        ->and($standing->fresh()->state)->toBe('payable');
});

it('keeps a row already paid out as paid and puts its amount as a minus on the next payouts', function () {
    [$partner, $client, $partnerOrg] = cduPartner($this);
    $row = cduSetAside($partner, $client, PartnerCommission::PAID, 150000);

    $result = app(DuplicateCommissions::class)->cancel($row->id);
    app(OutboxPublisher::class)->relayPending();

    expect($result['outcome'])->toBe('deducted')->and($row->fresh()->state)->toBe(PartnerCommission::PAID)
        ->and(PartnerCommission::query()->findOrFail($result['reversal']))->state->toBe(PartnerCommission::PAYABLE)->amount_minor->toBe(-150000)->payable_at->not->toBeNull()
        ->and(app(PartnerService::class)->balance($partner->fresh())['payable']->minor)->toBe(150000 - 150000); // the standing commission minus the doubled payment
    expect(Notification::query()->where('organization_id', $partnerOrg->id)->where('title', 'like', 'Zdvojená provize opravena%')->sole()->body)->toContain('odečte od příští výplaty');
});

it('lists the rows waiting for finance on the staff partner detail and leaves them out of the tier volume', function () {
    [$partner, $client] = cduPartner($this);
    $row = cduSetAside($partner, $client);
    $this->actingAs($this->staff('billing_finance_admin'), 'sanctum');

    $this->getJson("/v1/staff/partners/{$partner->id}")->assertOk()->assertJsonPath('data.duplicates.0.id', $row->id)->assertJsonPath('data.duplicates.0.duplicate_of', $row->duplicate_of_id);
    PartnerCommission::query()->update(['invoice_paid_at' => now()->subMonth()->startOfMonth()->addDay()]);
    app(PartnerService::class)->recomputeTier($partner->fresh());
    expect($partner->fresh()->volume_3m_minor)->toBe(intdiv(1000000, 3)); // the standing commission's base once (a monthly average), not the double
});
