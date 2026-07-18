@extends('layouts.panel')

@section('title', 'Automatizační pravidla')

@section('content')
<div class="container-fluid py-4">
    <div class="flex items-center justify-between mb-4">
        <h1 class="h4 mb-0">Automatizační pravidla</h1>
        <a href="{{ route('admin.automation.create') }}" class="btn btn-primary btn-sm">
            <i data-feather="plus" style="width:14px;height:14px"></i> Nové pravidlo
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
                        <th>Název</th>
                        <th>Spouštěč</th>
                        <th>Akce</th>
                        <th class="text-center">Stav</th>
                        <th class="text-right">Spuštění</th>
                        <th>Naposledy</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rules as $rule)
                        <tr>
                            <td class="small font-semibold">{{ $rule->name }}</td>
                            <td><span class="badge bg-secondary">{{ $rule->trigger }}</span></td>
                            <td><span class="badge bg-info text-dark">{{ $rule->action }}</span></td>
                            <td class="text-center">
                                <form action="{{ route('admin.automation.toggle', $rule) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-{{ $rule->is_active ? 'success' : 'outline-secondary' }} py-0 px-2">
                                        {{ $rule->is_active ? 'Aktivní' : 'Neaktivní' }}
                                    </button>
                                </form>
                            </td>
                            <td class="text-right small">{{ number_format($rule->run_count) }}</td>
                            <td class="small text-muted">{{ $rule->last_run_at?->diffForHumans() ?? '—' }}</td>
                            <td class="text-right">
                                <a href="{{ route('admin.automation.logs', $rule) }}" class="btn btn-xs btn-outline-secondary">Logy</a>
                                <a href="{{ route('admin.automation.edit', $rule) }}" class="btn btn-xs btn-outline-primary">Upravit</a>
                                <form action="{{ route('admin.automation.destroy', $rule) }}" method="POST" class="inline"
                                      onsubmit="return confirm('Smazat pravidlo?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-outline-danger">Smazat</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Žádná pravidla. <a href="{{ route('admin.automation.create') }}">Vytvořte první</a>.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
