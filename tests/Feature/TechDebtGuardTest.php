<?php

declare(strict_types=1);

/**
 * Phase P: static guards against tech-debt classes that have bitten before.
 */

/** @return list<string> */
function bladeTemplates(): array
{
    $files = [];

    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));

    foreach ($it as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

// ── P196: @php(...) paren form vs @php…@endphp block ──────────────────────────

it('never mixes @php(...) paren form with an @php block in the same template', function (): void {
    /*
     | Blade compiles `@php…@endphp` with the regex /(?<!@)@php(.*?)@endphp/s.
     | If the SAME file also has a `@php(...)` one-liner earlier, that regex
     | swallows everything from the one-liner's `@php(` to the block's
     | `@endphp` — the rest of the view becomes raw PHP and the parse error
     | surfaces somewhere distant. This has bitten twice (services/show,
     | order-show). Pick one form per file; the block form is the safe default.
     */
    $offenders = [];

    foreach (bladeTemplates() as $path) {
        $source = (string) file_get_contents($path);

        // Laravel's own raw-block regex. Only a match whose OPENER is a paren
        // form is the bug: it means a `@php(...)` one-liner started a raw block
        // and swallowed everything up to a later `@endphp`. Block-then-paren is
        // fine — the block's own @endphp closes it and later paren forms stand
        // alone.
        if (preg_match_all('/(?<!@)@php(.*?)@endphp/s', $source, $matches) > 0) {
            foreach ($matches[1] as $captured) {
                if (str_starts_with(ltrim($captured), '(')) {
                    $offenders[] = str_replace(resource_path('views') . DIRECTORY_SEPARATOR, '', $path);
                    break;
                }
            }
        }
    }

    expect($offenders)->toBe(
        [],
        "Templates mixing @php(...) and @php…@endphp:\n" . implode("\n", $offenders),
    );
});

// ── P195: no reference to a non-existent enum case ────────────────────────────

it('references only enum cases that actually exist', function (): void {
    /*
     | The bug this guards: the pay button checked
     | `$invoice->status->value === 'unpaid'`, but InvoiceStatus never had an
     | `unpaid` case — so the button never showed. A string compared against an
     | enum that lacks the value fails silently. Assert every `Enum::Case`
     | reference resolves.
     */
    $enums = [
        \App\Domains\Billing\Enums\OrderStatus::class,
        \App\Domains\Billing\Enums\InvoiceStatus::class,
        \App\Domains\Provisioning\Enums\ServiceStatus::class,
    ];

    $unknown = [];

    $phpFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path()));

    foreach ($phpFiles as $file) {
        if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
            continue;
        }

        $source = (string) file_get_contents($file->getPathname());

        foreach ($enums as $enum) {
            $short = class_basename($enum);
            $valid = array_map(static fn ($c): string => $c->name, $enum::cases());

            if (preg_match_all('/\b' . $short . '::([A-Z][A-Za-z]+)\b/', $source, $m) > 0) {
                foreach ($m[1] as $case) {
                    // Skip method/const names that look like a case but aren't.
                    if (! in_array($case, $valid, true) && ! in_array($case, ['class', 'cases'], true)) {
                        $unknown[] = basename($file->getPathname()) . ": {$short}::{$case}";
                    }
                }
            }
        }
    }

    expect(array_unique($unknown))->toBe([], 'Neexistující enum case: ' . implode(', ', array_unique($unknown)));
});

it('marks the right order states as terminal', function (): void {
    // Expired must behave like the other end states — no further transition.
    expect(\App\Domains\Billing\Enums\OrderStatus::Expired->isTerminal())->toBeTrue()
        ->and(\App\Domains\Billing\Enums\OrderStatus::Pending->isTerminal())->toBeFalse()
        ->and(\App\Domains\Billing\Enums\OrderStatus::Processing->isTerminal())->toBeFalse();
});

// ── P199: migration column verifier runs in CI ────────────────────────────────

it('has no migration referencing a column that does not exist yet', function (): void {
    /*
     | tools/verify-migration-columns.php replays every migration's up() and
     | flags an ->after()/->dropColumn()/->renameColumn() that names a column
     | absent at that point. This class of bug is invisible to the SQLite test
     | DB (its grammar ignores ->after()), so it only shows up on a real MySQL
     | deploy — exactly the "works locally, breaks on prod" trap. Running the
     | tool here closes that gap.
     */
    $tool = base_path('tools/verify-migration-columns.php');

    expect(file_exists($tool))->toBeTrue();

    exec('php ' . escapeshellarg($tool) . ' 2>&1', $output, $exit);

    expect($exit)->toBe(0, implode("\n", $output));
})->skip(PHP_OS_FAMILY === 'Windows', 'shells out to php; runs on Linux CI');
