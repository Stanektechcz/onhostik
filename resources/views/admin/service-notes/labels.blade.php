@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Štítky služeb';
    $breadcrumbItems = ['Služby' => route('admin.services.index'), 'Štítky' => ''];
@endphp

@section('title', 'Štítky služeb')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12">
        <div class="col-span-12 lg:col-span-4">
            <div class="card">
                <div class="card-header card-no-border">
                    <h5>Nový štítek</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.service-labels.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="name" class="form-label">Název</label>
                            <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}" maxlength="60" required>
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label for="color" class="form-label">Barva</label>
                            <select id="color" name="color" class="form-select @error('color') is-invalid @enderror">
                                @foreach($colors as $c)
                                <option value="{{ $c }}" {{ old('color') === $c ? 'selected' : '' }}>
                                    {{ ucfirst($c) }}
                                </option>
                                @endforeach
                            </select>
                            @error('color') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <button type="submit" class="btn btn-primary">Vytvořit štítek</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-span-12 lg:col-span-8">
            <div class="card">
                <div class="card-header card-no-border">
                    <h5>Existující štítky ({{ $labels->total() }})</h5>
                </div>
                <div class="card-body pt-0">
                    @if($labels->isEmpty())
                        <p class="text-center f-light py-4">Žádné štítky.</p>
                    @else
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead><tr><th>Štítek</th><th>Barva</th><th>Počet služeb</th><th></th></tr></thead>
                            <tbody>
                                @foreach($labels as $label)
                                <tr>
                                    <td><span class="badge bg-{{ $label->color }}">{{ $label->name }}</span></td>
                                    <td class="f-12">{{ $label->color }}</td>
                                    <td class="f-12">{{ $label->services_count }}</td>
                                    <td class="text-right">
                                        <form method="POST" action="{{ route('admin.service-labels.destroy', $label) }}"
                                              data-confirm="Smazat štítek?">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Smazat</button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-2">{{ $labels->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
