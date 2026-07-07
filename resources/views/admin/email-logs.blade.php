@extends('layouts.panel')

@section('title', 'Log odeslaných e-mailů')

@section('content')
<div class="container-fluid">
    <x-panel.card title="Log odeslaných e-mailů">
        <form method="GET" class="mb-3 d-flex gap-2">
            <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Hledat adresu nebo předmět..." style="max-width:300px">
            <button class="btn btn-sm btn-outline-secondary">Hledat</button>
        </form>

        @if($logs->isEmpty())
            <p class="text-muted">Žádné záznamy.</p>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Datum</th>
                        <th>Příjemce</th>
                        <th>Předmět</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($logs as $log)
                    <tr>
                        <td class="f-12 text-muted">{{ $log->created_at->format('d.m.Y H:i') }}</td>
                        <td>{{ $log->to_address }}</td>
                        <td class="f-12">{{ $log->subject }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $logs->links() }}</div>
        @endif
    </x-panel.card>
</div>
@endsection
