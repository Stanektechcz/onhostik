@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Obsah webu';
    $breadcrumbItems = ['Obsah webu' => ''];
@endphp

@section('title', 'Obsah webu')

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <form method="POST" action="{{ route('admin.site-content.bulk') }}">
            @csrf

            @forelse($groups as $group => $items)
                <x-panel.card :title="ucfirst($group)">
                    <div class="row g-3">
                        @foreach($items as $item)
                            <div class="{{ $item->type === 'textarea' || $item->type === 'html' ? 'col-md-12' : 'col-md-6' }}">
                                <label class="form-label f-w-500">
                                    {{ $item->label }}
                                    <small class="text-muted ms-1 f-w-400">({{ $item->key }})</small>
                                </label>
                                @if($item->type === 'textarea')
                                    <textarea name="contents[{{ $item->id }}]" rows="4" class="form-control">{{ $item->value }}</textarea>
                                @elseif($item->type === 'html')
                                    <textarea name="contents[{{ $item->id }}]" rows="8" class="form-control" style="font-family:monospace">{{ $item->value }}</textarea>
                                @elseif($item->type === 'boolean')
                                    <select name="contents[{{ $item->id }}]" class="form-select">
                                        <option value="1" @selected($item->value === '1')>Ano</option>
                                        <option value="0" @selected($item->value !== '1')>Ne</option>
                                    </select>
                                @else
                                    <input type="text" name="contents[{{ $item->id }}]" class="form-control" value="{{ $item->value }}">
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-panel.card>
            @empty
                <x-panel.card title="Obsah webu">
                    <div class="text-center py-4">
                        <p class="text-muted">Žádný obsah zatím. Přidejte záznamy pomocí seedu nebo ručně.</p>
                        <a href="{{ route('admin.settings.index') }}" class="btn btn-primary-light">Přejít na nastavení</a>
                    </div>
                </x-panel.card>
            @endforelse

            @if($groups->isNotEmpty())
                <div class="sticky-bottom py-3">
                    <button type="submit" class="btn btn-primary">
                        <i data-feather="save" class="me-1"></i> Uložit veškerý obsah
                    </button>
                </div>
            @endif
        </form>
    </div>
@endsection
