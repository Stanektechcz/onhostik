@extends('layouts.panel')

@section('title', 'Logy pravidla: ' . $rule->name)

@section('content')
<div class="container-fluid py-4">
    <div class="flex items-center mb-4">
        <a href="{{ route('admin.automation.index') }}" class="btn btn-sm btn-outline-secondary me-3">← Zpět</a>
        <h1 class="h4 mb-0">Logy: {{ $rule->name }}</h1>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Čas</th>
                        <th class="text-center">Výsledek</th>
                        <th>Entita</th>
                        <th>Zpráva</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td class="small text-muted text-nowrap">{{ $log->created_at?->format('d.m.Y H:i:s') }}</td>
                            <td class="text-center">
                                <span class="badge bg-{{ match($log->outcome) { 'ok' => 'success', 'skipped' => 'secondary', default => 'danger' } }}">
                                    {{ $log->outcome }}
                                </span>
                            </td>
                            <td class="small text-muted">
                                @if($log->entity_type)
                                    {{ class_basename($log->entity_type) }} #{{ $log->entity_id }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="small">{{ $log->message ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">Žádné záznamy</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
            <div class="card-footer">{{ $logs->links() }}</div>
        @endif
    </div>
</div>
@endsection
