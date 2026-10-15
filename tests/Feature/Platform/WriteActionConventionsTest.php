<?php

declare(strict_types=1);

use Onhost\Domain\Identity\Authorization\PermissionCatalog;

/*
 * F5 (panel decision 2026-10-15, variant C): every write goes through the CommandBus as a command with a handler, a risk level
 * and a permission key (AGENTS.md, CLAUDE.md). This test walks every concrete Command in app/, domains/, platform/ and
 * providers/ by reading the source (no command is constructed, nothing is dispatched) and asserts:
 *
 *   handler    - DomainServiceProvider::HANDLERS maps it, or the conventional `<Command>Handler` class exists (CommandBus::resolveHandler)
 *   permission - permission() names at least one literal key, and every literal key it names exists in PermissionCatalog
 *   risk       - its risk is known: a catalogue key carries one, or the command declares one as a RiskAwareCommand
 *
 * Today's exceptions sit in WAC_ALLOWLIST with a date. An expired entry fails, and so does an entry that no longer matches a
 * violation (it must then be removed) - the list can only shrink. It lives in tests/Feature because phpunit.xml does not run
 * tests/Architecture.
 */

const WAC_COMMAND = 'Onhost\\Platform\\Commands\\Command';
const WAC_HANDLER = 'Onhost\\Platform\\Commands\\CommandHandler';
const WAC_RISK_AWARE = 'Onhost\\Domain\\Identity\\Authorization\\RiskAwareCommand';
const WAC_PROVIDER = 'App\\Providers\\DomainServiceProvider';

/**
 * Current exceptions: `command FQCN => [rules => list<handler|permission|risk>, until => Y-m-d, reason => why]`.
 *
 * @var array<string, array{rules: list<string>, until: string, reason: string}>
 */
const WAC_ALLOWLIST = [
    // permission() returns null: actions of a signed-in person on their own session/membership, before any organization role applies
    'Onhost\\Domain\\Identity\\Commands\\WebSessionCommand' => ['rules' => ['permission', 'risk'], 'until' => '2027-01-15', 'reason' => 'null permission: a person ends their own web sessions; handler checks the actor'],
    'Onhost\\Domain\\Organizations\\Commands\\AcceptInvitationCommand' => ['rules' => ['permission', 'risk'], 'until' => '2027-01-15', 'reason' => 'null permission: the invitee has no role yet; the invitation token is the authorization'],
    'Onhost\\Domain\\Organizations\\Commands\\CreateOrganizationCommand' => ['rules' => ['permission', 'risk'], 'until' => '2027-01-15', 'reason' => 'null permission: creating an organization precedes any role in it'],
    // permission() returns null: system-internal, the handler admits the system actor only
    'Onhost\\Domain\\Services\\Penpot\\PaperParentCommand' => ['rules' => ['permission', 'risk'], 'until' => '2027-01-15', 'reason' => 'null permission: system-internal, handler admits the system actor only'],
    'Onhost\\Domain\\Services\\Metering\\AnnounceDiskTotalCommand' => ['rules' => ['permission'], 'until' => '2027-01-15', 'reason' => 'null permission: system-internal, handler checks the actor (RiskAwareCommand declares the risk)'],
    'Onhost\\Domain\\Tax\\Commands\\RecordVatCheckCommand' => ['rules' => ['permission'], 'until' => '2027-01-15', 'reason' => 'null permission: system-internal, handler checks the actor (RiskAwareCommand declares the risk)'],
    // permission() is computed from a lookup table (ServiceActionCommand::PERMISSIONS) the source scan does not follow
    'Onhost\\Domain\\Services\\Commands\\ServiceActionCommand' => ['rules' => ['permission'], 'until' => '2027-01-15', 'reason' => 'permissionFor() maps actions via PERMISSIONS; covered by its own tests, not readable as literals here'],
];

