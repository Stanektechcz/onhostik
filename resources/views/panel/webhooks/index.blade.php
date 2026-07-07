@extends('layouts.front')
@section('title', 'Webhooky')
@section('content')
<div class="container py-4">
    <x-panel.flash />

    <div class="row g-4">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">Nový webhook</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('panel.webhook-subscriptions.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">URL</label>
                            <input type="url" name="url" class="form-control @error('url') is-invalid @enderror" value="{{ old('url') }}" required placeholder="https://yourdomain.com/hook">
                            @error('url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Události</label>
                            @error('events') <div class="text-danger small mb-1">{{ $message }}</div> @enderror
                            @foreach($availableEvents as $ev)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="events[]" value="{{ $ev }}" id="ev_{{ $loop->index }}"
                                    {{ is_array(old('events')) && in_array($ev, old('events')) ? 'checked' : '' }}>
                                <label class="form-check-label" for="ev_{{ $loop->index }}">{{ $ev }}</label>
                            </div>
                            @endforeach
                        </div>
                        <button class="btn btn-primary w-100">Přidat webhook</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">Aktivní webhooky</div>
                <div class="card-body p-0">
                    @forelse($subscriptions as $sub)
                    <div class="d-flex align-items-start justify-content-between p-3 border-bottom">
                        <div>
                            <div class="fw-semibold text-break">{{ $sub->url }}</div>
                            <div class="mt-1">
                                @foreach((array)($sub->events ?? []) as $ev)
                                    <span class="badge bg-secondary me-1">{{ $ev }}</span>
                                @endforeach
                            </div>
                            <div class="small text-muted mt-1">
                                Secret: <code class="user-select-all">{{ $sub->secret }}</code>
                                @if($sub->last_triggered_at)
                                    &nbsp;| Naposledy: {{ $sub->last_triggered_at->diffForHumans() }}
                                @endif
                            </div>
                        </div>
                        <form method="POST" action="{{ route('panel.webhook-subscriptions.destroy', $sub) }}" class="ms-3 flex-shrink-0">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Smazat webhook?')">Smazat</button>
                        </form>
                    </div>
                    @empty
                    <div class="p-3 text-muted">Žádné webhooky.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
