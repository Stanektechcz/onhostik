@props([
    'notable',       // the model the notes hang off
    'type',          // slug in EntityNoteController::NOTABLES (e.g. 'order')
    'title' => 'Interní poznámky',
])

@php($routeKey = $notable->getRouteKey())

{{-- 75: unified internal notes, reusable on any entity that adopts HasEntityNotes --}}
<x-panel.card :title="$title">
    <form method="POST" action="{{ route('admin.entity-notes.store', [$type, $routeKey]) }}" class="mb-3">
        @csrf
        <textarea name="body" rows="2"
                  class="form-control form-control-sm f-12 mb-2 @error('body') is-invalid @enderror"
                  placeholder="Přidat poznámku…" maxlength="5000">{{ old('body') }}</textarea>
        @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="flex gap-2 items-center">
            <button type="submit" class="btn btn-primary btn-sm">Přidat</button>
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" name="is_pinned" value="1" id="pin_{{ $type }}_{{ $routeKey }}">
                <label class="form-check-label f-11" for="pin_{{ $type }}_{{ $routeKey }}">Připnout</label>
            </div>
        </div>
    </form>

    @forelse($notable->entityNotes as $note)
        <div class="border rounded p-2 mb-2 {{ $note->is_pinned ? 'border-warning bg-light-warning' : '' }}">
            <div class="flex justify-between items-start mb-1">
                <div>
                    @if($note->is_pinned)
                        <i data-feather="bookmark" style="width:12px;height:12px;" class="txt-warning me-1"></i>
                    @endif
                    <span class="f-12 f-w-600">{{ $note->author?->name ?? 'Admin' }}</span>
                    <span class="f-light f-11 ms-2">{{ $note->created_at->diffForHumans() }}</span>
                </div>
                <div class="flex gap-1">
                    <form method="POST" action="{{ route('admin.entity-notes.pin', [$type, $routeKey, $note]) }}">
                        @csrf
                        <button type="submit" class="btn btn-xs btn-outline-{{ $note->is_pinned ? 'warning' : 'secondary' }}"
                                title="{{ $note->is_pinned ? 'Odepnout' : 'Připnout' }}">
                            <i data-feather="bookmark" style="width:10px;height:10px;"></i>
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.entity-notes.destroy', [$type, $routeKey, $note]) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-xs btn-outline-danger" data-confirm="Smazat poznámku?">
                            <i data-feather="trash-2" style="width:10px;height:10px;"></i>
                        </button>
                    </form>
                </div>
            </div>
            <p class="f-12 mb-0" style="white-space:pre-wrap;">{{ $note->body }}</p>
        </div>
    @empty
        <p class="f-light f-12 mb-0">Žádné poznámky.</p>
    @endforelse
</x-panel.card>
