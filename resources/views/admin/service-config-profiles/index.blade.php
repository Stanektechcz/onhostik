@extends('layouts.panel')

@section('title', 'Profily konfigurace služeb')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 card-gap">

        {{-- Create form --}}
        <div class="col-span-4 xl:col-span-12">
            <x-panel.card title="Nový profil konfigurace">
                <form method="POST" action="{{ route('admin.service-config-profiles.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Název <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-sm" maxlength="255"
                               value="{{ old('name') }}" required>
                        @error('name')<div class="text-danger f-12">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Popis</label>
                        <textarea name="description" class="form-control form-control-sm" rows="3">{{ old('description') }}</textarea>
                        @error('description')<div class="text-danger f-12">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Typ služby <span class="text-danger">*</span></label>
                        <input type="text" name="service_type" class="form-control form-control-sm" maxlength="100"
                               value="{{ old('service_type') }}" required placeholder="napr. shared_hosting">
                        @error('service_type')<div class="text-danger f-12">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Konfigurační data (JSON)</label>
                        <textarea name="config_data_raw" class="form-control form-control-sm font-monospace"
                                  rows="6" placeholder="{&#10;  &quot;key&quot;: &quot;value&quot;&#10;}">{{ old('config_data_raw', '{}') }}</textarea>
                        <div class="form-text">Zadejte platný JSON objekt.</div>
                        @error('config_data_raw')<div class="text-danger f-12">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="is_active" value="1" id="is_active"
                               class="form-check-input" {{ old('is_active', '1') ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_active">Aktivní</label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Vytvořit profil</button>
                </form>
            </x-panel.card>
        </div>

        {{-- Table --}}
        <div class="col-span-8 xl:col-span-12">
            <x-panel.card title="Profily konfigurace">

                {{-- Filter --}}
                <form method="GET" action="{{ route('admin.service-config-profiles.index') }}" class="flex gap-2 mb-3">
                    <input type="text" name="service_type" class="form-control form-control-sm w-auto"
                           placeholder="Typ služby" value="{{ $serviceType }}">
                    <button type="submit" class="btn btn-sm btn-secondary">Filtrovat</button>
                    @if($serviceType)
                        <a href="{{ route('admin.service-config-profiles.index') }}" class="btn btn-sm btn-outline-secondary">Zrušit</a>
                    @endif
                </form>

                @if($profiles->isEmpty())
                    <p class="text-muted">Žádné profily konfigurace.</p>
                @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Název</th>
                                <th>Typ služby</th>
                                <th>Aktivní</th>
                                <th>Vytvořeno</th>
                                <th>Akce</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($profiles as $profile)
                            <tr>
                                <td class="f-12 text-muted">{{ $profile->id }}</td>
                                <td class="f-w-500">{{ $profile->name }}</td>
                                <td>
                                    <span class="badge bg-primary">{{ $profile->service_type }}</span>
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.service-config-profiles.update', $profile) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                                class="btn btn-xs {{ $profile->is_active ? 'btn-success' : 'btn-outline-secondary' }}">
                                            {{ $profile->is_active ? 'Aktivní' : 'Neaktivní' }}
                                        </button>
                                    </form>
                                </td>
                                <td class="f-12">
                                    {{ $profile->created_at instanceof \Carbon\Carbon ? $profile->created_at->format('d.m.Y H:i') : \Carbon\Carbon::parse($profile->created_at)->format('d.m.Y H:i') }}
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.service-config-profiles.destroy', $profile) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-outline-danger"
                                                data-confirm="Opravdu smazat profil?">Smazat</button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $profiles->appends(request()->query())->links() }}</div>
                @endif

            </x-panel.card>
        </div>

    </div>
</div>
@endsection
