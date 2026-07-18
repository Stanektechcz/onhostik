@extends('layouts.panel')

@section('title', 'Service Add-ons')

@section('content')
<div class="container-fluid py-4">
    <div class="flex items-center justify-between mb-4">
        <h1 class="h4 mb-0">Service Add-ons & Upsell</h1>
        <a href="{{ route('admin.service-addons.create') }}" class="btn btn-primary btn-sm">
            <i data-feather="plus" style="width:14px;height:14px"></i> Nový doplněk
        </a>
    </div>

    @if(session('status'))
        <div class="alert alert-success py-2">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Název</th>
                        <th>Slug</th>
                        <th class="text-right">Cena/měs.</th>
                        <th class="text-right">Aktivací</th>
                        <th class="text-center">Stav</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($addons as $addon)
                        <tr>
                            <td class="small text-muted">{{ $addon->sort_order }}</td>
                            <td class="small font-semibold">{{ $addon->name }}</td>
                            <td class="small font-monospace text-muted">{{ $addon->slug }}</td>
                            <td class="text-right small">{{ number_format($addon->price_czk / 100, 2) }} Kč</td>
                            <td class="text-right small">{{ $addon->subscriptions_count }}</td>
                            <td class="text-center">
                                <span class="badge bg-{{ $addon->is_active ? 'success' : 'secondary' }}">
                                    {{ $addon->is_active ? 'Aktivní' : 'Neaktivní' }}
                                </span>
                            </td>
                            <td class="text-right">
                                <a href="{{ route('admin.service-addons.edit', $addon) }}" class="btn btn-xs btn-outline-primary">Upravit</a>
                                <form action="{{ route('admin.service-addons.destroy', $addon) }}" method="POST" class="inline"
                                      onsubmit="return confirm('Smazat?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-outline-danger">Smazat</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Žádné doplňky. <a href="{{ route('admin.service-addons.create') }}">Vytvořte první</a>.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
