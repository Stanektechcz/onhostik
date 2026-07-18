@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Onboarding zákazníků';
    $breadcrumbItems = ['Zákazníci' => route('admin.customers.index'), 'Onboarding' => ''];
@endphp

@section('title', 'Onboarding zákazníků')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- Summary cards --}}
    <div class="grid grid-cols-12 gap-3 mb-3">
        <div class="col-span-12 md:col-span-3">
            <x-panel.stat-widget label="Celkem zákazníků" :value="$stats['total']" icon="users" />
        </div>
        <div class="col-span-12 md:col-span-3">
            <x-panel.stat-widget label="Dokončili onboarding" :value="$stats['fullyComplete']" icon="check-circle" color="success" />
        </div>
        <div class="col-span-12 md:col-span-3">
            <x-panel.stat-widget label="Rozepsáno" :value="$stats['inProgress']" icon="loader" color="warning" />
        </div>
        <div class="col-span-12 md:col-span-3">
            <x-panel.stat-widget label="Nezačali" :value="$stats['notStarted']" icon="user-x" color="secondary" />
        </div>
    </div>

    <div class="grid grid-cols-12 gap-3">
        {{-- Step completion rates --}}
        <div class="col-span-12 lg:col-span-4">
            <div class="card">
                <div class="card-header card-no-border"><h5>Dokončení kroků</h5></div>
                <div class="card-body">
                    @forelse($stats['stepRates'] as $step => $pct)
                    <div class="mb-3">
                        <div class="flex justify-between mb-1">
                            <span class="f-13">{{ $stepLabels[$step] ?? $step }}</span>
                            <span class="f-12 f-light">{{ $pct }}%</span>
                        </div>
                        <div class="progress" style="height:8px;">
                            <div class="progress-bar {{ $pct >= 75 ? 'bg-success' : ($pct >= 40 ? 'bg-warning' : 'bg-danger') }}"
                                 style="width:{{ $pct }}%"></div>
                        </div>
                    </div>
                    @empty
                        <p class="f-light text-center">Žádná data k zobrazení.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Incomplete customers --}}
        <div class="col-span-12 lg:col-span-8">
            <div class="card">
                <div class="card-header card-no-border">
                    <h5>Zákazníci bez dokončeného onboardingu</h5>
                </div>
                <div class="card-body pt-0">
                    @if($incomplete->isEmpty())
                        <p class="text-center f-light py-4">Všichni zákazníci dokončili onboarding.</p>
                    @else
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>Zákazník</th>
                                    <th>Email</th>
                                    <th>Registrace</th>
                                    <th>Onboarding uzavřen</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($incomplete as $customer)
                                <tr>
                                    <td class="f-w-500">{{ $customer->company_name ?: ($customer->user?->name ?? '—') }}</td>
                                    <td class="f-12 f-light">{{ $customer->user?->email ?? '—' }}</td>
                                    <td class="f-12">{{ $customer->created_at->format('d.m.Y') }}</td>
                                    <td class="f-12">
                                        @if($customer->onboarding_completed_at)
                                            {{ $customer->onboarding_completed_at->format('d.m.Y') }}
                                        @else
                                            <span class="badge badge-light-warning f-10">Nedokončeno</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.customers.show', $customer) }}"
                                           class="btn btn-outline-secondary btn-xs">Detail</a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-2">{{ $incomplete->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