/** @return array<string, array{file: string, abstract: bool, parents: list<string>, uses: array<string, string>, namespace: string}> */
function wacScanClasses(string $root): array
{
    $classes = [];
    foreach (['app', 'domains', 'platform', 'providers'] as $dir) {
        $base = $root.DIRECTORY_SEPARATOR.$dir;
        if (! is_dir($base)) {
            continue;
        }
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $classes += wacParseFile($file->getPathname());
            }
        }
    }

    return $classes;
}

/** @return array<string, array{file: string, abstract: bool, parents: list<string>, uses: array<string, string>, namespace: string}> */
function wacParseFile(string $path): array
{
    $tokens = array_values(array_filter(PhpToken::tokenize((string) file_get_contents($path)), fn (PhpToken $t) => ! $t->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])));
    $namespace = '';
    $uses = [];
    $out = [];
    $depth = 0;
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        $t = $tokens[$i];
        if ($t->text === '{' || $t->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) {
            $depth++;
        } elseif ($t->text === '}') {
            $depth--;
        } elseif ($t->is(T_NAMESPACE) && $depth === 0) {
            $namespace = $tokens[$i + 1]->text;
        } elseif ($t->is(T_USE) && $depth === 0) {
            $name = $tokens[$i + 1]->text;
            if (in_array($name, ['function', 'const'], true)) {
                continue;
            }
            $alias = substr($name, (int) strrpos('\\'.$name, '\\'));
            if (($tokens[$i + 2]->text ?? '') === 'as' || $tokens[$i + 2]->is(T_AS)) {
                $alias = $tokens[$i + 3]->text;
            }
            $uses[$alias] = ltrim($name, '\\');
        } elseif ($t->is([T_CLASS, T_INTERFACE]) && ($tokens[$i - 1]->text ?? '') !== '::' && ($tokens[$i - 1]->text ?? '') !== 'new' && $tokens[$i + 1]->is(T_STRING)) {
            $abstract = $t->is(T_INTERFACE);
            for ($j = $i - 1; $j >= 0 && $tokens[$j]->is([T_ABSTRACT, T_FINAL, T_READONLY]); $j--) {
                $abstract = $abstract || $tokens[$j]->is(T_ABSTRACT);
            }
            $parents = [];
            for ($j = $i + 2; $j < $count && $tokens[$j]->text !== '{'; $j++) {
                if ($tokens[$j]->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) {
                    $parents[] = wacResolve($tokens[$j]->text, $namespace, $uses);
                }
            }
            $fqcn = ltrim($namespace.'\\'.$tokens[$i + 1]->text, '\\');
            $out[$fqcn] = ['file' => $path, 'abstract' => $abstract, 'parents' => $parents, 'uses' => $uses, 'namespace' => $namespace];
        }
    }

    return $out;
}

/** @param array<string, string> $uses */
function wacResolve(string $name, string $namespace, array $uses): string
{
    if (str_starts_with($name, '\\')) {
        return ltrim($name, '\\');
    }
    $head = explode('\\', $name)[0];
    if (isset($uses[$head])) {
        return $uses[$head].substr($name, strlen($head));
    }

    return ltrim($namespace.'\\'.$name, '\\');
}

/** @param array<string, array{parents: list<string>}> $classes */
function wacReaches(string $class, string $target, array $classes, int $guard = 0): bool
{
    if ($class === $target) {
        return true;
    }
    foreach ($classes[$class]['parents'] ?? [] as $parent) {
        if ($guard < 32 && wacReaches($parent, $target, $classes, $guard + 1)) {
            return true;
        }
    }

    return false;
}

