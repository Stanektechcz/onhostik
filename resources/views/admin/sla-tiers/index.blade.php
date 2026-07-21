@extends('layouts.panel')

@section('title', 'SLA Tiery')

@section('content')
<div class="container-fluid py-4">

    <div class="flex justify-between items-center mb-4">
        <h1 class="h4 mb-0">SLA Tiery</h1>
        <a href="{{ route('admin.sla-tiers.create') }}" class="btn btn-sm btn-primary">+ Nový tier</a>
    </div>

    @if(session('status'))
        <div class="alert alert-success py-2">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Název</th>
                        <th>Slug</th>
                        <th>Uptime SLA</th>
                        <th>Odezva podpory</th>
                        <th>Čas řešení</th>
                        <th>Kredit / hod</th>
                        <th>Max. kredit</th>
                        <th>Stav</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tiers as $tier)
                    <tr>
                        <td class="font-semibold">{{ $tier->name }}</td>
                        <td class="text-muted small font-monospace">{{ $tier->slug }}</td>
                        <td>{{ $tier->uptimeLabel() }}</td>
                        <td>{{ $tier->response_time_minutes }} min</td>
                        <td>{{ $tier->resolution_time_hours }} hod</td>
                        <td>{{ $tier->credit_percent_per_hour }} %</td>
                        <td>{{ $tier->max_credit_percent }} %</td>
                        <td>
                            @if($tier->is_active)
                                <span class="badge bg-success">Aktivní</span>
                            @else
                                <span class="badge bg-secondary">Neaktivní</span>
                            @endif
                        </td>
                        <td class="text-right text-nowrap">
                            <a href="{{ route('admin.sla-tiers.edit', $tier) }}" class="btn btn-xs btn-outline-secondary">Upravit</a>
                            <form action="{{ route('admin.sla-tiers.destroy', $tier) }}" method="POST" class="inline"
                                  data-confirm="Smazat tier?">
                                @csrf @method('DELETE')
                                <button class="btn btn-xs btn-outline-danger">Smazat</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-muted text-center py-3">Žádné SLA tiery.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
