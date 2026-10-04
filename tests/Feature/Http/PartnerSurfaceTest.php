<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Partners\Models\Partner;
use Onhost\Domain\Partners\PartnerService;
use Onhost\Platform\Commands\CommandContext;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/*
 * The partner portal's marketplace tab (audit §5k-1, seam #46): outside demo mode the prototype gains one API-backed tab —
 * the tab table, the view flag, the page title and the markup are injected by the renderer; the module is preloaded.
 *
 * Audit A3 (P0-2): /partner had no partner gate and the prototype's narrated partner ("Atelier Šindelář · ID 4821", the client
 * "Bezvazásilky s.r.o.", the link ?ref=SINDELAR4821, tiers and payouts) showed while loading, on an API error and to any signed-in
 * person. Now only the people who run an active partnership open the portal, the narrated partner never reaches the page, and
 * the module answers with loading, empty and error states.
 */

/** The prototype's narrated partner: none of it may reach a page outside demo mode. */
function pgateNarrated(): array
{
    return ['Šindelář', 'Bezvazásilky', 'SINDELAR4821', 'ID 4821', 'verify=4821', 'Výplata PO-2026-08', "['PO-2026-08',", 'Kavárna Zrno', 'trh má 7 %', '+ 8,4 %'];
}

/** Enrols and approves the organization as a partner (the state the portal is for). */
function pgateEnrol(Organization $organization): Partner
{
    $partners = app(PartnerService::class);

    return $partners->approve($partners->apply($organization, ['model' => 'share'], CommandContext::system('test')), CommandContext::system('test'));
}

function pgateMember(Organization $organization, User $user, string $role): User
{
    app(OrganizationService::class)->attachMember($organization, $user, $role, CommandContext::system('test'), true);

    return $user;
}

it('serves the partner surface with the marketplace tab seams and the partner module', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['name' => 'Agentura Pixel s.r.o.']);
    pgateEnrol($org);
    $this->actingAs($owner, 'sanctum');
    $html = $this->get('/partner')->assertOk()->getContent();
    expect($html)->toContain('onhost-partner.api.js')->toContain("marketplace: 'marketplace'")->toContain("['marketplace', 'Marketplace'")
        ->toContain("isMarketplace: s.tab === 'marketplace'")->toContain('<sc-if value="{{ isMarketplace }}"')->toContain('{{ mkt.listings }}')->toContain('{{ mkt.orders }}')
        ->toContain('window.OnhostPartner.page(this)')->toContain('if (this.__onhostMounted) this.syncHash();')->toContain('this.__onhostMounted = true;'); // deep links survive the first render
    // the prototype's own tabs are untouched
    expect($html)->toContain("['assets', _('Materiály', 'Materials'), '']")->toContain('<sc-if value="{{ isPayouts }}"');
    expect((string) file_get_contents(base_path('apps/surfaces/api/onhost-partner.api.js')))->toContain('window.OnhostPartner = {')->toContain('/partner/marketplace/listings')->toContain('/partner/marketplace/orders');
});

it('serves the portal to the people who run an active partnership, without the narrated partner', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['name' => 'Agentura Pixel s.r.o.']);
    pgateEnrol($org);
    Log::spy();

    foreach ([$owner, pgateMember($org, $this->customer(), 'partner'), pgateMember($org, $this->customer(), 'billing_admin')] as $user) {
        $this->actingAs($user, 'sanctum');
        $html = $this->get('/partner/klienti')->assertOk()->getContent();
        foreach (pgateNarrated() as $literal) {
            expect($html)->not->toContain($literal);
        }
        expect($html)->toContain('"partner":{"code":')->toContain('"hash":"#/klienti"');
    }

    // the page carries the empty, loading and error hooks in place of the prototype's data
    expect($html)->toContain("  CLIENTS(cs) {\n    return [];")->toContain("    const feed = (window.OnhostPartner && window.OnhostPartner.feed(this)) || [\n    ];")
        ->toContain("company: window.OnhostPartner ? window.OnhostPartner.company(this) : '',")
        ->toContain("window.OnhostPartner.emptyText(this, 'clients')")->toContain("window.OnhostPartner.statusText(this, 'overview')")
        ->toContain("window.OnhostPartner.tiers(this)) || [[0, '—', 0]];")->toContain('const rate = liveRate != null ? liveRate : 0;')
        ->toContain("window.OnhostPartner.refBase(this)) || '';")->toContain("window.OnhostPartner.wlRecord(this, domOk ? dom : 'panel.vasefirma.cz')) || '',")
        ->toContain("if (p[4] === 'error') { if (window.OnhostPartner) window.OnhostPartner.reload(this); return; }")
        ->toContain('const balanceNum = live ? live.earned : 0;')->toContain("(cs ? 'Vaše firma' : 'Your company')");
    // every anchor of the literal removal and of seams #46/#47 still matches the pristine prototype
    Log::shouldNotHaveReceived('warning', fn (string $message) => str_contains($message, 'anchor mismatch'));
});

