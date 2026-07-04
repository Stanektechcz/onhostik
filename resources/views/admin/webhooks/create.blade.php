@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Nový webhook';
    $breadcrumbItems = ['Odchozí webhooky' => route('admin.outgoing-webhooks.index'), 'Nový webhook' => ''];
@endphp

@section('title', 'Nový odchozí webhook')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="row justify-content-center">
        <div class="col-xl-8">
            <x-panel.card title="Přidat odchozí webhook">
                <form method="POST" action="{{ route('admin.outgoing-webhooks.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="wh-name">Název</label>
                        <input id="wh-name" type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name') }}" placeholder="Můj integrační systém" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="wh-url">URL endpointu</label>
                        <input id="wh-url" type="url" name="url" class="form-control @error('url') is-invalid @enderror"
                               value="{{ old('url') }}" placeholder="https://example.com/webhook" required>
                        @error('url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="wh-secret">Tajný klíč (HMAC SHA-256)</label>
                        <input id="wh-secret" type="text" name="secret" class="form-control @error('secret') is-invalid @enderror"
                               value="{{ old('secret') }}" placeholder="volitelný — použit pro X-OnHost-Signature header">
                        @error('secret')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Události</label>
                        <div class="row g-2">
                            @foreach($allowedEvents as $event)
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="events[]"
                                               id="ev-{{ $loop->index }}" value="{{ $event }}"
                                               {{ in_array($event, old('events', []), true) ? 'checked' : '' }}>
                                        <label class="form-check-label f-13" for="ev-{{ $loop->index }}">
                                            <code>{{ $event }}</code>
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @error('events')<div class="text-danger f-12 mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3 form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="wh-active" value="1"
                               {{ old('is_active', '1') === '1' ? 'checked' : '' }}>
                        <label class="form-check-label" for="wh-active">Webhook je aktivní</label>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Uložit webhook</button>
                        <a href="{{ route('admin.outgoing-webhooks.index') }}" class="btn btn-outline-secondary">Zpět</a>
                    </div>
                </form>
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
