@extends('layouts.panel')

@section('title', 'Monitoring SSL certifikátů')

@section('content')
<x-panel.flash />

<x-panel.card title="Monitoring SSL certifikátů">
    <div class="grid grid-cols-12 gap-3 mb-4">
        <div class="col-span-12 md:col-span-6">
            <div class="alert alert-warning mb-0">
                <strong>Brzy expirující:</strong> {{ $expiringCount }}
            </div>
        </div>
        <div class="col-span-12 md:col-span-6">
            <div class="alert alert-danger mb-0">
                <strong>Expirovaná:</strong> {{ $expiredCount }}
            </div>
        </div>
    </div>

    <h5 class="mb-3">Poslední kontroly</h5>

    @if($recentChecks->isEmpty())
        <p class="text-muted">Žádné záznamy.</p>
    @else
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Doména</th>
                        <th>Služba ID</th>
                        <th>Stav</th>
                        <th>Platnost do</th>
                        <th>Zkontrolováno</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentChecks as $check)
                        <tr>
                            <td>{{ $check->domain }}</td>
                            <td>{{ $check->service_id }}</td>
                            <td>
                                @php
                                    $badgeClass = match($check->status) {
                                        'valid'          => 'success',
                                        'expiring_soon'  => 'warning',
                                        'expired'        => 'danger',
                                        'invalid'        => 'danger',
                                        default          => 'secondary',
                                    };
                                @endphp
                                <span class="badge bg-{{ $badgeClass }}">{{ $check->status }}</span>
                            </td>
                            <td>{{ $check->expires_at ? $check->expires_at->format('d.m.Y') : '—' }}</td>
                            <td>{{ $check->checked_at ? $check->checked_at->format('d.m.Y H:i') : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-panel.card>
@endsection
