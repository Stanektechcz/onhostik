@extends('layouts.panel')

@section('title', 'Žádosti o pozastavení fakturace')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <x-panel.card title="Žádosti o pozastavení fakturace služeb">
        @if($pending->isEmpty())
            <p class="text-muted">Žádné čekající žádosti.</p>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Služba</th>
                        <th>Zákazník</th>
                        <th>Žádost podána</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pending as $service)
                    <tr>
                        <td class="f-w-500">{{ $service->label }}</td>
                        <td>{{ $service->customer?->display_name ?? '—' }}</td>
                        <td class="f-12">{{ $service->billing_pause_requested_at->format('d.m.Y H:i') }}</td>
                        <td>
                            <div class="flex gap-2">
                                <form method="POST" action="{{ route('admin.service-billing-pause.approve', $service) }}" class="flex gap-2 items-center">
                                    @csrf
                                    <input type="date" name="paused_until" class="form-control form-control-sm" style="width:160px"
                                           min="{{ now()->addDay()->toDateString() }}" required>
                                    <button class="btn btn-xs btn-success">Schválit</button>
                                </form>
                                <form method="POST" action="{{ route('admin.service-billing-pause.reject', $service) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger">Zamítnout</button>
                                </form>
                            </div>
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
