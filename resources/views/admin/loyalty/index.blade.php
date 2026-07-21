@extends('layouts.panel')

@section('title', 'Věrnostní milníky')

@section('content')
<div class="container-fluid py-4">

    <div class="flex justify-between items-center mb-4">
        <h1 class="h4 mb-0">Věrnostní milníky & Odměny</h1>
        <a href="{{ route('admin.loyalty.create') }}" class="btn btn-primary btn-sm">+ Nový milník</a>
    </div>

    @if(session('status'))
        <div class="alert alert-success py-2">{{ session('status') }}</div>
    @endif

    {{-- Milestones table --}}
    <div class="card mb-4">
        <div class="card-header"><strong>Definované milníky</strong></div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Název</th>
                        <th>Podmínka</th>
                        <th>Odměna</th>
                        <th>Uděleno zákazníkům</th>
                        <th>Aktivní</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($milestones as $m)
                    <tr>
                        <td class="text-muted small">{{ $m->sort_order }}</td>
                        <td>
                            <strong>{{ $m->name }}</strong>
                            @if($m->description)
                                <div class="text-muted small">{{ $m->description }}</div>
                            @endif
                        </td>
                        <td class="small">{{ $m->triggerLabel() }}</td>
                        <td class="small">{{ $m->rewardLabel() }}</td>
                        <td>
                            <span class="badge bg-info">{{ $m->rewards_count }}</span>
                        </td>
                        <td>
                            @if($m->is_active)
                                <span class="badge bg-success">Ano</span>
                            @else
                                <span class="badge bg-secondary">Ne</span>
                            @endif
                        </td>
                        <td class="text-right">
                            <a href="{{ route('admin.loyalty.edit', $m) }}" class="btn btn-xs btn-outline-secondary">Upravit</a>
                            <form action="{{ route('admin.loyalty.destroy', $m) }}" method="POST" class="inline"
                                  data-confirm="Smazat milník?">
                                @csrf @method('DELETE')
                                <button class="btn btn-xs btn-outline-danger">Smazat</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-muted text-center py-3">Žádné milníky.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Manual check --}}
    <div class="card mb-4">
        <div class="card-header"><strong>Ruční kontrola zákazníka</strong></div>
        <div class="card-body">
            <form action="{{ route('admin.loyalty.check') }}" method="POST" class="grid grid-cols-12 gap-2 items-end">
                @csrf
                <div class="col-auto">
                    <label class="form-label small">ID zákazníka</label>
                    <input type="number" name="customer_id" class="form-control form-control-sm" required min="1">
                </div>
                <div class="col-auto">
                    <button class="btn btn-sm btn-outline-primary">Zkontrolovat & udělit</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Recent rewards --}}
    <div class="card">
        <div class="card-header"><strong>Poslední udělené odměny</strong></div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Zákazník</th>
                        <th>Milník</th>
                        <th>Uděleno</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentRewards as $r)
                    <tr>
                        <td>{{ $r->customer?->user->name ?? '—' }}</td>
                        <td>{{ $r->milestone?->name ?? '—' }}</td>
                        <td class="small text-muted">{{ $r->awarded_at?->format('d.m.Y H:i') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="text-muted text-center py-3">Zatím žádné odměny.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
