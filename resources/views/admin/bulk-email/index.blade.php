@extends('layouts.panel')

@section('title', 'Hromadné emaily')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="mb-3">
        <a href="{{ route('admin.bulk-email-campaigns.create') }}" class="btn btn-primary">+ Nová kampaň</a>
    </div>

    <x-panel.card title="Email kampaně">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Předmět</th>
                        <th>Segment</th>
                        <th class="text-end">Příjemců</th>
                        <th class="text-end">Odesláno</th>
                        <th>Stav</th>
                        <th>Vytvořeno</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($campaigns as $c)
                    <tr>
                        <td>{{ $c->subject }}</td>
                        <td class="text-muted">{{ $c->target_segment ?? 'Všichni' }}</td>
                        <td class="text-end">{{ number_format($c->recipient_count) }}</td>
                        <td class="text-end">{{ number_format($c->sent_count) }}</td>
                        <td>
                            @php $badges = ['draft'=>'secondary','sending'=>'warning text-dark','sent'=>'success','failed'=>'danger'] @endphp
                            <span class="badge bg-{{ $badges[$c->status] ?? 'secondary' }}">{{ $c->status }}</span>
                        </td>
                        <td class="text-muted small">{{ $c->created_at?->format('d.m.Y H:i') }}</td>
                        <td>
                            @if($c->status === 'draft')
                            <form method="POST" action="{{ route('admin.bulk-email-campaigns.destroy', $c) }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Smazat</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Žádné kampaně.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $campaigns->links() }}</div>
    </x-panel.card>
</div>
@endsection
