@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Game server presety';
    $breadcrumbItems = ['Game server presety' => ''];
@endphp

@section('title', 'Game server presety')

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <div class="flex justify-between items-center mb-3">
            <p class="seccolor f-14 mb-0">
                Presety definují výchozí zdroje a konfiguraci pro každou hru nasazovanou přes Pterodactyl.
            </p>
            <a href="{{ route('admin.game-presets.create') }}" class="btn btn-primary btn-sm">+ Přidat preset</a>
        </div>

        @if($presets->isEmpty())
            <div class="alert alert-light-secondary">
                Žádné presety zatím nejsou. Přidejte první preset pro Minecraft, CS2 nebo jinou hru.
            </div>
        @else
            <x-panel.card title="Presety herních serverů">
                <x-panel.data-table :headers="['Hra', 'Slug', 'Egg ID', 'RAM', 'Disk', 'CPU', 'Stav', '']">
                    @foreach($presets as $preset)
                        <tr>
                            <td class="f-w-600">{{ $preset->name }}</td>
                            <td><code class="f-12">{{ $preset->game_slug }}</code></td>
                            <td class="f-12">
                                nest&nbsp;{{ $preset->nest_id }} / egg&nbsp;{{ $preset->egg_id }}
                            </td>
                            <td class="f-12">{{ number_format($preset->default_memory_mb / 1024, 1) }} GB</td>
                            <td class="f-12">{{ number_format($preset->default_disk_mb / 1024, 1) }} GB</td>
                            <td class="f-12">{{ $preset->default_cpu_limit }} %</td>
                            <td>
                                @if($preset->is_active)
                                    <span class="badge badge-light-success">Aktivní</span>
                                @else
                                    <span class="badge badge-light-secondary">Neaktivní</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('admin.game-presets.edit', $preset) }}" class="btn btn-outline-secondary btn-sm">
                                    <i data-feather="edit-2" style="width:13px;height:13px"></i>
                                    Upravit
                                </a>
                                <form method="POST" action="{{ route('admin.game-presets.destroy', $preset) }}"
                                      class="inline" onsubmit="return confirm('Opravdu smazat preset {{ $preset->name }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm ms-1">
                                        <i data-feather="trash-2" style="width:13px;height:13px"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </x-panel.data-table>
            </x-panel.card>
        @endif
    </div>
@endsection
