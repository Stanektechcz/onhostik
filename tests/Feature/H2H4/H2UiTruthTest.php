<?php

declare(strict_types=1);

use Onhost\Domain\Services\DeletionPolicy;

/*
 * Phase H, package H2 (TASK-0124): the last window.prompt calls, the seam applied to the template only, and the prototype sentences
 * that stated what the platform does not do (a 30-day archive, a top-up bonus). The prototypes stay byte-identical: every
 * correction is a needle in resources/surfaces/credit-claims.php, pinned against the prototype by G8UiFollowUpsTest.
 */

/** @return list<string> every file a browser can be served that may hold script */
function h2ServedScriptFiles(): array
{
    $files = [];
    $roots = [base_path('resources/views'), base_path('apps/surfaces'), base_path('public')];
    foreach ($roots as $root) {
        if (! is_dir($root)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if (preg_match('~\.(blade\.php|js|mjs|html)$~', $path) && ! str_contains($path, '/node_modules/') && ! str_contains($path, '/public/build/')) {
                $files[] = $path;
            }
        }
    }

    return $files;
}

it('serves no window.prompt call anywhere but the dialog\'s own no-DOM fallback', function () {
    $callers = [];
    foreach (h2ServedScriptFiles() as $path) {
        $source = (string) file_get_contents($path);
        $count = preg_match_all('~\bwindow\.prompt\s*\(~', $source);
        if ($count > 0) {
            $callers[ltrim(str_replace(str_replace('\\', '/', base_path()), '', $path), '/')] = $count;
        }
    }

    // one place may still ask the browser: OnhostDialog.form when there is no document (a test harness); everything else is a real dialog
    expect($callers)->toBe(['apps/surfaces/api/onhost-session-bridge.js' => 1]);
});

it('gives every staff Blade page the shared dialog, loaded without touching the shell session', function () {
    foreach (['admin/approvals', 'admin/integrations', 'admin/lifecycle', 'admin/operations', 'admin/plans', 'service-console'] as $view) {
        $source = (string) file_get_contents(resource_path('views/'.$view.'.blade.php'));
        expect($source)->toContain("@include('partials.dialog-bridge'")->toContain('window.OnhostDialog.');
    }

    $partial = view('partials.dialog-bridge')->render();
    expect($partial)->toContain('dialogOnly: true')->toContain('/surfaces/api/onhost-session-bridge.js?v=');

    // the bridge defines the dialog and the wording helpers first and stops there for such a page: it must not clear the session or the role
    $bridge = (string) file_get_contents(base_path('apps/surfaces/api/onhost-session-bridge.js'));
    $stop = strpos($bridge, 'if (B.dialogOnly) return;');
    expect($stop)->toBeInt()
        ->and(strpos($bridge, 'window.OnhostDialog = '))->toBeLessThan($stop)
        ->and(strpos($bridge, 'window.OnhostI18n = '))->toBeLessThan($stop)
        ->and(strpos($bridge, 'write(K.session'))->toBeGreaterThan($stop)
        ->and(strpos($bridge, 'window.OnhostApi = '))->toBeGreaterThan($stop);
});

it('serves the bridge to the Blade pages from the same route as to the surfaces', function () {
    $this->get('/surfaces/api/onhost-session-bridge.js')->assertOk();
});

it('corrects the admin demo cards that promised a top-up bonus nobody gives', function () {
    $admin = (string) $this->actingAs($this->staff())->get('/sprava')->assertOk()->getContent();

    foreach (['bonus 10 %', '10% bonus', 'Bonus 10 %', 'bonus 10 %'] as $claim) {
        // the template says it nowhere; (the only "10 %" left in the page are unrelated sentences, not a bonus)
        expect($admin)->not->toContain($claim);
    }
    expect($admin)->toContain('no bonus, the same terms as for end customers');
});

it('says the archive of a removed service is kept for the policy\'s days, not the prototype\'s 30', function () {
    $days = app(DeletionPolicy::class)->retentionDays();
    expect($days)->toBe(60);

    [$customer] = $this->customerWithOrganization();
    $panel = (string) $this->actingAs($customer)->get('/panel')->assertOk()->getContent();
    expect($panel)->toContain("v archivu ke stažení {$days} dní")
        ->not->toContain('ke stažení 30 dní po zrušení')
        ->not->toContain('{retention_days}');

    $admin = (string) $this->actingAs($this->staff())->get('/sprava')->assertOk()->getContent();
    expect($admin)->toContain("ke sta\u{17E}en\u{00ED} {$days} dn\u{00ED}")->not->toContain('{retention_days}');
});

it('applies the claims seam to the template, never to the boot JSON that carries a customer\'s own text', function () {
    // a customer who calls their organization like a prototype sentence must find the name unchanged in the boot object
    [$customer] = $this->customerWithOrganization([], ['name' => 'refund unused days within ten days']);
    $panel = (string) $this->actingAs($customer)->get('/panel')->assertOk()->getContent();

    expect($panel)->toContain('"name":"refund unused days within ten days"')
        ->and($panel)->not->toContain('refund unused days within ten days.'); // the prototype sentence itself is corrected
    expect(substr_count($panel, 'refund unused days within ten days'))->toBeGreaterThan(0);
});

it('tells a person before the click that ending a session renews the remember-me token and signs remembered browsers out too', function () {
    $source = (string) file_get_contents(base_path('apps/surfaces/api/onhost-panel-account.api.js'));

    expect(substr_count($source, 'REMEMBER_CS'))->toBeGreaterThanOrEqual(3) // defined once, used by both confirmations
        ->and($source)->toContain('REMEMBER_EN')->toContain('zapamatované prohlížeče se přihlásí znovu');
});