it('sends a signed-in person without a partnership to the programme and its application form', function () {
    // a customer whose organization is not enrolled
    [$customer] = $this->customerWithOrganization();
    $this->actingAs($customer, 'sanctum');
    $this->get('/partner')->assertRedirect('/reseller');
    $this->get('/partner/vyplaty')->assertRedirect('/reseller');

    // enrolled but not approved yet
    [$applicant, $applied] = $this->customerWithOrganization([], ['name' => 'Čekatel s.r.o.']);
    app(PartnerService::class)->apply($applied, ['model' => 'share'], CommandContext::system('test'));
    expect(Partner::query()->where('organization_id', $applied->id)->value('state'))->not->toBe('active');
    $this->actingAs($applicant, 'sanctum');
    $this->get('/partner')->assertRedirect('/reseller');

    // a member of an active partner who may not read its portal (no partner.portal.read)
    [, $org] = $this->customerWithOrganization([], ['name' => 'Druhý partner s.r.o.']);
    pgateEnrol($org);
    foreach (['viewer', 'developer', 'support_contact'] as $role) {
        $this->actingAs(pgateMember($org, $this->customer(), $role), 'sanctum');
        $this->get('/partner')->assertRedirect('/reseller');
    }

    // staff are not partners; a person without any organization neither
    $this->actingAs($this->staff('support_manager'), 'sanctum');
    $this->get('/partner')->assertRedirect('/reseller');
    $this->actingAs($this->customer(), 'sanctum');
    $this->get('/partner')->assertRedirect('/reseller');
});

it('asks a guest to sign in and keeps the prototype for demo mode', function () {
    $this->get('/partner/provize')->assertRedirect('/prihlaseni?next='.urlencode('/partner/provize'));

    config(['onhost.ui.demo' => true]);
    $html = $this->get('/partner')->assertOk()->getContent();
    expect($html)->toContain("_('Atelier Šindelář · ID 4821', 'Atelier Šindelář · ID 4821')")->toContain('Bezvazásilky s.r.o.')->not->toContain('onhost-partner.api.js');
});

