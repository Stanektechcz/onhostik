<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * H5: production-size data for `php artisan onhost:doctor` (a LOCAL benchmark, never part of DatabaseSeeder).
 *
 *   ONHOST_BENCH_ORGS=10000 php artisan db:seed --class=DoctorBenchmarkSeeder   (use a scratch database)
 *
 * Per organization: 3 loyalty rows, 1-2 services, 3 invoices, and a refund for every tenth one. Rows are bulk inserts
 * with every NOT NULL column without a default filled from its type, so a new column does not break the seeder.
 * Refuses to run in production.
 */
final class DoctorBenchmarkSeeder extends Seeder
{
    private const CHUNK = 500;

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('The benchmark seeder never runs in production.');

            return;
        }
        $orgs = max(1, (int) (getenv('ONHOST_BENCH_ORGS') ?: 10000));
        $now = now()->toDateTimeString();
        $old = now()->subMonths(30)->toDateTimeString();
        $buffers = [];
        $flush = function (string $table) use (&$buffers): void {
            if (! empty($buffers[$table])) {
                foreach (array_chunk($buffers[$table], self::CHUNK) as $part) {
                    DB::table($table)->insert($part);
                }
                $buffers[$table] = [];
            }
        };
        $push = function (string $table, array $row) use (&$buffers, $flush, $now): void {
            $buffers[$table][] = $this->complete($table, $row, $now);
            if (count($buffers[$table]) >= self::CHUNK) {
                $flush($table);
            }
        };

        for ($i = 1; $i <= $orgs; $i++) {
            $org = sprintf('org_bench%07d', $i);
            $push('organizations', ['id' => $org, 'slug' => "bench-{$i}", 'name' => "Bench {$i}", 'owner_user_id' => sprintf('usr_bench%07d', $i)]);
            foreach (['order.paid' => 100, 'payment.on_time' => 20, 'mfa.enabled' => -5] as $rule => $points) {
                $push('loyalty_points', ['id' => sprintf('lp_%s_%s', $i, substr(md5($rule), 0, 6)), 'organization_id' => $org, 'rule' => $rule, 'reference' => "ref-{$i}", 'points' => $points, 'created_at' => $i % 7 === 0 ? $old : $now]);
            }
            for ($s = 1; $s <= 1 + $i % 2; $s++) {
                $push('services', ['id' => sprintf('svc_b%07d_%d', $i, $s), 'organization_id' => $org, 'product_key' => 'web-start', 'family' => 'web', 'name' => "Bench service {$i}-{$s}", 'state' => 'ACTIVE']);
            }
            for ($n = 1; $n <= 3; $n++) {
                $invoice = sprintf('inv_b%07d_%d', $i, $n);
                $push('invoices', ['id' => $invoice, 'number' => "B{$i}-{$n}", 'organization_id' => $org, 'type' => $n === 3 ? 'credit_note' : 'invoice', 'state' => 'PAID', 'currency' => 'CZK', 'total_minor' => 12100, 'paid_minor' => 12100]);
                if ($n === 3 && $i % 10 === 0) {
                    $push('payment_refunds', ['id' => sprintf('rf_b%07d', $i), 'payment_intent_id' => sprintf('pi_b%07d', $i), 'credit_note_id' => $invoice, 'state' => 'succeeded', 'amount_minor' => 12100, 'currency' => 'CZK']);
                }
            }
        }
        foreach (['organizations', 'loyalty_points', 'services', 'invoices', 'payment_refunds'] as $table) {
            $flush($table);
        }
        $this->command?->info("Seeded {$orgs} organizations with loyalty points, services, invoices and refunds.");
    }

    /** @return array<string, mixed> the row plus a value for every NOT NULL column that has no default */
    private function complete(string $table, array $row, string $now): array
    {
        static $columns = [];
        static $serial = 0;
        $columns[$table] ??= Schema::getColumns($table);
        foreach ($columns[$table] as $column) {
            $name = $column['name'];
            if (array_key_exists($name, $row)) {
                continue;
            }
            if ($name === 'created_at' || $name === 'updated_at') {
                $row[$name] = $now;
            } elseif (! $column['nullable'] && $column['default'] === null && ! ($column['auto_increment'] ?? false)) {
                $type = (string) $column['type_name'];
                $row[$name] = match (true) {
                    str_contains($type, 'int'), in_array($type, ['numeric', 'decimal', 'float', 'double', 'real'], true) => 0,
                    in_array($type, ['bool', 'boolean', 'tinyint'], true) => 0,
                    str_contains($type, 'json') => '{}',
                    str_contains($type, 'time'), $type === 'date' => $now,
                    default => 'x'.dechex(++$serial), // short and unique (unique columns exist)
                };
            }
        }

        return $row;
    }
}
