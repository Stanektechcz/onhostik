@extends('layouts.panel')

@section('title', 'LTV Report')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- Segment avg KPI strip --}}
    <div class="grid grid-cols-12 card-gap mb-3">
        @foreach($segments as $seg)
        <div class="col-span-3 xl:col-span-6 sm:col-span-12">
            <div class="small-widget">
                <div class="card card-no-border">
                    <div class="card-body">
                        <div>
                            <h6 class="f-w-500 mb-1">{{ $seg->value }}</h6>
                            <h5 class="mb-0 f-w-600">
                                {{ number_format(($segmentAvg[$seg->value] ?? 0) / 100, 0, ',', ' ') }} Kč
                            </h5>
                            <span class="f-light f-12">Průměrné LTV</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <x-panel.card title="Top zákazníci dle LTV (top 100)">
        @if($ranked->isEmpty())
            <p class="text-muted mb-0">Žádná data.</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Zákazník</th>
                            <th>Segment</th>
                            <th class="text-right">LTV (Kč)</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ranked as $i => $row)
                        <tr>
                            <td class="f-w-500">{{ $i + 1 }}</td>
                            <td>
                                @if($row['customer'])
                                    <span class="f-w-500">{{ $row['customer']->email }}</span>
                                    @if($row['customer']->company_name)
                                        <br><span class="f-12 f-light">{{ $row['customer']->company_name }}</span>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($row['customer']?->segment)
                                    <span class="badge badge-light-primary">{{ $row['customer']->segment }}</span>
                                @else
                                    <span class="text-muted f-12">—</span>
                                @endif
                            </td>
                            <td class="text-right f-w-600">
                                {{ number_format($row['ltv_minor'] / 100, 0, ',', ' ') }} Kč
                            </td>
                            <td class="text-right">
                                @if($row['customer'])
                                    <a href="{{ route('admin.customers.show', $row['customer']) }}"
                                       class="btn btn-outline-secondary btn-sm">
                                        Detail
                                    </a>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-panel.card>
</div>
@endsection
