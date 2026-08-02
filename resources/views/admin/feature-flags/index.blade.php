@extends('layouts.panel')

@section('title', 'Feature flags')

@php
    $breadcrumbTitle = 'Feature flags';
    $breadcrumbItems = ['Feature flags' => ''];
@endphp

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 gap-4">
        <div class="col-span-12 lg:col-span-8">
            <div class="card">
                <div class="card-header card-no-border">
                    <div class="header-top">
                        <h5>Přepínače funkcí</h5>
                        <p class="f-m-light mt-1">
                            Zapínejte funkce bez nasazení. <strong>Procento</strong> je postupné
                            zavádění — zákazník má stabilní zařazení, takže se stav nepřepíná
                            mezi requesty a rozšíření procenta jen přidává další zákazníky.
                        </p>
                    </div>
                </div>
                <div class="card-body">
                    @forelse ($flags as $flag)
                        <form method="POST" action="{{ route('admin.feature-flags.update', $flag) }}" class="border rounded p-3 mb-3">
                            @csrf @method('PUT')
                            <div class="flex items-start justify-between gap-2 mb-2">
                                <div>
                                    <code class="f-w-600">{{ $flag->key }}</code>
                                    <span class="badge {{ $flag->is_enabled ? 'badge-light-success' : 'badge-light-secondary' }} ms-2">
                                        {{ $flag->is_enabled ? 'Zapnuto' : 'Vypnuto' }}
                                    </span>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_enabled" value="1"
                                           id="enabled-{{ $flag->id }}" @checked($flag->is_enabled)>
                                    <label class="form-check-label f-12" for="enabled-{{ $flag->id }}">Aktivní</label>
                                </div>
                            </div>

                            <div class="grid grid-cols-12 gap-2 mb-2">
                                <div class="col-span-12 md:col-span-6">
                                    <label class="form-label f-12">Název</label>
                                    <input type="text" name="label" class="form-control form-control-sm" value="{{ $flag->label }}" required>
                                </div>
                                <div class="col-span-6 md:col-span-3">
                                    <label class="form-label f-12">Rollout (%)</label>
                                    <input type="number" name="rollout_percent" class="form-control form-control-sm"
                                           value="{{ $flag->rollout_percent }}" min="0" max="100">
                                </div>
                                <div class="col-span-6 md:col-span-3">
                                    <label class="form-label f-12">ID zákazníků (vždy)</label>
                                    <input type="text" name="customer_ids" class="form-control form-control-sm"
                                           value="{{ implode(',', $flag->customer_ids ?? []) }}" placeholder="12,34">
                                </div>
                            </div>

                            <div class="mb-2">
                                <label class="form-label f-12">Popis</label>
                                <textarea name="description" class="form-control form-control-sm" rows="2">{{ $flag->description }}</textarea>
                            </div>

                            <div class="flex gap-2">
                                <button type="submit" class="btn btn-primary btn-sm text-white">Uložit</button>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('admin.feature-flags.destroy', $flag) }}" class="mb-4"
                              data-confirm="Odstranit flag {{ $flag->key }}?">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-light btn-sm">Odstranit</button>
                        </form>
                    @empty
                        <p class="text-muted">Zatím žádné feature flags.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-span-12 lg:col-span-4">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Nový flag</h5></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.feature-flags.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label f-12">Klíč</label>
                            <input type="text" name="key" class="form-control form-control-sm font-monospace @error('key') is-invalid @enderror"
                                   value="{{ old('key') }}" placeholder="new-checkout" required>
                            @error('key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label f-12">Název</label>
                            <input type="text" name="label" class="form-control form-control-sm" value="{{ old('label') }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label f-12">Rollout (%)</label>
                            <input type="number" name="rollout_percent" class="form-control form-control-sm"
                                   value="{{ old('rollout_percent', 100) }}" min="0" max="100">
                        </div>
                        <div class="mb-3 form-check">
                            <input class="form-check-input" type="checkbox" name="is_enabled" value="1" id="new-enabled">
                            <label class="form-check-label f-12" for="new-enabled">Rovnou aktivní</label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm text-white">Vytvořit</button>
                    </form>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-body">
                    <h6 class="mb-2">Použití v kódu</h6>
                    <pre class="f-11 mb-0"><code>@verbatim@feature('new-checkout')
    ...nová varianta...
@endfeature@endverbatim</code></pre>
                    <p class="f-11 f-light mt-2 mb-0">
                        V PHP: <code>app(Features::class)->enabled('key', $customer)</code>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
