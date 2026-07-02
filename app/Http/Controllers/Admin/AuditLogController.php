<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $logName = $request->string('log')->toString();
        $search  = $request->string('q')->toString();

        return view('admin.audit', [
            'activities' => Activity::query()
                ->with(['causer', 'subject'])
                ->when($logName !== '', fn ($query) => $query->where('log_name', $logName))
                ->when($search !== '', fn ($query) => $query->where('description', 'like', "%{$search}%"))
                ->latest('id')
                ->paginate(30)
                ->withQueryString(),
            'filter' => $logName,
        ]);
    }

    public function export(Request $request): Response
    {
        $logName = $request->string('log')->toString();
        $from    = $request->string('from')->toString();
        $to      = $request->string('to')->toString();

        $query = Activity::query()
            ->with(['causer'])
            ->when($logName !== '', fn ($q) => $q->where('log_name', $logName))
            ->when($from !== '', fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to !== '', fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->orderBy('id');

        $rows = ["ID,Log,Event,Causer,CauserEmail,SubjectType,SubjectId,Properties,Timestamp"];

        $query->chunk(500, function ($activities) use (&$rows): void {
            foreach ($activities as $a) {
                $rows[] = implode(',', array_map(
                    fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"',
                    [
                        $a->id,
                        $a->log_name,
                        $a->description,
                        ($a->causer instanceof \App\Models\User ? $a->causer->name : null) ?? '',
                        ($a->causer instanceof \App\Models\User ? $a->causer->email : null) ?? '',
                        $a->subject_type ?? '',
                        $a->subject_id ?? '',
                        json_encode($a->properties, JSON_UNESCAPED_UNICODE) ?: '',
                        $a->created_at?->toISOString() ?? '',
                    ]
                ));
            }
        });

        $filename = 'audit_log_' . now()->format('Ymd_His') . '.csv';

        return response(implode("\n", $rows), 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
