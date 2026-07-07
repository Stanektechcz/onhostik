@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Zdraví zákazníků';
    $breadcrumbItems = [__('panel.nav.admin_crm') => '#', 'Zdraví zákazníků' => ''];
@endphp

@section('title', 'Zdraví zákazníků')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- Summary tiles --}}
    <div class="grid grid-cols-12 card-gap mb-3">
        <div class="col-span-4 sm:col-span-12">
            <x-panel.stat-widget label="Zdraví (≥80)" :value="$counts['healthy']"
                icon="check-circle" color="success" />
        </div>
        <div class="col-span-4 sm:col-span-12">
            <x-panel.stat-widget label="Pozor (50–79)" :value="$counts['warning']"
                icon="alert-circle" color="warning" />
        </div>
        <div class="col-span-4 sm:col-span-12">
            <x-panel.stat-widget label="Kritický (<50)" :value="$counts['critical']"
                icon="x-circle" color="danger" />
        </div>
    </div>

    <x-panel.card title="Zdraví zákazníků" subtitle="Skóre 0–100, seřazeno od nejhorší hodnoty">

        {{-- Tier filter --}}
        <div class="d-flex gap-2 mb-3 flex-wrap">
            <a href="{{ route('admin.customer-health-scores.index') }}"
               class="btn btn-sm {{ $tier === null ? 'btn-primary' : 'btn-outline-secondary' }}">
                Vše
            </a>
            <a href="{{ route('admin.customer-health-scores.index', ['tier' => 'healthy']) }}"
               class="btn btn-sm {{ $tier === 'healthy' ? 'btn-success' : 'btn-outline-success' }}">
                <i data-feather="check-circle" style="width:13px;height:13px;"></i> Zdraví
            </a>
            <a href="{{ route('admin.customer-health-scores.index', ['tier' => 'warning']) }}"
               class="btn btn-sm {{ $tier === 'warning' ? 'btn-warning' : 'btn-outline-warning' }}">
                <i data-feather="alert-circle" style="width:13px;height:13px;"></i> Pozor
            </a>
            <a href="{{ route('admin.customer-health-scores.index', ['tier' => 'critical']) }}"
               class="btn btn-sm {{ $tier === 'critical' ? 'btn-danger' : 'btn-outline-danger' }}">
                <i data-feather="x-circle" style="width:13px;height:13px;"></i> Kritický
            </a>
        </div>

        @if($customers->isEmpty())
            <div class="text-center py-5">
                <i data-feather="heart" style="width:40px;height:40px;" class="text-muted mb-3"></i>
                <p class="f-light">Zatím nejsou k dispozici žádná data. Spusťte <code>crm:compute-health-scores</code>.</p>
            </div>
        @else
            <x-panel.data-table :headers="['Zákazník', 'E-mail', 'Zdraví', 'Skóre', 'Slouby', 'Faktury', '']">
                @foreach($customers as $customer)
                    @php
                        $hs = $customer->health_score;
                        $hColor = match(true) {
                            $hs >= 80 => 'success',
                            $hs >= 50 => 'warning',
                            default   => 'danger',
                        };
                        $hLabel = match(true) {
                            $hs >= 80 => 'Zdravý',
                            $hs >= 50 => 'Pozor',
                            default   => 'Kritický',
                        };
                    @endphp
                    <tr>
                        <td class="f-w-600">
                            {{ $customer->company_name ?? $customer->user?->name ?? '—' }}
                        </td>
                        <td class="f-light f-12">{{ $customer->email }}</td>
                        <td>
                            <span class="badge badge-light-{{ $hColor }}">{{ $hLabel }}</span>
                        </td>
                        <td style="min-width:120px;">
                            <div class="d-flex align-items-center gap-2">
                                <span class="f-w-600 f-12 text-{{ $hColor }}" style="width:30px;">{{ $hs }}</span>
                                <div class="progress flex-grow-1" style="height:6px;">
                                    <div class="progress-bar bg-{{ $hColor }}" style="width:{{ $hs }}%"></div>
                                </div>
                            </div>
                        </td>
                        <td>{{ $customer->services_count }}</td>
                        <td>{{ $customer->invoices_count }}</td>
                        <td>
                            <a href="{{ route('admin.customers.show', $customer) }}"
                               class="btn btn-outline-primary btn-sm">Detail</a>
                        </td>
                    </tr>
                @endforeach
            </x-panel.data-table>
            {{ $customers->links() }}
        @endif
    </x-panel.card>
</div>
@endsection
