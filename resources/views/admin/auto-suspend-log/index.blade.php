@extends('layouts.panel')

@section('title', 'Log automatického pozastavení')

@section('content')
<x-panel.flash />

<x-panel.card title="Log automatického pozastavení">
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="alert alert-success mb-0">
                <strong>Aktivní pravidla:</strong> {{ $activeCount }}
            </div>
        </div>
        <div class="col-md-6">
            <div class="alert alert-secondary mb-0">
                <strong>Neaktivní:</strong> {{ $inactiveCount }}
            </div>
        </div>
    </div>

    @if($rules->isEmpty())
        <p class="text-muted">Žádná pravidla.</p>
    @else
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Název</th>
                        <th>Spouštěč</th>
                        <th>Práh</th>
                        <th>Aktivní</th>
                        <th>Popis</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rules as $rule)
                        <tr>
                            <td>{{ $rule->name }}</td>
                            <td>
                                @php
                                    $triggerClass = match($rule->trigger ?? '') {
                                        'overdue_days'    => 'info',
                                        'usage_percent'   => 'warning',
                                        'failed_payments' => 'danger',
                                        default           => 'secondary',
                                    };
                                @endphp
                                <span class="badge bg-{{ $triggerClass }}">{{ $rule->trigger ?? '—' }}</span>
                            </td>
                            <td>{{ $rule->threshold ?? '—' }}</td>
                            <td>
                                @if($rule->is_active)
                                    <span class="text-success" title="Aktivní">&#10003;</span>
                                @else
                                    <span class="text-danger" title="Neaktivní">&#10007;</span>
                                @endif
                            </td>
                            <td>{{ $rule->description ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-panel.card>
@endsection
