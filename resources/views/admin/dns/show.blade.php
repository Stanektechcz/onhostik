@extends('layouts.panel')
@section('title', $zone->domain . ' — DNS')

@section('content')
<div class="container-fluid">
    <div class="page-header">
        <div class="grid grid-cols-12 items-center">
            <div class="col-span-12 sm:col-span-8">
                <h3 class="font-monospace">{{ $zone->domain }}</h3>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Přehled</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.dns.index') }}">DNS Zóny</a></li>
                    <li class="breadcrumb-item active">{{ $zone->domain }}</li>
                </ol>
            </div>
            <div class="col-span-12 sm:col-span-4 text-right">
                <span class="badge bg-{{ $zone->status->color() === 'success' ? 'success' : ($zone->status->color() === 'danger' ? 'danger' : 'warning') }} fs-6">
                    {{ $zone->status->label() }}
                </span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-3 mb-3">
        <div class="col-span-12 md:col-span-4">
            <div class="card h-full">
                <div class="card-body">
                    <p class="text-muted f-12 mb-1">Zákazník</p>
                    <p class="font-semibold mb-0">
                        @if ($zone->customer)
                            <a href="{{ route('admin.customers.show', $zone->customer) }}">{{ $zone->customer->email }}</a>
                        @else
                            —
                        @endif
                    </p>
                </div>
            </div>
        </div>
        <div class="col-span-12 md:col-span-4">
            <div class="card h-full">
                <div class="card-body">
                    <p class="text-muted f-12 mb-1">Provider</p>
                    <p class="font-semibold mb-0">{{ strtoupper($zone->provider) }}</p>
                </div>
            </div>
        </div>
        <div class="col-span-12 md:col-span-4">
            <div class="card h-full">
                <div class="card-body">
                    <p class="text-muted f-12 mb-1">Počet záznamů</p>
                    <p class="font-semibold mb-0">{{ $records->count() }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">DNS záznamy</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 f-13">
                    <thead class="table-light">
                        <tr>
                            <th>Typ</th>
                            <th>Název</th>
                            <th>Obsah</th>
                            <th>TTL</th>
                            <th>Priorita</th>
                            <th>Přidáno</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($records as $record)
                    <tr>
                        <td>
                            <span class="badge bg-secondary f-11">{{ $record->type->value }}</span>
                        </td>
                        <td class="font-monospace f-12">{{ $record->name }}</td>
                        <td class="font-monospace f-12 text-break" style="max-width:320px">{{ $record->content }}</td>
                        <td class="text-muted f-12">{{ number_format($record->ttl) }}</td>
                        <td class="text-muted f-12">{{ $record->priority !== null ? $record->priority : '—' }}</td>
                        <td class="text-muted f-12">{{ $record->created_at->format('d.m.Y') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Žádné záznamy.</td>
                    </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
