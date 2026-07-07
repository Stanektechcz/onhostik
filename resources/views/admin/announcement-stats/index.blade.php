@extends('layouts.panel')

@section('title', 'Statistiky oznámení')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <x-panel.card title="Statistiky systémových oznámení">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Název</th>
                        <th>Typ</th>
                        <th class="text-end">Odesláno</th>
                        <th class="text-end">Zavřeno uživateli</th>
                        <th>Stav</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($announcements as $a)
                    <tr>
                        <td>{{ $a->title }}</td>
                        <td><span class="{{ $a->typeBadgeClass() }}">{{ $a->typeLabel() }}</span></td>
                        <td class="text-end">{{ number_format($a->sent_count ?? 0) }}</td>
                        <td class="text-end">{{ number_format($a->dismissed_by_count) }}</td>
                        <td>
                            @if($a->isActive())
                                <span class="badge bg-success">Aktivní</span>
                            @else
                                <span class="badge bg-secondary">Neaktivní</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.announcement-stats.show', $a) }}" class="btn btn-sm btn-outline-primary">Detail</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Žádná oznámení.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $announcements->links() }}</div>
    </x-panel.card>
</div>
@endsection
