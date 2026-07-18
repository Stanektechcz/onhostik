@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Průzkum odchodů';
    $breadcrumbItems = ['Admin' => route('admin.dashboard'), 'Průzkum odchodů' => ''];
@endphp

@section('title', 'Důvody rušení služeb')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- Summary cards --}}
    <div class="grid grid-cols-12 gap-3 mb-4">
        <div class="col-span-12 sm:col-span-6 col-span-12 xl:col-span-3">
            <div class="card widget-flat text-center">
                <div class="card-body py-3">
                    <p class="text-muted f-12 mb-1">Celkem průzkumů</p>
                    <h3 class="mb-0">{{ $total }}</h3>
                </div>
            </div>
        </div>
        @foreach($byCancelReason->take(3) as $row)
        <div class="col-span-12 sm:col-span-6 col-span-12 xl:col-span-3">
            <div class="card widget-flat text-center">
                <div class="card-body py-3">
                    <p class="text-muted f-12 mb-1">{{ $reasonLabels[$row->reason] ?? $row->reason }}</p>
                    <h3 class="mb-0">{{ $row->total }}</h3>
                    @if($total > 0)
                        <span class="f-12 text-muted">{{ round($row->total / $total * 100) }}&nbsp;%</span>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>

    @if($total === 0)
        <div class="alert alert-light-secondary text-center py-4">
            <i data-feather="clipboard" style="width:32px;height:32px" class="mb-2 block mx-auto text-muted"></i>
            <p class="mb-0 text-muted">Zatím nebyl zaznamenán žádný průzkum odchodu.</p>
        </div>
    @else
        <div class="grid grid-cols-12 gap-3">
            <div class="col-span-12 lg:col-span-4">
                <div class="card h-full">
                    <div class="card-header py-3">
                        <h6 class="mb-0">Rozložení důvodů</h6>
                    </div>
                    <div class="card-body">
                        @foreach($byCancelReason as $row)
                            @php $pct = $total > 0 ? round($row->total / $total * 100) : 0; @endphp
                            <div class="mb-3">
                                <div class="flex justify-between items-center mb-1">
                                    <span class="f-12">{{ $reasonLabels[$row->reason] ?? $row->reason }}</span>
                                    <span class="f-12 f-w-600">{{ $row->total }} <span class="text-muted f-w-400">({{ $pct }}&nbsp;%)</span></span>
                                </div>
                                <div class="progress" style="height:6px;">
                                    <div class="progress-bar bg-primary" role="progressbar"
                                         style="width:{{ $pct }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-8">
                <div class="card">
                    <div class="card-header py-3">
                        <h6 class="mb-0">Posledních 20 průzkumů</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Datum</th>
                                        <th>Služba</th>
                                        <th>Zákazník</th>
                                        <th>Důvod</th>
                                        <th>Komentář</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recent as $entry)
                                        <tr>
                                            <td class="f-12 text-muted">{{ $entry->created_at->format('d.m.Y') }}</td>
                                            <td class="f-12">
                                                @if($entry->service)
                                                    <a href="{{ route('admin.services.show', $entry->service) }}">{{ $entry->service->label }}</a>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="f-12">
                                                @if($entry->user)
                                                    <a href="{{ route('admin.customers.show', $entry->user) }}">{{ $entry->user->name }}</a>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge badge-light-secondary f-10">{{ $entry->reasonLabel() }}</span>
                                            </td>
                                            <td class="f-12 text-muted" style="max-width:220px;">
                                                {{ $entry->feedback ? Str::limit($entry->feedback, 80) : '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