/** Body of `function $method(` as declared in $file, or null. */
function wacMethodBody(string $file, string $method): ?string
{
    $tokens = PhpToken::tokenize((string) file_get_contents($file));
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        if (! $tokens[$i]->is(T_FUNCTION)) {
            continue;
        }
        $j = $i + 1;
        while ($j < $count && $tokens[$j]->is(T_WHITESPACE)) {
            $j++;
        }
        if (! $tokens[$j]->is(T_STRING) || $tokens[$j]->text !== $method) {
            continue;
        }
        while ($j < $count && $tokens[$j]->text !== '{' && $tokens[$j]->text !== ';') {
            $j++;
        }
        if ($tokens[$j]->text === ';') {
            return null; // abstract or interface declaration
        }
        $depth = 0;
        $body = '';
        for (; $j < $count; $j++) {
            $text = $tokens[$j]->text;
            $depth += match (true) {
                $text === '{', $tokens[$j]->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES]) => 1,
                $text === '}' => -1,
                default => 0,
            };
            $body .= $text;
            if ($depth === 0) {
                return $body;
            }
        }
    }

    return null;
}

/** Literal permission keys named by the nearest permission() in the class or its in-repo ancestors. @return list<string>|null */
function wacPermissionKeys(string $class, array $classes, int $guard = 0): ?array
{
    if (! isset($classes[$class]) || $guard > 32) {
        return null;
    }
    $body = wacMethodBody($classes[$class]['file'], 'permission');
    if ($body === null) {
        foreach ($classes[$class]['parents'] as $parent) {
            $keys = wacPermissionKeys($parent, $classes, $guard + 1);
            if ($keys !== null) {
                return $keys;
            }
        }

        return null;
    }
    $keys = [];
    $previous = '';
    $tokens = array_values(array_filter(PhpToken::tokenize('<?php '.$body), fn (PhpToken $t) => ! $t->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])));
    foreach ($tokens as $n => $t) {
        $after = $previous;
        $previous = $t->text;
        // `return Foo::PERMISSION;` / `self::PERMISSION`: a string class constant counts as the literal it holds
        if ($t->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_STATIC]) && ($tokens[$n + 1]->text ?? '') === '::'
            && ($tokens[$n + 2] ?? null)?->is(T_STRING) && ($tokens[$n + 3]->text ?? '') !== '[' && ($tokens[$n + 3]->text ?? '') !== '('
            && in_array(strtolower($after), ['return', '=>', '?', ':', '??'], true)) {
            $owner = in_array(strtolower($t->text), ['self', 'static'], true) ? $class : wacResolve($t->text, $classes[$class]['namespace'], $classes[$class]['uses']);
            $value = isset($classes[$owner]) && preg_match('/const\s+(?:string\s+)?'.preg_quote($tokens[$n + 2]->text, '/').'\s*=\s*([\'"])([^\'"]+)\1\s*;/', (string) file_get_contents($classes[$owner]['file']), $m) === 1 ? $m[2] : null;
            if ($value !== null && preg_match('/^[a-z0-9_]+(\.[a-z0-9_]+)+$/', $value) === 1) {
                $keys[] = $value;
            }

            continue;
        }
        // a returned value follows `return`, `=>` (a match arm's result), `?`, `:` or `??`; a match condition (`'op' =>`) does not
        if ($t->is(T_CONSTANT_ENCAPSED_STRING) && in_array(strtolower($after), ['return', '=>', '?', ':', '??'], true)) {
            $value = substr($t->text, 1, -1);
            if (preg_match('/^[a-z0-9_]+(\.[a-z0-9_]+)+$/', $value) === 1) {
                $keys[] = $value;
            }
        }
    }

    return array_values(array_unique($keys));
}

/** DomainServiceProvider::HANDLERS read from source: command FQCN => handler FQCN. @return array<string, string> */
function wacRegisteredHandlers(array $classes): array
{
    $info = $classes[WAC_PROVIDER] ?? null;
    if ($info === null) {
        return [];
    }
    $src = (string) file_get_contents($info['file']);
    if (preg_match('/const\s+HANDLERS\s*=\s*\[(.*?)\n\s*\];/s', $src, $m) !== 1) {
        return [];
    }
    preg_match_all('/([\\\\\w]+)::class\s*=>\s*([\\\\\w]+)::class/', $m[1], $pairs, PREG_SET_ORDER);
    $map = [];
    foreach ($pairs as [, $command, $handler]) {
        $map[wacResolve($command, $info['namespace'], $info['uses'])] = wacResolve($handler, $info['namespace'], $info['uses']);
    }

    return $map;
}

