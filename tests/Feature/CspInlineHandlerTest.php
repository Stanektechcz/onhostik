<?php

declare(strict_types=1);

/**
 * Audit H119 (extended): no Blade template may carry an inline event handler.
 *
 * script-src ships a nonce and deliberately omits 'unsafe-inline'. A nonce
 * whitelists <script nonce="…"> BLOCKS — it does not whitelist inline event
 * ATTRIBUTES. So under SECURITY_CSP_ENFORCE=true an onclick="…" never fires,
 * and it fails silently: no error, no visible breakage, just a button that
 * does nothing. The first sweep caught the confirm() handlers; this test
 * exists so the rest cannot come back.
 *
 * Behaviour is declared with data-* attributes and bound in the nonced
 * dispatcher at the bottom of layouts/panel.blade.php.
 */
it('has no inline event handlers in any Blade template', function (): void {
    $handlers = [
        'onclick', 'onchange', 'onsubmit', 'oninput', 'onkeyup', 'onkeydown',
        'onerror', 'onload', 'onfocus', 'onblur', 'onmouseover',
    ];

    $pattern = '/\b(' . implode('|', $handlers) . ')\s*=\s*["\']/i';

    $offenders = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(resource_path('views')),
    );

    foreach ($files as $file) {
        if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        $relative = str_replace(resource_path('views') . DIRECTORY_SEPARATOR, '', $file->getPathname());

        // pdf/ is included deliberately: those templates are rendered by dompdf
        // AND served as ordinary HTML pages (the print route), where CSP applies.

        $contents = (string) file_get_contents($file->getPathname());

        // Ignore the dispatcher's own prose, which names the handlers it replaced.
        $contents = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $contents);
        $contents = (string) preg_replace('#/\*.*?\*/#s', '', $contents);

        if (preg_match_all($pattern, $contents, $matches) > 0) {
            $offenders[] = $relative . ' → ' . implode(', ', array_unique($matches[1]));
        }
    }

    expect($offenders)->toBe(
        [],
        "Inline handlery blokované CSP:\n" . implode("\n", $offenders),
    );
});
