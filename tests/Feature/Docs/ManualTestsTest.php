<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

/*
 * TASK-0103: the manual test documents in docs/manual-tests/*.md (Czech, for the owner and QA) stay true.
 *
 * Generic over the folder, so every document of F1-F8 is checked, whoever wrote it:
 *   1. every numbered document (NN-*.md) points at an E2E test file that exists (tests/Feature/E2E/XxxTest.php);
 *   2. every path in backticks that starts with /v1/, /panel, /sprava, /registrace … (optionally "POST /v1/…") is known to the
 *      router with that method; a /panel/<slug> is also a section of the client panel (the slug map of Onhost-app.dc.html);
 *   3. every outbox event named on a line that says "událost" / "události" is listed in docs/architecture/events-catalog.md;
 *   4. README.md lists all eight documents F1-F8 and every document of the folder.
 *
 * Writing rule for the documents: a path is `/v1/orders` or `POST /v1/orders` in backticks (placeholders as {id}); an outbox
 * event is a dotted name in backticks on a line that contains the word "událost" or "události". Nothing else is parsed.
 */

/** The surfaces whose first path segment is checked against the router. */
function manualTestsPathPrefixes(): array
{
    return ['v1', 'panel', 'sprava', 'partner', 'registrace', 'prihlaseni', 'kosik', 'overeni-emailu', 'obnova-hesla', 'mailbox', 'stav', 'webhosting', 'dokumenty'];
}

/** @return list<string> every document of the folder, README included */
function manualTestsFiles(): array
{
    $files = glob(base_path('docs/manual-tests/*.md')) ?: [];
    sort($files);

    return $files;
}

/** @return list<string> the numbered documents (01-….md), the ones that describe one service */
function manualTestsNumbered(): array
{
    return array_values(array_filter(manualTestsFiles(), fn (string $f) => preg_match('/^\d{2}-/', basename($f)) === 1));
}

/** @return list<string> every backticked span of a text, with its line number as "line\tspan" */
function manualTestsSpans(string $text): array
{
    $out = [];
    foreach (preg_split('/\R/', $text) ?: [] as $i => $line) {
        if (preg_match_all('/`([^`]+)`/', $line, $m)) {
            foreach ($m[1] as $span) {
                $out[] = ($i + 1)."\t".$span;
            }
        }
    }

    return $out;
}

/** Whether the router knows the path (with the method when one is named); {x} stands for any value. */
function manualTestsRouteKnown(string $path, ?string $method): bool
{
    $routes = Route::getRoutes();
    $methods = $method === null ? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] : [$method];
    $uri = preg_replace('/\{[^}]+\}/', 'abc', $path) ?? $path;
    foreach ($methods as $m) {
        try {
            $routes->match(Request::create($uri, $m));

            return true;
        } catch (HttpException) {
            // not this method
        }
    }

    return false;
}

/** The first-segment slugs the client panel shows (TAB_SLUG of apps/surfaces/Onhost-app.dc.html). @return list<string> */
function manualTestsPanelSlugs(): array
{
    $html = (string) file_get_contents(base_path('apps/surfaces/Onhost-app.dc.html'));
    if (preg_match('/TAB_SLUG = \{(.*?)\};/s', $html, $block) !== 1) {
        return [];
    }
    preg_match_all("/[a-z]+: '([a-z-]+)'/", $block[1], $m);

    return array_values(array_unique($m[1]));
}

/** @return list<string> every event name the catalog lists */
function manualTestsCatalogEvents(): array
{
    $catalog = (string) file_get_contents(base_path('docs/architecture/events-catalog.md'));
    preg_match_all('/`([a-z_]+(?:\.[a-z_<>]+)+)`/', $catalog, $m);

    return array_values(array_unique($m[1]));
}

it('has the documents of the first four services and an index', function () {
    $names = array_map('basename', manualTestsFiles());
    foreach (['README.md', '01-registrace-objednavka.md', '02-web.md', '03-domena-dns.md', '04-email.md'] as $expected) {
        expect($names)->toContain($expected);
    }
});

