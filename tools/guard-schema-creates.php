<?php

declare(strict_types=1);

/**
 * One-shot mechanical hardening: wraps every bare Schema::create('table', ...)
 * call inside a migration's up() method with `if (!Schema::hasTable('table'))`.
 *
 * Why: MySQL commits CREATE TABLE immediately regardless of the wrapping
 * migration transaction. If `artisan migrate` gets interrupted (killed
 * process, dropped connection, permission error mid-run) right after a
 * CREATE TABLE succeeds but before Laravel logs that migration as run, a
 * re-run crashes with "table already exists" — even though the migration
 * itself has no bug. This has now hit three unrelated tables in production
 * (drip sequences, invoice_field_definitions, service_tag_assignments).
 *
 * The guard is a no-op for the normal case (table doesn't exist yet) and
 * only changes behavior for the abnormal one (table already exists from an
 * interrupted prior run) — safe to apply everywhere.
 *
 * Idempotent: skips any Schema::create already preceded by a hasTable guard.
 * Only touches up() — never down().
 *
 * Usage: php tools/guard-schema-creates.php [path-to-migrations-dir]
 */

$dir = $argv[1] ?? 'database/migrations';
$files = glob($dir . '/*.php');
sort($files);

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

    return ['start' => $pos, 'braceStart' => $braceStart, 'end' => $i];
}

$totalWrapped = 0;
$filesChanged = 0;

foreach ($files as $file) {
    $src = file_get_contents($file);

    $upBlock = extractBlock($src, 0, 'function up(');
    if ($upBlock === null) {
        continue;
    }
    $upStart = $upBlock['braceStart'] + 1;
    $upEnd = $upBlock['end']; // index of up()'s closing '}'

    /** @var list<array{stmtStart:int, stmtEnd:int, table:string, indent:string}> */
    $edits = [];

    $offset = $upStart;
    while (true) {
        $pos = strpos($src, 'Schema::create(', $offset);
        if ($pos === false || $pos >= $upEnd) {
            break;
        }

        // Table name (must be a literal string — skip dynamic/constant names).
        if (!preg_match("/Schema::create\\(\\s*'([^']+)'/", substr($src, $pos, 200), $tm)) {
            $offset = $pos + 15;
            continue;
        }
        $table = $tm[1];

        // Find the statement's closing `);` by counting parens from `Schema::create(`.
        $parenDepth = 0;
        $j = $pos + strlen('Schema::create');
        $stmtEnd = null;
        for (; $j < $upEnd; $j++) {
            if ($src[$j] === '(') {
                $parenDepth++;
            } elseif ($src[$j] === ')') {
                $parenDepth--;
                if ($parenDepth === 0) {
                    $stmtEnd = $j + 1; // position right after the matching ')'
                    break;
                }
            }
        }
        if ($stmtEnd === null) {
            $offset = $pos + 15;
            continue;
        }
        // Consume the trailing ';'.
        if (($src[$stmtEnd] ?? '') === ';') {
            $stmtEnd++;
        }

        // Already guarded? Look a short distance back for a matching hasTable check.
        $lookbackStart = max($upStart, $pos - 160);
        $before = substr($src, $lookbackStart, $pos - $lookbackStart);
        $alreadyGuarded = str_contains($before, "Schema::hasTable('{$table}')");

        // Find start-of-line for the statement, to match indentation.
        $lineStart = strrpos(substr($src, 0, $pos), "\n");
        $lineStart = $lineStart === false ? $pos : $lineStart + 1;
        $indent = substr($src, $lineStart, $pos - $lineStart);
        if (trim($indent) !== '') {
            $indent = '        '; // fallback if something unexpected precedes it
        }

        if (!$alreadyGuarded) {
            $edits[] = [
                'stmtStart' => $lineStart,
                'stmtEnd'   => $stmtEnd,
                'table'     => $table,
                'indent'    => $indent,
            ];
        }

        $offset = $stmtEnd;
    }

    if ($edits === []) {
        continue;
    }

    // Apply edits back-to-front so earlier offsets stay valid.
    foreach (array_reverse($edits) as $e) {
        $stmt = substr($src, $e['stmtStart'], $e['stmtEnd'] - $e['stmtStart']);
        $inner = preg_replace('/^(\s*)/m', '$1    ', rtrim($stmt, "\n"));
        $replacement = "{$e['indent']}if (!Schema::hasTable('{$e['table']}')) {\n"
            . $inner . "\n"
            . "{$e['indent']}}\n";
        $src = substr_replace($src, $replacement, $e['stmtStart'], $e['stmtEnd'] - $e['stmtStart']);
    }

    file_put_contents($file, $src);
    $totalWrapped += count($edits);
    $filesChanged++;
    echo basename($file) . ': wrapped ' . count($edits) . " Schema::create call(s)\n";
}

echo "\nDone: {$totalWrapped} Schema::create call(s) guarded across {$filesChanged} file(s).\n";
