@extends('layouts.panel')
@section('title', 'Uptime monitoring služeb')
@section('content')
<div class="container-fluid">
    <x-panel.flash />
    <x-panel.card title="Přehled uptime — všechny služby">
        @if($services->isEmpty())
            <p class="text-muted">Žádné služby.</p>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th>Služba</th><th>Zákazník</th><th class="text-right">Výpadky (24h)</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach($services as $s)
                    <tr>
                        <td>{{ $s->label }}</td>
                        <td>{{ $s->customer?->company_name }}</td>
                        <td class="text-right">
                            @if(($s->down_checks ?? 0) > 0)
                                <span class="badge bg-danger">{{ $s->down_checks }} výpadků</span>
                            @else
                                <span class="badge bg-success">OK</span>
                            @endif
                        </td>
                        <td><a href="{{ route('admin.service-uptime.show', $s) }}" class="btn btn-sm btn-outline-primary">Detail</a></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </x-panel.card>
</div>
@endsection
