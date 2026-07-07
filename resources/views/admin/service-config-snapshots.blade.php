@extends('layouts.panel')

@section('title', 'Snapshoty konfigurace — ' . $service->label)

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 card-gap">
        <div class="col-span-4 xl:col-span-12">
            <x-panel.card title="Uložit snapshot">
                <p class="text-muted f-12">Aktuální konfigurace: <code>{{ json_encode($service->resources ?? []) }}</code></p>
                <form method="POST" action="{{ route('admin.services.config-snapshots.store', $service) }}">
                    @csrf
                    <div class="mb-2">
                        <input type="text" name="reason" class="form-control form-control-sm" placeholder="Důvod (volitelné)" maxlength="200">
                    </div>
                    <button class="btn btn-primary btn-sm">Uložit snapshot</button>
                </form>
            </x-panel.card>
        </div>

        <div class="col-span-8 xl:col-span-12">
            <x-panel.card title="Historie snapshotů">
                @if($snapshots->isEmpty())
                    <p class="text-muted">Žádné snapshoty.</p>
                @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr><th>Datum</th><th>Admin</th><th>Důvod</th><th>Konfigurace</th></tr>
                        </thead>
                        <tbody>
                            @foreach($snapshots as $snap)
                            <tr>
                                <td class="f-12">{{ $snap->created_at->format('d.m.Y H:i') }}</td>
                                <td>{{ $snap->creator?->name ?? '—' }}</td>
                                <td class="f-12 text-muted">{{ $snap->reason ?? '—' }}</td>
                                <td><code class="f-11">{{ Str::limit(json_encode($snap->config), 80) }}</code></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
