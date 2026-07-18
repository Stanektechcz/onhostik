@extends('layouts.panel')
@section('title', 'DNS Zóny')

@section('content')
<div class="container-fluid">
    <div class="page-header">
        <div class="grid grid-cols-12 items-center">
            <div class="col-span-12 sm:col-span-6">
                <h3>DNS Zóny</h3>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Přehled</a></li>
                    <li class="breadcrumb-item active">DNS Zóny</li>
                </ol>
            </div>
            <div class="col-span-12 sm:col-span-6 text-right">
                <span class="badge bg-primary fs-6">{{ $zones->total() }} zón</span>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Doména</th>
                            <th>Zákazník</th>
                            <th>Stav</th>
                            <th>Záznamy</th>
                            <th>Přidáno</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($zones as $zone)
                    <tr>
                        <td class="font-monospace font-semibold">{{ $zone->domain }}</td>
                        <td>
                            @if ($zone->customer)
                                <a href="{{ route('admin.customers.show', $zone->customer) }}" class="text-decoration-none">
                                    {{ $zone->customer->email }}
                                </a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-{{ $zone->status->color() === 'success' ? 'success' : ($zone->status->color() === 'danger' ? 'danger' : 'warning') }}">
                                {{ $zone->status->label() }}
                            </span>
                        </td>
                        <td class="text-muted">{{ $zone->records_count }}</td>
                        <td class="text-muted f-12">{{ $zone->created_at->format('d.m.Y H:i') }}</td>
                        <td class="text-right">
                            <a href="{{ route('admin.dns.show', $zone) }}" class="btn btn-outline-primary btn-sm">
                                <i data-feather="eye" style="width:13px;height:13px"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Žádné DNS zóny.</td>
                    </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($zones->hasPages())
        <div class="card-footer">
            {{ $zones->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
