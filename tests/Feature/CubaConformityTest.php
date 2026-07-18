<?php

declare(strict_types=1);

/**
 * Cuba conformity guard.
 *
 * This Cuba build is the TAILWIND port: Bootstrap utility classes
 * (d-flex, align-items-*, justify-content-*, col-md-*, row, text-end,
 * w-100, fw-bold, rounded-pill …) DO NOT EXIST in its style.css, so using
 * them silently produces no-op / broken layout. This test scans every
 * Cuba-layout view and fails if any forbidden class reappears — keeping the
 * whole panel/admin UI 1:1 with the Cuba Tailwind template.
 *
 * Antler/Bootstrap FRONT views (layouts.front / layouts.auth) legitimately
 * use these classes and are excluded.
 */

/** @return list<string> Cuba-rendered blade files (absolute paths). */
function cubaBladeFiles(): array
{
    $root = base_path('resources/views');
    /** @var list<string> $files */
    $files = [];

    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        /** @var SplFileInfo $file */
        if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        $path = str_replace('\\', '/', $file->getPathname());

        // Skip the Antler / Bootstrap-based front + auth + email/pdf views.
        if (preg_match('#/views/front/|/components/front/|/layouts/front|/layouts/auth|/views/emails/|/views/pdf/|/views/vendor/#', $path)) {
            continue;
        }

        $contents = (string) file_get_contents($path);

        // Only files rendered inside a Cuba layout (or Cuba components/partials).
        $isCuba = str_contains($contents, "@extends('layouts.panel')")
            || str_contains($contents, "@extends('layouts.cuba-standalone')")
            || str_contains($path, '/components/panel/')
            || str_contains($path, '/partials/')
            || str_contains($path, '/layouts/panel.blade.php');

        if ($isCuba) {
            $files[] = $path;
        }
    }

    return $files;
}

it('has no forbidden Bootstrap utility classes in Cuba views', function (): void {
    // token => human hint of the Tailwind replacement
    $forbidden = [
        'd-flex'                 => 'flex',
        'd-inline-flex'          => 'inline-flex',
        'd-block'                => 'block',
        'd-none'                 => 'hidden',
        'align-items-center'     => 'items-center',
        'align-items-start'      => 'items-start',
        'align-items-end'        => 'items-end',
        'justify-content-between' => 'justify-between',
        'justify-content-center' => 'justify-center',
        'justify-content-end'    => 'justify-end',
        'flex-column'            => 'flex-col',
        'text-end'               => 'text-right',
        'float-end'              => 'float-right',
        'w-100'                  => 'w-full',
        'h-100'                  => 'h-full',
        'fw-bold'                => 'font-bold',
        'fw-semibold'            => 'font-semibold',
        'text-truncate'          => 'truncate',
        'rounded-pill'           => 'rounded-full',
        'rounded-circle'         => 'rounded-full',
        'position-relative'      => 'relative',
        'text-uppercase'         => 'uppercase',
        'vh-100'                 => 'h-screen',
    ];

    $violations = [];

    foreach (cubaBladeFiles() as $path) {
        $contents = (string) file_get_contents($path);
        $rel      = str_replace(str_replace('\\', '/', base_path()) . '/', '', $path);

        foreach ($forbidden as $class => $hint) {
            if (preg_match('/\b' . preg_quote($class, '/') . '\b/', $contents)) {
                $violations[] = "{$rel}: '{$class}' → use '{$hint}'";
            }
        }

        // Bootstrap grid
        foreach (['col-sm-', 'col-md-', 'col-lg-', 'col-xl-'] as $g) {
            if (preg_match('/\b' . preg_quote($g, '/') . '\d/', $contents)) {
                $violations[] = "{$rel}: '{$g}N' → use 'col-span-12 {sm,md,lg,xl}:col-span-N'";
            }
        }

        // Standalone Bootstrap `row` class
        if (preg_match('/class="[^"]*(?:^|[" ])row(?:[" ]|$)/', $contents)) {
            $violations[] = "{$rel}: standalone 'row' → use 'grid grid-cols-12'";
        }
    }

    expect($violations)->toBe([], "Forbidden Bootstrap classes found:\n" . implode("\n", $violations));
});
