<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Reporting\ReportBuilder;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReportController extends Controller
{
    public function __construct(private readonly ReportBuilder $builder) {}

    public function index(Request $request): View
    {
        $params   = $this->resolveParams($request);
        $report   = null;
        $hasQuery = $request->has('type');

        if ($hasQuery) {
            $report = $this->builder->build($params);
        }

        return view('admin.reports.index', [
            'types'    => ReportBuilder::TYPES,
            'params'   => $params,
            'report'   => $report,
            'hasQuery' => $hasQuery,
        ]);
    }

    public function download(Request $request): Response
    {
        $request->validate([
            'type' => ['required', 'in:' . implode(',', array_keys(ReportBuilder::TYPES))],
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
        ]);

        $params   = $this->resolveParams($request);
        $report   = $this->builder->build($params);
        $csv      = $this->builder->toCsv($report);
        $filename = 'report_' . $params['type'] . '_' . now()->format('Ymd_His') . '.csv';

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * @return array{type: string, from: string, to: string, group_by: string, status: string}
     */
    private function resolveParams(Request $request): array
    {
        return [
            'type'     => $request->string('type', 'revenue')->toString(),
            'from'     => $request->string('from', now()->startOfMonth()->format('Y-m-d'))->toString(),
            'to'       => $request->string('to', now()->format('Y-m-d'))->toString(),
            'group_by' => $request->string('group_by', 'month')->toString(),
            'status'   => $request->string('status')->toString(),
        ];
    }
}
