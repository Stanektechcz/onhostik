<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite indexes for the hot filter/sort paths (audit 500 #4/#5/#6).
 *
 * Single-column indexes don't help a query that filters on one column and
 * orders by another — the engine still sorts. These pair the filter with the
 * ordering column so the common admin/panel listings and the new cursor
 * pagination can walk an index instead of sorting a large result set.
 *
 * Each index is added only when the table exists and the index does not, so the
 * migration is safe to re-run and on partially-built schemas.
 */
return new class extends Migration
{
    /** @var list<array{table: string, columns: list<string>, name: string}> */
    private array $indexes = [
        // Panel + API service listings: "my active services, newest first".
        ['table' => 'services', 'columns' => ['customer_id', 'status', 'id'], 'name' => 'services_customer_status_id_index'],
        // Renewal/expiry sweeps.
        ['table' => 'services', 'columns' => ['status', 'next_due_date'], 'name' => 'services_status_next_due_index'],
        // Invoice listings + cursor pagination.
        ['table' => 'invoices', 'columns' => ['customer_id', 'status', 'id'], 'name' => 'invoices_customer_status_id_index'],
        // Dunning / overdue sweeps.
        ['table' => 'invoices', 'columns' => ['status', 'due_date'], 'name' => 'invoices_status_due_date_index'],
        // Revenue rollup reads paid invoices by date.
        ['table' => 'invoices', 'columns' => ['status', 'paid_at'], 'name' => 'invoices_status_paid_at_index'],
        // Order history.
        ['table' => 'orders', 'columns' => ['customer_id', 'status', 'id'], 'name' => 'orders_customer_status_id_index'],
        // Activity timeline for one subject.
        ['table' => 'activity_log', 'columns' => ['subject_type', 'subject_id', 'created_at'], 'name' => 'activity_subject_created_index'],
        // API usage analytics per token / per user over time.
        ['table' => 'api_usage_logs', 'columns' => ['token_id', 'created_at'], 'name' => 'api_usage_token_created_index'],
        ['table' => 'api_usage_logs', 'columns' => ['user_id', 'created_at'], 'name' => 'api_usage_user_created_index'],
        // Ticket queues.
        ['table' => 'support_tickets', 'columns' => ['customer_id', 'status', 'id'], 'name' => 'tickets_customer_status_id_index'],
        // Domain expiry reminders.
        ['table' => 'domain_registrations', 'columns' => ['expires_at', 'auto_renew'], 'name' => 'domains_expiry_autorenew_index'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $index) {
            if (! Schema::hasTable($index['table']) || $this->hasIndex($index['table'], $index['name'])) {
                continue;
            }

            foreach ($index['columns'] as $column) {
                if (! Schema::hasColumn($index['table'], $column)) {
                    continue 2; // column missing on this schema — skip the index
                }
            }

            Schema::table($index['table'], function (Blueprint $table) use ($index): void {
                $table->index($index['columns'], $index['name']);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $index) {
            if (! Schema::hasTable($index['table']) || ! $this->hasIndex($index['table'], $index['name'])) {
                continue;
            }

            Schema::table($index['table'], function (Blueprint $table) use ($index): void {
                $table->dropIndex($index['name']);
            });
        }
    }

    private function hasIndex(string $table, string $name): bool
    {
        foreach (Schema::getIndexes($table) as $existing) {
            if (($existing['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }
};
