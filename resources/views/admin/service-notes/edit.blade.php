@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Poznámky a štítky';
    $breadcrumbItems = ['Služby' => route('admin.services.index'), "#{$service->id}" => route('admin.services.show', $service), 'Poznámky' => ''];
@endphp

@section('title', 'Poznámky a štítky — ' . ($service->label ?: "Služba #{$service->id}"))

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <form method="POST" action="{{ route('admin.service-notes.update', $service) }}">
        @csrf
        @method('PUT')

        <div class="card mb-3">
            <div class="card-header card-no-border">
                <h5>Interní poznámka k službě #{{ $service->id }}</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label f-12 f-light">Zákazník</label>
                    <div class="f-w-500">{{ $service->customer?->company_name ?? '—' }}</div>
                    <div class="f-12 f-light">{{ $service->customer?->user?->email ?? '' }}</div>
                </div>

                <div class="mb-3">
                    <label for="admin_note" class="form-label">Interní poznámka</label>
                    <textarea id="admin_note" name="admin_note" class="form-control @error('admin_note') is-invalid @enderror"
                              rows="6" placeholder="Poznámka viditelná pouze pro adminy…">{{ old('admin_note', $service->admin_note) }}</textarea>
                    @error('admin_note') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Štítky</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach($allLabels as $label)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox"
                                   name="labels[]" value="{{ $label->id }}"
                                   id="label_{{ $label->id }}"
                                   {{ in_array($label->id, $serviceLabels) ? 'checked' : '' }}>
                            <label class="form-check-label" for="label_{{ $label->id }}">
                                <span class="badge bg-{{ $label->color }}">{{ $label->name }}</span>
                            </label>
                        </div>
                        @endforeach
                        @if($allLabels->isEmpty())
                            <span class="f-12 f-light">Žádné štítky. <a href="{{ route('admin.service-labels.index') }}">Vytvořte štítek →</a></span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="card-footer flex gap-2">
                <button type="submit" class="btn btn-primary">Uložit</button>
                <a href="{{ route('admin.services.show', $service) }}" class="btn btn-outline-secondary">Zrušit</a>
            </div>
        </div>
    </form>
</div>
@endsection
