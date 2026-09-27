<?php

declare(strict_types=1);

use Onhost\Domain\Identity\Authorization\RoleCatalog;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/*
 * Red-team round on the integrated Phase-0 chain (TASK-0035 finding, permission program IF-17 / audit TD-11): the projects page
 * of the customer panel still treated a person whose role the server did not report as a manager (`!u.member_role ||` in
 * canManage) — the fail-open default the team page lost in TASK-0035. And the catalogue still called the partner preset
 * "Partner / Reseller" while the panel (rightly) says commission only.
 */

const PROJECTS_UI_JS = 'apps/surfaces/api/onhost-panel-projects.api.js';

/** Renders the project list of the real seam file in node for a person with `$role` (or none); returns whether the create form shows. */
function projectsUiShowsCreateForm(?string $role): bool
{
    $node = (new ExecutableFinder)->find('node');
    if ($node === null) {
        test()->markTestSkipped('node is not installed; the static check below still runs');
    }
    $harness = <<<'JS'
const fs = require('fs');
const [file, role] = process.argv.slice(2);
global.window = {
  ONHOST: { user: { id: 'u-1', email: 'me@x.cz', member_role: role === '-' ? undefined : role, organization: { id: 'org-1' } } },
  OnhostApi: { get: () => new Promise(() => {}), post: () => new Promise(() => {}), key: () => 'k' }
};
eval(fs.readFileSync(file, 'utf8'));
const cmp = { state: { lang: 'cs', projSel: null }, setState(o) { Object.assign(this.state, o); }, flash() {} };
const H = { stat: () => ({}), pill: () => '', bar: () => '', dot: () => '', rowStyle: '', match: () => true };
const view = window.OnhostPanelProjects.view(cmp, (cs) => cs, H);
process.stdout.write(JSON.stringify({ form: !!view.form }));
JS;
    $script = tempnam(sys_get_temp_dir(), 'projects-ui').'.js';
    file_put_contents($script, $harness);
    try {
        $process = new Process([$node, $script, base_path(PROJECTS_UI_JS), $role ?? '-']);
        $process->mustRun();

        return (bool) json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR)['form'];
    } finally {
        @unlink($script);
    }
}

it('shows the project controls only to a role that manages them, and to nobody whose role is unknown', function () {
    expect(projectsUiShowsCreateForm(null))->toBeFalse()
        ->and(projectsUiShowsCreateForm('viewer'))->toBeFalse()
        ->and(projectsUiShowsCreateForm('owner'))->toBeTrue()
        ->and(projectsUiShowsCreateForm('org_admin'))->toBeTrue();

    expect((string) file_get_contents(base_path(PROJECTS_UI_JS)))->not->toContain('!u.member_role ||'); // and without node, the same fact in the source
});

it('names the partner preset in the catalogue as the panel does: commission only', function () {
    $partner = RoleCatalog::all()['partner'];
    expect($partner['name'])->toBe('Partner (commission only)')
        ->and($partner['name'].' '.$partner['description'])->not->toContain('Reseller')->not->toContain('Sub-customers')->not->toContain('white-label');
});