/** Violations: command FQCN => sorted list of broken rules (handler|permission|risk), plus details. @return array<string, array{rules: list<string>, detail: list<string>}> */
function wacViolations(string $root, callable $permissionExists): array
{
    $classes = wacScanClasses($root);
    $handlers = wacRegisteredHandlers($classes);
    $out = [];
    foreach ($classes as $class => $info) {
        if ($info['abstract'] || $class === WAC_COMMAND || ! wacReaches($class, WAC_COMMAND, $classes)) {
            continue;
        }
        $rules = [];
        $detail = [];
        $handler = $handlers[$class] ?? $class.'Handler';
        if (! isset($classes[$handler]) || ! wacReaches($handler, WAC_HANDLER, $classes)) {
            $rules[] = 'handler';
            $detail[] = "no CommandHandler {$handler}";
        }
        $keys = wacPermissionKeys($class, $classes) ?? [];
        $unknown = array_values(array_filter($keys, fn (string $k) => ! $permissionExists($k)));
        if ($keys === [] || $unknown !== []) {
            $rules[] = 'permission';
            $detail[] = $keys === [] ? 'permission() names no key' : 'unknown permission keys: '.implode(', ', $unknown);
        }
        if (count($unknown) === count($keys) && ! wacReaches($class, WAC_RISK_AWARE, $classes)) {
            $rules[] = 'risk';
            $detail[] = 'no catalogue key and not a RiskAwareCommand';
        }
        if ($rules !== []) {
            $out[$class] = ['rules' => $rules, 'detail' => $detail];
        }
    }
    ksort($out);

    return $out;
}

function wacProjectRoot(): string
{
    return dirname(__DIR__, 3);
}

it('finds the bus commands it is meant to guard', function () {
    $classes = wacScanClasses(wacProjectRoot());
    $commands = array_filter(array_keys($classes), fn (string $c) => ! $classes[$c]['abstract'] && wacReaches($c, WAC_COMMAND, $classes));

    expect(count($commands))->toBeGreaterThan(20)
        ->and(wacRegisteredHandlers($classes))->not->toBeEmpty();
});

it('gives every bus command a handler, a permission key and a risk level, or a dated allowlist entry', function () {
    $violations = wacViolations(wacProjectRoot(), fn (string $key) => PermissionCatalog::exists($key));
    $failures = [];
    foreach ($violations as $class => $v) {
        $allowed = WAC_ALLOWLIST[$class]['rules'] ?? [];
        $missing = array_diff($v['rules'], $allowed);
        if ($missing !== []) {
            $failures[] = $class.' breaks '.implode(', ', $missing).' ('.implode('; ', $v['detail']).')';
        }
    }

    expect($failures)->toBe([]);
});

it('keeps the allowlist dated, unexpired and no wider than today', function () {
    $violations = wacViolations(wacProjectRoot(), fn (string $key) => PermissionCatalog::exists($key));
    $today = date('Y-m-d');
    $problems = [];
    foreach (WAC_ALLOWLIST as $class => $entry) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $entry['until']) !== 1 || $entry['until'] < $today) {
            $problems[] = "{$class}: entry expired or undated ({$entry['until']})";
        }
        if (trim($entry['reason']) === '') {
            $problems[] = "{$class}: entry has no reason";
        }
        $stale = array_diff($entry['rules'], $violations[$class]['rules'] ?? []);
        if ($stale !== []) {
            $problems[] = "{$class}: no longer breaks ".implode(', ', $stale).' - remove it from WAC_ALLOWLIST';
        }
    }

    expect($problems)->toBe([]);
});
