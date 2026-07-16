<?php

declare(strict_types=1);

/**
 * Chronological schema simulator for Laravel migrations.
 *
 * Walks every migration file in filename (= timestamp) order, tracks which
 * columns exist on each table at each point in history, and flags any
 * ->after('col') / ->dropColumn('col') / ->renameColumn('col', ...)
 * reference to a column that does not exist yet according to the simulated
 * schema.
 *
 * This catches copy-paste column-name bugs (e.g. ->after('amount_haler') on
 * a table that only has 'amount', or ->after('some_col') referencing a
 * column a LATER migration adds) which SQLite silently ignores — Laravel's
 * SQLite grammar doesn't reposition columns at all — but MySQL rejects
 * outright on a fresh database. Exactly the production incident class this
 * project has hit twice: an unrecorded 64-char index-name failure and
 * multiple phantom/future ->after() column references.
 *
 * Usage: php tools/verify-migration-columns.php [path-to-migrations-dir]
 * Exit code 0 = clean, 1 = problems found (printed to stdout).
 */

$dir = $argv[1] ?? 'database/migrations';
$files = glob($dir . '/*.php');
sort($files); // filenames are YYYY_MM_DD_HHMMSS_name.php — sorts chronologically

/** @var array<string, array<string, bool>> table => set of known columns */
$schema = [];
$problems = [];
$skippedTables = [];

$columnMethods = implode('|', [
    'id', 'uuid', 'ulid', 'string', 'char', 'text', 'mediumText', 'longText',
    'integer', 'tinyInteger', 'smallInteger', 'mediumInteger', 'bigInteger',
    'unsignedInteger', 'unsignedTinyInteger', 'unsignedSmallInteger',
    'unsignedMediumInteger', 'unsignedBigInteger', 'float', 'double', 'decimal',
    'unsignedDecimal', 'boolean', 'json', 'jsonb', 'date', 'dateTime', 'dateTimeTz',
    'time', 'timeTz', 'timestamp', 'timestampTz', 'year', 'binary', 'foreignId',
    'foreignUuid', 'foreignUlid', 'rememberToken', 'ipAddress', 'macAddress',
    'morphs', 'nullableMorphs', 'uuidMorphs', 'enum', 'set', 'geometry', 'point',
]);

function extractBlock(string $src, int $searchFrom, string $needle): ?array
{
    $pos = strpos($src, $needle, $searchFrom);
    if ($pos === false) {
        return null;
    }
    $braceStart = strpos($src, '{', $pos);
    if ($braceStart === false) {
        return null;
    }
    $depth = 0;
    $i = $braceStart;
    $len = strlen($src);
    for (; $i < $len; $i++) {
        if ($src[$i] === '{') {
            $depth++;
        } elseif ($src[$i] === '}') {
            $depth--;
            if ($depth === 0) {
                break;
            }
        }
    }

    return ['body' => substr($src, $braceStart + 1, $i - $braceStart - 1), 'end' => $i, 'callStart' => $pos];
}

/** @return list<array{col: string, pos: int, method: string}> in source order */
function columnCalls(string $body, string $columnMethods): array
{
    preg_match_all(
        "/\\\$table->({$columnMethods})\\(\\s*'([^']*)'/",
        $body,
        $named,
        PREG_OFFSET_CAPTURE
    );
    $calls = [];
    foreach ($named[2] as $i => $m) {
        $calls[] = ['col' => $m[0], 'pos' => $m[1], 'method' => $named[1][$i][0]];
    }

    // Zero-arg id()/uuid()/ulid() → implicit column names.
    preg_match_all("/\\\$table->(id|uuid|ulid)\\(\\s*\\)/", $body, $zero, PREG_OFFSET_CAPTURE);
    foreach ($zero[1] as $m) {
        $calls[] = ['col' => $m[0], 'pos' => $m[1], 'method' => $m[0]];
    }

    preg_match_all("/\\\$table->timestamps\\(\\)/", $body, $ts, PREG_OFFSET_CAPTURE);
    foreach ($ts[0] as $m) {
        $calls[] = ['col' => 'created_at', 'pos' => $m[1], 'method' => 'timestamps'];
        $calls[] = ['col' => 'updated_at', 'pos' => $m[1], 'method' => 'timestamps'];
    }

    preg_match_all("/\\\$table->softDeletes\\(\\)/", $body, $sd, PREG_OFFSET_CAPTURE);
    foreach ($sd[0] as $m) {
        $calls[] = ['col' => 'deleted_at', 'pos' => $m[1], 'method' => 'softDeletes'];
    }

    preg_match_all("/\\\$table->rememberToken\\(\\)/", $body, $rt, PREG_OFFSET_CAPTURE);
    foreach ($rt[0] as $m) {
        $calls[] = ['col' => 'remember_token', 'pos' => $m[1], 'method' => 'rememberToken'];
    }

    preg_match_all("/\\\$table->morphs\\(\\s*'([^']*)'/", $body, $morphs, PREG_OFFSET_CAPTURE);
    foreach ($morphs[1] as $m) {
        $calls[] = ['col' => $m[0] . '_id', 'pos' => $m[1], 'method' => 'morphs'];
        $calls[] = ['col' => $m[0] . '_type', 'pos' => $m[1], 'method' => 'morphs'];
    }

    usort($calls, fn ($a, $b) => $a['pos'] <=> $b['pos']);

    return $calls;
}

