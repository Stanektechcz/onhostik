<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Models\ServiceResourceUsage;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServiceUsageExportController extends Controller
{
    public function export(Request $request, Service $service): StreamedResponse
    {
        $this->authorize('view', $service);

        $from = $request->date('from', 'Y-m-d');
        $to   = $request->date('to', 'Y-m-d');

        $query = ServiceResourceUsage::where('service_id', $service->id)
            ->orderBy('recorded_at');

        if ($from !== null) {
            $query->where('recorded_at', '>=', $from->startOfDay());
        }

        if ($to !== null) {
            $query->where('recorded_at', '<=', $to->endOfDay());
        }

        $rows = $query->get();

        $filename = 'pouziti-' . $service->id . '-' . now()->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Datum a čas', 'CPU (%)', 'RAM (MB)', 'Disk (GB)', 'Bandwidth (GB)']);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->recorded_at->format('Y-m-d H:i:s'),
                    $row->cpu_percent ?? '',
                    $row->ram_mb ?? '',
                    $row->disk_gb ?? '',
                    $row->bandwidth_gb ?? '',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
