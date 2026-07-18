@extends('layouts.panel')

@section('title', 'Aktivní uživatelé')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- KPI strip --}}
    <div class="grid grid-cols-12 card-gap mb-3">
        <div class="col-span-4 xl:col-span-6 sm:col-span-12">
            <div class="small-widget">
                <div class="card card-no-border">
                    <div class="card-body">
                        <h5 class="mb-0 f-w-600">{{ number_format($dau) }}</h5>
                        <span class="f-light f-12">DAU (dnes)</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-span-4 xl:col-span-6 sm:col-span-12">
            <div class="small-widget">
                <div class="card card-no-border">
                    <div class="card-body">
                        <h5 class="mb-0 f-w-600">{{ number_format($wau) }}</h5>
                        <span class="f-light f-12">WAU (7 dní)</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-span-4 xl:col-span-6 sm:col-span-12">
            <div class="small-widget">
                <div class="card card-no-border">
                    <div class="card-body">
                        <h5 class="mb-0 f-w-600">{{ number_format($mau) }}</h5>
                        <span class="f-light f-12">MAU (30 dní)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <x-panel.card title="Denně aktivní uživatelé — posledních 30 dní">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Datum</th>
                        <th class="text-right">Unikátní uživatelé</th>
                        <th>Graf</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($days as $row)
                    <tr>
                        <td class="f-12">{{ $row['day'] }}</td>
                        <td class="text-right f-w-600">{{ $row['users'] }}</td>
                        <td style="min-width:180px;">
                            @php $pct = $maxDau > 0 ? round($row['users'] / $maxDau * 100) : 0; @endphp
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-success"
                                     role="progressbar"
                                     style="width: {{ $pct }}%"
                                     aria-valuenow="{{ $pct }}"
                                     aria-valuemin="0"
                                     aria-valuemax="100">
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-panel.card>
</div>
@endsection
