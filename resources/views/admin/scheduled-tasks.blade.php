@extends('layouts.panel')

@section('title', 'Fronta úloh')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="f-w-700 {{ $failedCount > 0 ? 'text-danger' : 'text-success' }}">{{ $failedCount }}</h3>
                    <p class="text-muted mb-0">Selhané úlohy</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="f-w-700">{{ $pendingCount }}</h3>
                    <p class="text-muted mb-0">Čekající úlohy</p>
                </div>
            </div>
        </div>
    </div>

    <x-panel.card title="Selhané úlohy">
        @if($failedJobs->isEmpty())
            <p class="text-success">Žádné selhané úlohy.</p>
        @else
        <div class="mb-2">
            <form method="POST" action="{{ route('admin.scheduled-tasks.clear') }}" onsubmit="return confirm('Smazat všechny selhané úlohy?')">
                @csrf
                @method('DELETE')
                <button class="btn btn-xs btn-outline-danger">Smazat vše</button>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Selháno</th>
                        <th>Fronta</th>
                        <th>Třída úlohy</th>
                        <th>Chyba</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($failedJobs as $job)
                    @php
                        $payload   = json_decode($job->payload, true);
                        $jobClass  = $payload['displayName'] ?? ($payload['job'] ?? 'Unknown');
                        $exception = mb_substr($job->exception ?? '', 0, 200);
                    @endphp
                    <tr>
                        <td class="f-12 text-muted">{{ \Carbon\Carbon::parse($job->failed_at)->format('d.m.Y H:i') }}</td>
                        <td class="f-12">{{ $job->queue }}</td>
                        <td class="f-12 f-w-500">{{ class_basename($jobClass) }}</td>
                        <td class="f-11 text-danger" style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $exception }}">{{ $exception }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.scheduled-tasks.retry', $job->id) }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-xs btn-outline-secondary">Smazat</button>
                            </form>
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
