<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class AuditLogExportController extends Controller
{
    public function export(Request $request): Response
    {
        $validated = $request->validate([
            'from'       => ['nullable', 'date'],
            'to'         => ['nullable', 'date', 'after_or_equal:from'],
            'log_name'   => ['nullable', 'string', 'max:50'],
            'causer_id'  => ['nullable', 'integer'],
        ]);

        $query = DB::table('activity_log')
            ->orderByDesc('created_at')
            ->limit(10000);

        if (! empty($validated['from'])) {
            $query->where('created_at', '>=', $validated['from']);
        }
        if (! empty($validated['to'])) {
            $query->where('created_at', '<=', $validated['to'] . ' 23:59:59');
        }
        if (! empty($validated['log_name'])) {
            $query->where('log_name', $validated['log_name']);
        }
        if (! empty($validated['causer_id'])) {
            $query->where('causer_id', $validated['causer_id']);
        }

        $rows = $query->get();

        $csv = "id,log_name,description,subject_type,subject_id,causer_type,causer_id,properties,created_at\n";
        foreach ($rows as $row) {
            $csv .= implode(',', [
                $row->id,
                '"' . str_replace('"', '""', (string) ($row->log_name ?? '')) . '"',
                '"' . str_replace('"', '""', (string) ($row->description ?? '')) . '"',
                '"' . str_replace('"', '""', (string) ($row->subject_type ?? '')) . '"',
                (string) ($row->subject_id ?? ''),
                '"' . str_replace('"', '""', (string) ($row->causer_type ?? '')) . '"',
                (string) ($row->causer_id ?? ''),
                '"' . str_replace('"', '""', (string) ($row->properties ?? '')) . '"',
                '"' . ($row->created_at ?? '') . '"',
            ]) . "\n";
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="audit-log-' . now()->format('Y-m-d') . '.csv"',
        ]);
    }

    public function index(): \Illuminate\View\View
    {
        $logNames = DB::table('activity_log')->distinct()->pluck('log_name')->filter()->values();

        return view('admin.audit-log-export.index', compact('logNames'));
    }
}
