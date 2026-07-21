@extends('layouts.panel')

@section('title', 'Finanční exporty')

@section('content')
<div class="container-fluid py-4">

    <div class="flex justify-between items-center mb-4">
        <h1 class="h4 mb-0">Finanční exporty</h1>
        <a href="{{ route('admin.exports.create') }}" class="btn btn-primary btn-sm">+ Nový export</a>
    </div>

    @if(session('status'))
        <div class="alert alert-success py-2">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger py-2">{{ session('error') }}</div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Formát</th>
                        <th>Období</th>
                        <th>Stav</th>
                        <th>Řádků</th>
                        <th>Vytvořil</th>
                        <th>Datum</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($jobs as $job)
                    <tr>
                        <td><strong>{{ $job->formatLabel() }}</strong></td>
                        <td class="small">
                            @if($job->date_from || $job->date_to)
                                {{ $job->date_from?->format('d.m.Y') ?? '—' }}
                                –
                                {{ $job->date_to?->format('d.m.Y') ?? '—' }}
                            @else
                                <span class="text-muted">Vše</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $job->statusBadgeClass() }}">{{ $job->statusLabel() }}</span>
                            @if($job->error_message)
                                <div class="text-danger small">{{ $job->error_message }}</div>
                            @endif
                        </td>
                        <td class="small text-muted">{{ $job->row_count ?? '—' }}</td>
                        <td class="small text-muted">{{ $job->creator?->name ?? '—' }}</td>
                        <td class="small text-muted">{{ $job->created_at->format('d.m.Y H:i') }}</td>
                        <td class="text-right text-nowrap">
                            @if($job->isDownloadable())
                                <a href="{{ route('admin.exports.download', $job) }}"
                                   class="btn btn-xs btn-outline-success">Stáhnout</a>
                            @endif
                            <form action="{{ route('admin.exports.destroy', $job) }}" method="POST" class="inline"
                                  data-confirm="Smazat export?">
                                @csrf @method('DELETE')
                                <button class="btn btn-xs btn-outline-danger">Smazat</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-muted text-center py-3">Žádné exporty.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $jobs->links() }}</div>

</div>
@endsection
