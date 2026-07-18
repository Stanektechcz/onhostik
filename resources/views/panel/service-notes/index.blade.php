@extends('layouts.panel')

@section('title', 'Poznámky ke službě')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="mb-3">
        <a href="{{ route('panel.services.show', $service) }}" class="btn btn-sm btn-outline-secondary">
            &larr; Zpět na službu
        </a>
    </div>

    <div class="grid grid-cols-12 gap-4">
        <div class="col-span-12 md:col-span-8">
            <x-panel.card title="Moje poznámky — {{ $service->name }}">
                @forelse($notes as $note)
                <div class="border rounded p-3 mb-3">
                    <div class="flex justify-between items-start">
                        <p class="mb-1">{{ $note->content }}</p>
                        <form method="POST" action="{{ route('panel.service-notes.destroy', $note) }}" class="ms-3">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Smazat</button>
                        </form>
                    </div>
                    <small class="text-muted">{{ $note->created_at->format('d.m.Y H:i') }}</small>
                </div>
                @empty
                <p class="text-muted text-center py-3">Žádné poznámky.</p>
                @endforelse
                <div class="mt-2">{{ $notes->links() }}</div>
            </x-panel.card>
        </div>

        <div class="col-span-12 md:col-span-4">
            <x-panel.card title="Nová poznámka">
                <form method="POST" action="{{ route('panel.service-notes.store', $service) }}">
                    @csrf
                    <div class="mb-3">
                        <textarea name="content" class="form-control" rows="5" placeholder="Poznámka…" maxlength="2000" required>{{ old('content') }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-full">Uložit</button>
                </form>
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
