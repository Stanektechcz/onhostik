<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Onhost\Domain\Identity\Authorization\RoleCatalog;

/*
 * F12a (TASK-0106): follow-ups of the identity and API audit.
 *
 * 1. A test that signs in a member of staff names a role RoleCatalog knows. A made-up key binds no permission at all, so since
 *    D2 the staff guard refuses that person before the permission the test was written for is ever asked — the 403 proved the
 *    guard, not the permission (three tests did it with a `support_agent` that never existed).
 */

it('signs staff in only with role keys RoleCatalog knows', function () {
    $pattern = '/(?<![A-Za-z0-9_])(?:staff|steppedUpStaff)\(\s*[\'"]([^\'"]+)[\'"]/';
    $calls = 0;
    $unknown = [];
    foreach (File::allFiles(base_path('tests')) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        foreach (preg_split('/\R/', $file->getContents()) ?: [] as $index => $line) {
            if (preg_match_all($pattern, $line, $matches) === 0) {
                continue;
            }
            foreach ($matches[1] as $role) {
                $calls++;
                if (! RoleCatalog::exists($role)) {
                    $unknown[] = str_replace('\\', '/', $file->getRelativePathname()).':'.($index + 1)." {$role}";
                }
            }
        }
    }

    expect($calls)->toBeGreaterThan(100) // the scan itself still finds the helper calls: a renamed helper must not make this pass silently
        ->and($unknown)->toBe([]);
});
