@extends('layouts.panel')

@section('title', 'Katalog věrnostních odměn')

@php
    $breadcrumbTitle = 'Věrnostní odměny';
    $breadcrumbItems = ['Věrnostní odměny' => ''];
@endphp

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 gap-4">
        {{-- Catalog table --}}
        <div class="col-span-12 lg:col-span-8">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Katalog odměn</h5></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Název</th>
                                    <th>Cena (body)</th>
                                    <th>Odměna</th>
                                    <th>Aktivní</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rewards as $reward)
                                    <tr>
                                        <td class="f-w-500">{{ $reward->name }}</td>
                                        <td>{{ number_format($reward->points_cost, 0, ',', ' ') }}</td>
                                        <td class="f-12">{{ $reward->rewardLabel() }}</td>
                                        <td>
                                            <span class="badge {{ $reward->is_active ? 'badge-light-success' : 'badge-light-secondary' }}">
                                                {{ $reward->is_active ? 'Ano' : 'Ne' }}
                                            </span>
                                        </td>
                                        <td class="text-right">
                                            <form method="POST" action="{{ route('admin.loyalty-rewards.destroy', $reward) }}"
                                                  data-confirm="Odstranit odměnu {{ $reward->name }}?">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-light">Odstranit</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-4">Zatím žádné odměny.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Add form --}}
        <div class="col-span-12 lg:col-span-4">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Přidat odměnu</h5></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.loyalty-rewards.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label f-12">Název</label>
                            <input type="text" name="name" class="form-control form-control-sm @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label f-12">Popis</label>
                            <textarea name="description" class="form-control form-control-sm" rows="2" maxlength="1000">{{ old('description') }}</textarea>
                        </div>
                        <div class="grid grid-cols-12 gap-2 mb-3">
                            <div class="col-span-6">
                                <label class="form-label f-12">Cena (body)</label>
                                <input type="number" name="points_cost" class="form-control form-control-sm @error('points_cost') is-invalid @enderror"
                                       value="{{ old('points_cost', 100) }}" min="1" required>
                                @error('points_cost')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-span-6">
                                <label class="form-label f-12">Kredit (Kč)</label>
                                <input type="number" name="reward_czk" class="form-control form-control-sm @error('reward_czk') is-invalid @enderror"
                                       value="{{ old('reward_czk', 50) }}" min="0" step="0.01" required>
                                @error('reward_czk')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="mb-3 form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" checked>
                            <label class="form-check-label f-12" for="is_active">Aktivní</label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Přidat do katalogu</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