it('points every numbered document at an E2E test file that exists', function () {
    $problems = [];
    foreach (manualTestsNumbered() as $file) {
        $text = (string) file_get_contents($file);
        preg_match_all('#tests/Feature/E2E/[A-Za-z0-9_]+Test\.php#', $text, $m);
        $refs = array_unique($m[0]);
        if ($refs === []) {
            $problems[] = basename($file).': names no E2E test file (tests/Feature/E2E/…Test.php)';
        }
        foreach ($refs as $ref) {
            if (! is_file(base_path($ref))) {
                $problems[] = basename($file).': '.$ref.' does not exist';
            }
        }
    }
    expect($problems)->toBe([]);
});

it('names only paths the router knows', function () {
    $slugs = manualTestsPanelSlugs();
    expect($slugs)->toContain('fakturace')->and($slugs)->toContain('domeny')->and($slugs)->toContain('posta');
    $prefixes = implode('|', array_map(fn (string $p) => preg_quote($p, '~'), manualTestsPathPrefixes()));
    $problems = [];
    $checked = 0;
    foreach (manualTestsFiles() as $file) {
        foreach (manualTestsSpans((string) file_get_contents($file)) as $row) {
            [$line, $span] = explode("\t", $row, 2);
            if (preg_match('~^(?:(GET|POST|PUT|PATCH|DELETE) )?(/(?:'.$prefixes.')(?:[/?#][^\s]*)?)$~', trim($span), $m) !== 1) {
                continue;
            }
            $method = $m[1] !== '' ? $m[1] : null;
            $path = (string) preg_replace('/[?#].*$/', '', $m[2]);
            $checked++;
            $where = basename($file).':'.$line.' '.$span;
            if (! manualTestsRouteKnown($path, $method)) {
                $problems[] = $where.' is not a route';

                continue;
            }
            if (str_starts_with($path, '/panel/')) { // /panel/{path?} answers every path: the first segment must be a real section
                $segment = explode('/', trim(substr($path, 7), '/'))[0];
                if ($segment !== '' && ! in_array($segment, $slugs, true) && ! manualTestsRouteKnown($path, 'POST') && ! str_starts_with($segment, 'konzole')) {
                    $problems[] = $where.' is not a section of the client panel ('.implode(', ', $slugs).')';
                }
            }
        }
    }
    expect($problems)->toBe([])->and($checked)->toBeGreaterThan(0);
});

it('names only outbox events the events catalog lists', function () {
    $known = manualTestsCatalogEvents();
    expect($known)->toContain('order.paid');
    $problems = [];
    foreach (manualTestsFiles() as $file) {
        foreach (preg_split('/\R/', (string) file_get_contents($file)) ?: [] as $i => $line) {
            if (mb_stripos($line, 'událost') === false && mb_stripos($line, 'události') === false) {
                continue;
            }
            preg_match_all('/`([a-z_]+(?:\.[a-z_]+)+)`/', $line, $m);
            foreach ($m[1] as $event) {
                if (! in_array($event, $known, true)) {
                    $problems[] = basename($file).':'.($i + 1).' '.$event.' is not in docs/architecture/events-catalog.md';
                }
            }
        }
    }
    expect($problems)->toBe([]);
});

it('lists every manual test document in the index', function () {
    $readme = (string) file_get_contents(base_path('docs/manual-tests/README.md'));
    $expected = ['01-registrace-objednavka.md', '02-web.md', '03-domena-dns.md', '04-email.md', '05-vps.md', '06-herni-server.md', '07-fakturace-upominky.md', '08-zruseni-konec-uctu.md'];
    $missing = [];
    foreach (array_unique(array_merge($expected, array_map('basename', array_filter(manualTestsFiles(), fn (string $f) => basename($f) !== 'README.md')))) as $name) {
        if (! str_contains($readme, $name)) {
            $missing[] = $name;
        }
    }
    expect($missing)->toBe([]);
});
