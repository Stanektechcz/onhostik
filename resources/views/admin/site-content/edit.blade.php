@extends('layouts.panel')

@php($breadcrumbTitle = 'Upravit obsah: ' . $item->label)

@section('title', 'Upravit obsah')

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <x-panel.card :title="'Upravit: ' . $item->label">
            <form method="POST" action="{{ route('admin.site-content.update', $item) }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label f-w-500">Klíč</label>
                    <input type="text" class="form-control" value="{{ $item->key }}" disabled>
                </div>

                <div class="mb-4">
                    <label class="form-label f-w-500">Hodnota</label>
                    @if($item->type === 'textarea')
                        <textarea name="value" rows="6" class="form-control @error('value') is-invalid @enderror">{{ old('value', $item->value) }}</textarea>
                    @elseif($item->type === 'html')
                        <textarea name="value" rows="12" class="form-control @error('value') is-invalid @enderror" style="font-family:monospace">{{ old('value', $item->value) }}</textarea>
                    @elseif($item->type === 'boolean')
                        <select name="value" class="form-select @error('value') is-invalid @enderror">
                            <option value="1" @selected(old('value', $item->value) === '1')>Ano</option>
                            <option value="0" @selected(old('value', $item->value) !== '1')>Ne</option>
                        </select>
                    @else
                        <input type="text" name="value" class="form-control @error('value') is-invalid @enderror" value="{{ old('value', $item->value) }}">
                    @endif
                    @error('value')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary">Uložit</button>
                <a href="{{ route('admin.site-content.index') }}" class="btn btn-light ms-2">Zpět</a>
            </form>
        </x-panel.card>
    </div>
@endsection
