@extends('layouts.panel')
@section('title', 'Komentáře ke článkům KB')
@section('content')
<div class="container-fluid">
    <x-panel.flash />
    <x-panel.card title="Čekající komentáře ke schválení">
        @if($pending->isEmpty())
            <p class="text-muted">Žádné čekající komentáře.</p>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Článek</th>
                        <th>Autor</th>
                        <th>Komentář</th>
                        <th>Datum</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pending as $c)
                    <tr>
                        <td class="truncate" style="max-width:160px">
                            <a href="{{ route('admin.kb.show', $c->kb_article_id) }}">{{ $c->article?->title ?? '—' }}</a>
                        </td>
                        <td>{{ $c->author?->name ?? '—' }}</td>
                        <td class="truncate" style="max-width:300px">{{ $c->body }}</td>
                        <td class="small text-muted">{{ $c->created_at?->format('d.m.Y H:i') }}</td>
                        <td>
                            <div class="flex gap-2">
                                <form method="POST" action="{{ route('admin.kb-comments.approve', $c) }}">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-sm btn-success">Schválit</button>
                                </form>
                                <form method="POST" action="{{ route('admin.kb-comments.destroy', $c) }}">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Smazat?')">Zamítnout</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $pending->links() }}</div>
        @endif
    </x-panel.card>
</div>
@endsection