it('shows loading, error and live states from the partner module', function () {
    $node = (new ExecutableFinder)->find('node');
    if ($node === null) {
        $this->markTestSkipped('node is not installed');
    }
    $harness = sys_get_temp_dir().'/onhost-partner-states-'.bin2hex(random_bytes(4)).'.cjs';
    file_put_contents($harness, <<<'JS'
const vm = require('vm'), fs = require('fs');
const src = fs.readFileSync(process.argv[2], 'utf8');
const tick = () => new Promise((r) => setImmediate(r));
async function run(answer) {
  const box = { console, setTimeout, location: { origin: 'https://onhost.test' } };
  box.window = box;
  box.ONHOST = { user: { organization: { name: 'Agentura Pixel s.r.o.' } } };
  box.OnhostApi = { get: (path) => answer(path), key: () => 'k' };
  vm.createContext(box);
  vm.runInContext(src, box);
  const P = box.OnhostPartner;
  const cmp = { state: { lang: 'cs' }, forceUpdate() {}, flash() {}, setState() {}, mny: (n) => n + ' Kč' };
  const read = () => (P.sync(cmp), {
    company: P.company(cmp), feed: P.feed(cmp), kpis: P.kpis(cmp, 0, 0, 1), comm: P.commRows(cmp), pay: P.payRows(cmp, 0, 'x'),
    clients: P.clients(cmp), clientsEmpty: P.emptyText(cmp, 'clients'), badge: P.clientBadge(cmp), live: P.payoutLive(cmp), files: P.files(cmp),
    tiers: P.tiers(cmp), rate: P.rate(cmp), ref: P.refBase(cmp), refText: P.statusText(cmp, 'overview'), wl: P.wlRecord(cmp, 'panel.example.cz'),
  });
  const loading = read();
  for (let i = 0; i < 5; i++) await tick();
  return { loading, after: read() };
}
(async () => {
  const failed = await run(() => Promise.reject(new Error('HTTP 500')));
  const overview = { partner: { code: 'PIXEL7', rate: 18, model: 'share' }, kpis: { volume: { minor: 129000 }, commission_monthly: { minor: 23220 }, active_clients: 1, clients: 1, churn: 0 }, feed: [], tier: { table: [{ name: 'bronze', threshold_minor: 0, rate: 15 }] } };
  const data = { '/partner/overview': overview, '/partner/clients': [{ id: 'o1', name: 'Demo s.r.o.', mrr: { minor: 129000 }, services: [] }], '/partner/commissions': { months: [], balance: { payable: { minor: 0, currency: 'CZK' }, held: { minor: 0 } }, rules: { rate: 18 } }, '/partner/payouts': { payouts: [] }, '/partner/assets': [], '/partner/whitelabel': { cname_target: 'wl.onhost.test' } };
  const live = await run((path) => Promise.resolve({ data: data[path] !== undefined ? data[path] : {} }));
  process.stdout.write(JSON.stringify({ failed, live }));
})().catch((e) => { console.error(e); process.exit(1); });
JS);
    $process = new Process([$node, $harness, base_path('apps/surfaces/api/onhost-partner.api.js')], base_path(), null, null, 60);
    $process->run();
    @unlink($harness);
    expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
    $out = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);

    // while loading: nothing narrated, every block says it is loading
    $loading = $out['failed']['loading'];
    expect($loading['company'])->toBe('Načítám data partnera…')->and($loading['feed'][0]['text'])->toBe('Načítám data partnera…')
        ->and($loading['kpis'])->toHaveCount(4)->and($loading['kpis'][0]['value'])->toBe('—')->and($loading['comm'][0]['state'])->toBe('načítám')
        ->and($loading['pay'])->toBe([])->and($loading['clients'])->toBe([])->and($loading['clientsEmpty'])->toBe('Načítám data partnera…')->and($loading['badge'])->toBe('')
        ->and($loading['live'])->toBe(['docs' => 0, 'earned' => 0, 'held' => 0, 'paidBase' => 0, 'heldBase' => 0])->and($loading['files'])->toBe([])
        ->and($loading['tiers'])->toBeNull()->and($loading['rate'])->toBeNull()->and($loading['ref'])->toBeNull()->and($loading['wl'])->toBeNull();

    // the API failed: error rows name the failed read, nothing falls back to the prototype
    $failed = $out['failed']['after'];
    $error = 'Data partnera se nepodařilo načíst (HTTP 500) — zkuste to znovu.';
    expect($failed['company'])->toBe($error)->and($failed['feed'])->toHaveCount(1)->and($failed['feed'][0]['text'])->toBe($error)->and($failed['feed'][0]['tag'])->toBe('chyba')
        ->and($failed['kpis'][2]['delta'])->toBe($error)->and($failed['comm'][0]['month'])->toBe($error)->and($failed['comm'][0]['state'])->toBe('chyba')
        ->and($failed['pay'])->toBe([['chyba', '—', 0, $error, 'error']])->and($failed['clientsEmpty'])->toBe($error)->and($failed['clients'])->toBe([])
        ->and($failed['refText'])->toBe($error);
    expect(json_encode($out, JSON_UNESCAPED_UNICODE))->not->toContain('Šindelář')->not->toContain('Bezvazásilky')->not->toContain('SINDELAR4821');

    // the API answered: the partner's own data
    $live = $out['live']['after'];
    expect($live['company'])->toBe('Agentura Pixel s.r.o. · PIXEL7')->and($live['ref'])->toBe('https://onhost.test/?ref=PIXEL7')->and($live['refText'])->toBeNull()
        ->and($live['clients'][0]['name'])->toBe('Demo s.r.o.')->and($live['clientsEmpty'])->toBeNull()->and($live['badge'])->toBe('1')->and($live['rate'])->toBe(0.18)
        ->and($live['tiers'])->toBe([[0, 'Bronz', 15]])->and($live['wl'])->toBe('panel.example.cz.   300  IN  CNAME  wl.onhost.test.')
        ->and($live['feed'][0]['tag'])->toBe('start')->and($live['pay'])->toBe([]);
});
