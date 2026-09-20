<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SQLite does not look at the length of a VARCHAR; PostgreSQL — the production database — refuses the statement. With
 * `ONHOST_WIDTH_GUARD=1` every test is followed by a measurement: the longest value of every string column against the width
 * the migrations declare for it. What is too long is written to `storage/framework/testing/width-guard.log` (one line per
 * table and column). It costs about a query per table per test, so it is opt-in: run it before a release and whenever a
 * migration or a key format changes.
 */
final class WidthGuard
{
    /** @var array<string, array<string,int>>|null table → column → declared width */
    private static ?array $widths = null;

    /** @var array<string,true> */
    private static array $reported = [];

    public static function enabled(): bool
    {
        return getenv('ONHOST_WIDTH_GUARD') === '1';
    }

    /**
     * Read from the migrations: SQLite creates a bare `varchar`, so the schema itself cannot be asked. A later migration
     * (`->change()`) overrides an earlier one.
     *
     * @return array<string, array<string,int>>
     */
    public static function widths(): array
    {
        if (self::$widths !== null) {
            return self::$widths;
        }
        $widths = [];
        $files = glob(database_path('migrations/*.php')) ?: [];
        sort($files);
        foreach ($files as $file) {
            $source = (string) file_get_contents($file);
            $down = strpos($source, 'function down(');
            $parts = preg_split('/(?=Schema::(?:create|table)\()/', $down === false ? $source : substr($source, 0, $down)) ?: []; // what `down()` narrows back is not the schema
            foreach ($parts as $part) {
                if (! preg_match('/^Schema::(?:create|table)\(\'([a-z_0-9]+)\'/', $part, $m)) {
                    continue;
                }
                preg_match_all('/\$table->string\(\'([a-z_0-9]+)\'(?:,\s*(\d+))?\)/', $part, $columns, PREG_SET_ORDER);
                foreach ($columns as $column) {
                    $widths[$m[1]][$column[1]] = isset($column[2]) && $column[2] !== '' ? (int) $column[2] : 255;
                }
            }
        }

        return self::$widths = $widths;
    }

    /** @return array<string,int> */
    public static function of(string $table): array
    {
        return self::widths()[$table] ?? [];
    }

    /** @return list<string> what was found too long after this test (`table.column: length > width`) */
    public static function measure(string $test): array
    {
        $found = [];
        foreach (self::widths() as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $existing = array_flip(Schema::getColumnListing($table));
            $columns = array_intersect_key($columns, $existing);
            if ($columns === []) {
                continue;
            }
            $select = implode(', ', array_map(fn (string $c) => 'max(length("'.$c.'")) as "'.$c.'"', array_keys($columns)));
            $row = (array) DB::table($table)->selectRaw($select)->first();
            foreach ($columns as $column => $width) {
                $longest = (int) ($row[$column] ?? 0);
                if ($longest > $width && ! isset(self::$reported["{$table}.{$column}"])) {
                    self::$reported["{$table}.{$column}"] = true;
                    $found[] = "{$table}.{$column}: {$longest} > {$width} ({$test})";
                }
            }
        }
        if ($found !== []) {
            $dir = storage_path('framework/testing');
            if (! is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            file_put_contents($dir.'/width-guard.log', implode("\n", $found)."\n", FILE_APPEND);
        }

        return $found;
    }
}
