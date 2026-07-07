<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $logName     = $request->string('log')->toString();
        $search      = $request->string('q')->toString();
        $from        = $request->string('from')->toString();
        $to          = $request->string('to')->toString();
        $causerEmail = $request->string('causer')->toString();
        $subjectType = $request->string('subject_type')->toString();

        $activities = Activity::query()
            ->with(['causer', 'subject'])
            ->when($logName !== '', fn ($q) => $q->where('log_name', $logName))
            ->when($search !== '', fn ($q) => $q->where('description', 'like', "%{$search}%"))
            ->when($from !== '', fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to !== '', fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->when($causerEmail !== '', fn ($q) => $q->whereHasMorph(
                'causer', [User::class],
                fn ($u) => $u->where('email', 'like', "%{$causerEmail}%")
            ))
            ->when($subjectType !== '', fn ($q) => $q->where('subject_type', 'like', "%{$subjectType}%"))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.audit', compact(
            'activities', 'logName', 'search', 'from', 'to', 'causerEmail', 'subjectType'
        ) + ['filter' => $logName]);
    }

    public function export(Request $request): Response
    {
        $logName     = $request->string('log')->toString();
        $from        = $request->string('from')->toString();
        $to          = $request->string('to')->toString();
        $search      = $request->string('q')->toString();
        $causerEmail = $request->string('causer')->toString();
        $subjectType = $request->string('subject_type')->toString();
        $format      = $request->string('format', 'csv')->toString();

        $query = Activity::query()
            ->with(['causer'])
            ->when($logName !== '', fn ($q) => $q->where('log_name', $logName))
            ->when($search !== '', fn ($q) => $q->where('description', 'like', "%{$search}%"))
            ->when($from !== '', fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to !== '', fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->when($causerEmail !== '', fn ($q) => $q->whereHasMorph(
                'causer', [User::class],
                fn ($u) => $u->where('email', 'like', "%{$causerEmail}%")
            ))
            ->when($subjectType !== '', fn ($q) => $q->where('subject_type', 'like', "%{$subjectType}%"))
            ->orderBy('id');

        $filename = 'audit_log_' . now()->format('Ymd_His');

        if ($format === 'json') {
            return $this->exportJson($query, $filename);
        }

        return $this->exportCsv($query, $filename);
    }

    private function exportCsv(mixed $query, string $filename): Response
    {
        $rows = ["ID,Log,Event,Causer,CauserEmail,SubjectType,SubjectId,Properties,Timestamp"];

        $query->chunk(500, function ($activities) use (&$rows): void {
            foreach ($activities as $a) {
                $rows[] = implode(',', array_map(
                    fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"',
                    [
                        $a->id,
                        $a->log_name,
                        $a->description,
                        ($a->causer instanceof User ? $a->causer->name : null) ?? '',
                        ($a->causer instanceof User ? $a->causer->email : null) ?? '',
                        $a->subject_type ?? '',
                        $a->subject_id ?? '',
                        json_encode($a->properties, JSON_UNESCAPED_UNICODE) ?: '',
                        $a->created_at?->toISOString() ?? '',
                    ]
                ));
            }
        });

        return response(implode("\n", $rows), 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ]);
    }

    private function exportJson(mixed $query, string $filename): Response
    {
        $records = [];

        $query->chunk(500, function ($activities) use (&$records): void {
            foreach ($activities as $a) {
                $records[] = [
                    'id'           => $a->id,
                    'log_name'     => $a->log_name,
                    'description'  => $a->description,
                    'causer_name'  => ($a->causer instanceof User ? $a->causer->name : null),
                    'causer_email' => ($a->causer instanceof User ? $a->causer->email : null),
                    'subject_type' => $a->subject_type,
                    'subject_id'   => $a->subject_id,
                    'properties'   => $a->properties,
                    'timestamp'    => $a->created_at?->toISOString(),
                ];
            }
        });

        $json = json_encode(
            ['exported_at' => now()->toISOString(), 'count' => count($records), 'records' => $records],
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );

        return response($json ?: '{}', 200, [
            'Content-Type'        => 'application/json; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}.json\"",
        ]);
    }
}
