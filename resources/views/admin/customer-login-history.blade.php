@extends('layouts.panel')

@php($breadcrumbTitle = 'Historie přihlášení')
@php($breadcrumbItems = [
    __('panel.nav.admin_customers') => route('admin.customers.index'),
    ($customer->company_name ?: $customer->user?->name ?? $customer->email) => route('admin.customers.show', $customer),
    'Historie přihlášení' => '',
])

@section('title', 'Historie přihlášení – ' . ($customer->company_name ?: $customer->email))

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="flex items-center justify-between mb-3">
        <h1 class="h4 mb-0">
            <i data-feather="log-in" style="width:20px;height:20px" class="me-1"></i>
            Historie přihlášení
            <small class="text-muted f-14 ms-1">– {{ $customer->company_name ?: $customer->email }}</small>
        </h1>
        <a href="{{ route('admin.customers.show', $customer) }}" class="btn btn-outline-secondary btn-sm">
            <i data-feather="arrow-left" style="width:13px;height:13px"></i> Zpět na zákazníka
        </a>
    </div>

    <x-panel.card title="Přihlášení">
        @if($history->isEmpty())
            <div class="text-center py-5">
                <i data-feather="clock" style="width:40px;height:40px;" class="text-muted mb-3"></i>
                <p class="text-muted">Žádná přihlášení nebyla zaznamenána.</p>
            </div>
        @else
            <x-panel.data-table :headers="['Datum a čas', 'IP adresa', 'Prohlížeč / User-Agent']">
                @foreach($history as $entry)
                    <tr>
                        <td class="f-12 text-nowrap">
                            <i data-feather="clock" style="width:11px;height:11px" class="text-muted me-1"></i>
                            {{ $entry->created_at->format('d.m.Y H:i:s') }}
                        </td>
                        <td class="f-12">
                            @if($entry->ip_address)
                                <code class="f-12">{{ $entry->ip_address }}</code>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="f-12 truncate" style="max-width:400px" title="{{ $entry->user_agent }}">
                            {{ $entry->user_agent ? Str::limit($entry->user_agent, 80) : '—' }}
                        </td>
                    </tr>
                @endforeach
            </x-panel.data-table>

            @if($history instanceof \Illuminate\Pagination\LengthAwarePaginator && $history->hasPages())
                <div class="mt-3">
                    {{ $history->links() }}
                </div>
            @endif
        @endif
    </x-panel.card>
</div>
@endsection
