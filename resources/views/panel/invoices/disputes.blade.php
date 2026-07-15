@extends('layouts.panel')

@section('title', 'Moje námitky k fakturám')

@section('content')
<div class="container py-5">
    <h2 class="mb-4">Námitky k fakturám</h2>

    @if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if($errors->any())
    <div class="alert alert-danger">{{ $errors->first('dispute') }}</div>
    @endif

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Faktura</th>
                            <th>Důvod</th>
                            <th>Stav</th>
                            <th>Vyjádření</th>
                            <th>Datum</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($disputes as $d)
                        <tr>
                            <td>{{ $d->invoice?->number }}</td>
                            <td class="text-muted small">{{ Str::limit($d->reason, 80) }}</td>
                            <td>
                                @php $badges = ['open'=>'warning text-dark','under_review'=>'info text-dark','resolved'=>'success','rejected'=>'danger'] @endphp
                                <span class="badge bg-{{ $badges[$d->status] ?? 'secondary' }}">{{ $d->status }}</span>
                            </td>
                            <td class="text-muted small">{{ Str::limit($d->admin_note, 80) ?? '—' }}</td>
                            <td class="text-muted small">{{ $d->created_at?->format('d.m.Y') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Žádné námitky.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="mt-3">{{ $disputes->links() }}</div>
</div>
@endsection
