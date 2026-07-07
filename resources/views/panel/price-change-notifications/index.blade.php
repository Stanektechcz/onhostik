@extends('layouts.panel')

@section('title', 'Oznámení o změnách cen')

@section('content')
<x-panel.flash />

<x-panel.card title="Oznámení o změnách cen">
    @if ($notifications->isEmpty())
        <p class="text-muted text-center py-3">Žádná oznámení o změnách cen.</p>
    @else
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>Název</th>
                        <th>Platné od</th>
                        <th>Popis</th>
                        <th>Datum odeslání</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($notifications as $notification)
                        <tr>
                            <td>{{ $notification->title }}</td>
                            <td class="text-nowrap">
                                {{ $notification->effective_from ? \Carbon\Carbon::parse($notification->effective_from)->format('d.m.Y') : '—' }}
                            </td>
                            <td>{{ \Illuminate\Support\Str::limit($notification->body, 120) }}</td>
                            <td class="text-nowrap">
                                {{ $notification->sent_at ? \Carbon\Carbon::parse($notification->sent_at)->format('d.m.Y H:i') : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($notifications->hasPages())
            <div class="mt-3">
                {{ $notifications->links() }}
            </div>
        @endif
    @endif
</x-panel.card>
@endsection
