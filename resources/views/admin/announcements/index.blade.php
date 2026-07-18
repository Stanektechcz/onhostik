@extends('layouts.panel')

@section('title', 'Systémová oznámení')

@section('content')
<div class="container-fluid py-4">

    <div class="flex justify-between items-center mb-4">
        <h1 class="h4 mb-0">Systémová oznámení</h1>
        <a href="{{ route('admin.announcements.create') }}" class="btn btn-sm btn-primary">+ Nové oznámení</a>
    </div>

    @if(session('status'))
        <div class="alert alert-success py-2">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Název</th>
                        <th>Typ</th>
                        <th>Email</th>
                        <th>Stav</th>
                        <th>Odesláno</th>
                        <th>Vyprší</th>
                        <th>Vytvořeno</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($announcements as $a)
                    <tr>
                        <td class="font-semibold small">{{ $a->title }}</td>
                        <td><span class="{{ $a->typeBadgeClass() }}">{{ $a->typeLabel() }}</span></td>
                        <td class="small">{{ $a->send_email ? 'Ano' : 'Ne' }}</td>
                        <td>
                            @if($a->isActive())
                                <span class="badge bg-success">Aktivní</span>
                            @elseif($a->is_published)
                                <span class="badge bg-secondary">Expirováno</span>
                            @else
                                <span class="badge bg-warning text-dark">Nepublikováno</span>
                            @endif
                        </td>
                        <td class="small">{{ $a->sent_count > 0 ? $a->sent_count . ' zákazníků' : '—' }}</td>
                        <td class="small text-muted">{{ $a->expires_at?->format('d.m.Y') ?? '—' }}</td>
                        <td class="small text-muted">{{ $a->created_at->format('d.m.Y') }}</td>
                        <td class="text-right text-nowrap">
                            @if(!$a->is_published)
                                <form action="{{ route('admin.announcements.publish', $a) }}" method="POST" class="inline">
                                    @csrf
                                    <button class="btn btn-xs btn-outline-success">Odeslat všem</button>
                                </form>
                                <a href="{{ route('admin.announcements.edit', $a) }}" class="btn btn-xs btn-outline-secondary">Upravit</a>
                            @endif
                            <form action="{{ route('admin.announcements.destroy', $a) }}" method="POST" class="inline"
                                  onsubmit="return confirm('Smazat oznámení?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-xs btn-outline-danger">Smazat</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-muted text-center py-3">Žádná oznámení.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $announcements->links() }}</div>

</div>
@endsection
