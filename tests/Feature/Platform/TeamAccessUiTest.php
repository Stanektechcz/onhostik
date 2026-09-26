<?php

declare(strict_types=1);

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/*
 * TASK-0035 (permission program IF-17 / P0-06, audit TD-11): the team page of the customer panel. The admin controls were
 * shown to anybody whose role the server did not report (`member_role || 'owner'` — a missing role was an owner), a role was
 * changed by typing its key into window.prompt (a typo became a role key the server had to refuse), "remove" was one word in
 * that prompt with no question asked, and the list offered nine of the fourteen presets. The partner preset reads as what it
 * is: commission only, no reseller powers.
 */

const TEAM_UI_JS = 'apps/surfaces/api/onhost-panel-account.api.js';

/** Runs the team view of the real seam file in node with a stubbed panel scope; returns what the script reports. */
function teamUiRun(string $scenario): array
{
    $node = (new ExecutableFinder)->find('node');
    if ($node === null) {
        test()->markTestSkipped('node is not installed; the static checks below still run');
    }
    $harness = <<<'JS'
const fs = require('fs');
const [file, scenario] = process.argv.slice(2);
const calls = [], prompts = [];
let confirmAnswer = false;
const org = { members: [
  { user_id: 'u-owner', name: 'Owner', email: 'owner@x.cz', role: 'owner', state: 'active' },
  { user_id: 'u-dev', name: 'Dev', email: 'dev@x.cz', role: 'developer', state: 'active' }
], invitations: [] };
const role = { missing: undefined, owner: 'owner', viewer: 'viewer' }[scenario.split(':')[0]];
global.window = {
  ONHOST: { user: { id: scenario.startsWith('owner') ? 'u-owner' : 'u-other', email: 'me@x.cz', member_role: role, organization: { id: 'org-1' } } },
  prompt: (q) => { prompts.push(q); return 'remove'; },
  confirm: (q) => { calls.push(['confirm', q]); return confirmAnswer; },
  OnhostApi: {
    get: () => Promise.resolve({ data: org }),
    post: (p, b) => { calls.push(['POST', p, b]); return Promise.resolve({}); },
    patch: (p, b) => { calls.push(['PATCH', p, b]); return Promise.resolve({}); },
    del: (p) => { calls.push(['DELETE', p]); return Promise.resolve({}); },
    key: () => 'k'
  }
};
global.navigator = { userAgent: 'node' };
eval(fs.readFileSync(file, 'utf8'));
const cmp = { state: { lang: 'cs' }, setState(o) { Object.assign(this.state, o); }, flash() {} };
const _ = (cs) => cs;
const H = { stat: () => ({}), pill: () => '', bar: () => '', dot: () => '', rowStyle: '', match: () => true };
const acct = window.OnhostPanelAccount;
const tick = () => new Promise((r) => setTimeout(r, 0));
(async () => {
  acct.teamView(cmp, _, H); await tick();
  let view = acct.teamView(cmp, _, H);
  const out = { inviteForm: !!(view.form && /Pozvat/.test(view.form.title)), inviteOptions: view.form ? view.form.fields[1].options : [], rowActions: view.rows.map((r) => r.action), sideRoles: view.side.rows.map((r) => r.meta), projectRoles: acct.roles.map((r) => r[0]) };
  const dev = view.rows.find((r) => r.name.indexOf('Dev') === 0);
  if (scenario.startsWith('owner') && dev) {
    dev.onAction();
    view = acct.teamView(cmp, _, H);
    out.editTitle = view.form && view.form.title;
    out.editOptions = (view.form && view.form.fields[0].options) || [];
    const removal = out.editOptions[out.editOptions.length - 1];
    if (!out.editOptions.length) { out.calls = calls; out.prompts = prompts; process.stdout.write(JSON.stringify(out)); return; }
    if (scenario === 'owner:remove-declined') { cmp.state.teamRole = removal; confirmAnswer = false; view.form.submit(); }
    if (scenario === 'owner:remove-confirmed') { cmp.state.teamRole = removal; confirmAnswer = true; view.form.submit(); }
    if (scenario === 'owner:change') { cmp.state.teamRole = 'partner (jen provize)'; view.form.submit(); }
    await tick();
  }
  out.calls = calls; out.prompts = prompts;
  process.stdout.write(JSON.stringify(out));
})();
JS;
    $script = tempnam(sys_get_temp_dir(), 'team-ui').'.js';
    file_put_contents($script, $harness);
    try {
        $process = new Process([$node, $script, base_path(TEAM_UI_JS), $scenario]);
        $process->mustRun();

        return (array) json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    } finally {
        @unlink($script);
    }
}

it('hides every admin control when the server reports no role for the person', function () {
    foreach (['missing', 'viewer'] as $scenario) {
        $out = teamUiRun($scenario);
        expect($out['inviteForm'])->toBeFalse($scenario)
            ->and(array_filter($out['rowActions']))->toBe([], $scenario);
    }
    expect(teamUiRun('owner')['inviteForm'])->toBeTrue();
});

it('changes a role through a select of every preset and removes a member only after a confirmation', function () {
    $out = teamUiRun('owner:change');
    expect($out['editTitle'])->toContain('dev@x.cz')
        ->and($out['editOptions'])->toContain('administrátor', 'fakturace', 'správce pošty', 'bezpečnostní auditor', 'kontakt pro podporu', 'jen čtení', 'partner (jen provize)')
        ->and($out['editOptions'])->not->toContain('vlastník') // ownership moves only by a transfer
        ->and($out['prompts'])->toBe([])
        ->and($out['calls'])->toContain(['PATCH', '/organizations/org-1/members/u-dev', ['role' => 'partner']]);

    $declined = teamUiRun('owner:remove-declined');
    expect(collect($declined['calls'])->pluck(0)->all())->toBe(['confirm'])->and($declined['prompts'])->toBe([]);
    $confirmed = teamUiRun('owner:remove-confirmed');
    expect(collect($confirmed['calls'])->pluck(0)->all())->toBe(['confirm', 'DELETE'])
        ->and($confirmed['calls'][1][1])->toBe('/organizations/org-1/members/u-dev')
        ->and($confirmed['calls'][0][1])->toContain('dev@x.cz');
});

it('lists every customer preset, names the partner role for what it is, and offers projects only project roles', function () {
    $out = teamUiRun('owner');
    expect($out['sideRoles'])->toBe(['owner', 'org_admin', 'billing_admin', 'domain_manager', 'dns_manager', 'developer', 'cloud_operator', 'game_operator', 'mail_manager', 'security_auditor', 'support_contact', 'viewer', 'guest', 'partner'])
        ->and($out['inviteOptions'])->toContain('partner (jen provize)')->not->toContain('vlastník')
        ->and($out['projectRoles'])->not->toContain('guest')->not->toContain('partner');

    // and without node, the same facts in the source: no prompt, no fail-open default
    $js = (string) file_get_contents(base_path(TEAM_UI_JS));
    expect($js)->not->toContain("member_role || 'owner'")->not->toContain('window.prompt(_(\'Nová role')->toContain("['partner', 'partner (jen provize)', 'partner (commission only)']");
});