foreach ($files as $file) {
    $fullSrc = file_get_contents($file);
    $base = basename($file);

    // Restrict everything to up() — down() always contains its own
    // Schema::dropIfExists('same table') for rollback, which must NOT
    // erase the table this same file just created in up().
    $upBlock = extractBlock($fullSrc, 0, 'function up(');
    $src = $upBlock !== null ? $upBlock['body'] : $fullSrc;

    // ── Schema::create('table', function ($table) { ... }); ──────────────
    $offset = 0;
    while (($block = extractBlock($src, $offset, 'Schema::create(')) !== null) {
        $offset = $block['end'];
        if (!preg_match("/Schema::create\\(\\s*'([^']+)'/", substr($src, $block['callStart'], 200), $tm)) {
            continue; // non-literal table name, e.g. a constant — skip safely
        }
        $table = $tm[1];
        $schema[$table] ??= [];
        foreach (columnCalls($block['body'], $columnMethods) as $c) {
            $schema[$table][$c['col']] = true;
        }
    }

    // ── Schema::table('table', function ($table) { ... }); (alter) ───────
    $offset = 0;
    while (($block = extractBlock($src, $offset, 'Schema::table(')) !== null) {
        $offset = $block['end'];
        if (!preg_match("/Schema::table\\(\\s*'([^']+)'/", substr($src, $block['callStart'], 200), $tm)) {
            continue;
        }
        $table = $tm[1];
        $body = $block['body'];

        if (!isset($schema[$table])) {
            $skippedTables[$table] = $base; // table created outside our regex reach
            $schema[$table] = [];
        }

        $localCalls = columnCalls($body, $columnMethods);

        // Validate ->after('x') targets against the schema-so-far snapshot,
        // walking the block in source order: columns added EARLIER in the
        // SAME alter block are valid ->after() targets for later calls in
        // that block (MySQL executes same-statement ADD COLUMNs in order).
        preg_match_all("/->after\\(\\s*'([^']+)'\\s*\\)/", $body, $afters, PREG_OFFSET_CAPTURE);
        $knownSoFar = $schema[$table];
        $localIdx = 0;
        foreach ($afters[1] as $am) {
            [$afterCol, $afterPos] = $am;
            while ($localIdx < count($localCalls) && $localCalls[$localIdx]['pos'] < $afterPos) {
                $knownSoFar[$localCalls[$localIdx]['col']] = true;
                $localIdx++;
            }
            if (!isset($knownSoFar[$afterCol])) {
                $problems[] = "{$base}: Schema::table('{$table}') -> after('{$afterCol}') — column does not exist yet";
            }
        }

        // dropColumn('x') / dropColumn(['x','y']) — array and single-string
        // forms need separate patterns so the array's ']' never ends up
        // inside the captured column name.
        preg_match_all("/->dropColumn\\(\\s*\\[([^\\]]+)\\]\\s*\\)/", $body, $dropArrays);
        preg_match_all("/->dropColumn\\(\\s*'([^']+)'\\s*\\)/", $body, $dropSingles);
        $dropLists = array_merge($dropArrays[1], array_map(fn ($c) => "'{$c}'", $dropSingles[1]));
        foreach ($dropLists as $list) {
            foreach (explode(',', $list) as $raw) {
                $col = trim($raw, " \t\n'\"");
                if ($col === '') {
                    continue;
                }
                if (!isset($schema[$table][$col])) {
                    // Only flag if it's ALSO not defined earlier in this same
                    // file (re-adding then dropping within one migration is valid).
                    $definedThisFile = false;
                    foreach ($localCalls as $c) {
                        if ($c['col'] === $col) {
                            $definedThisFile = true;
                            break;
                        }
                    }
                    if (!$definedThisFile) {
                        $problems[] = "{$base}: Schema::table('{$table}') -> dropColumn('{$col}') — column not known to exist";
                    }
                }
                unset($schema[$table][$col]);
            }
        }

        // renameColumn('old', 'new')
        preg_match_all("/->renameColumn\\(\\s*'([^']+)'\\s*,\\s*'([^']+)'\\s*\\)/", $body, $renames, PREG_SET_ORDER);
        foreach ($renames as $r) {
            if (!isset($schema[$table][$r[1]])) {
                $problems[] = "{$base}: Schema::table('{$table}') -> renameColumn('{$r[1]}', ...) — source column not known to exist";
            }
            unset($schema[$table][$r[1]]);
            $schema[$table][$r[2]] = true;
        }

        // Newly added columns in this block persist going forward.
        foreach ($localCalls as $c) {
            $schema[$table][$c['col']] = true;
        }
    }

    // ── Schema::dropIfExists('table') / Schema::drop('table') ────────────
    if (preg_match_all("/Schema::drop(?:IfExists)?\\(\\s*'([^']+)'/", $src, $dm)) {
        foreach ($dm[1] as $t) {
            unset($schema[$t]);
        }
    }

    // ── Schema::rename('old', 'new') ──────────────────────────────────────
    if (preg_match("/Schema::rename\\(\\s*'([^']+)'\\s*,\\s*'([^']+)'\\s*\\)/", $src, $rm)) {
        if (isset($schema[$rm[1]])) {
            $schema[$rm[2]] = $schema[$rm[1]];
            unset($schema[$rm[1]]);
        }
    }
}

if ($skippedTables !== []) {
    echo "NOTE — tables altered before/without a literal Schema::create seen (not necessarily bugs, just unverified):\n";
    foreach ($skippedTables as $t => $f) {
        echo "  {$t} (first seen altered in {$f})\n";
    }
    echo "\n";
}

if ($problems === []) {
    echo "OK: every ->after() / ->dropColumn() / ->renameColumn() reference matches the simulated schema at that point.\n";
    exit(0);
}

echo 'FOUND ' . count($problems) . " suspect reference(s):\n";
foreach ($problems as $p) {
    echo "  - {$p}\n";
}
exit(1);
