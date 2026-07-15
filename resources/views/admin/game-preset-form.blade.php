@extends('layouts.panel')

@php
    $isNew = $preset === null;
    $breadcrumbTitle = $isNew ? 'Nový preset' : 'Upravit: ' . $preset->name;
    $breadcrumbItems = ['Game server presety' => route('admin.game-presets.index'), $breadcrumbTitle => ''];
@endphp

@section('title', $breadcrumbTitle)

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <div class="grid grid-cols-12 card-gap">
            {{-- Main form --}}
            <div class="col-span-8 xl:col-span-12">
                <x-panel.card :title="$breadcrumbTitle">
                    <form method="POST"
                          action="{{ $isNew ? route('admin.game-presets.store') : route('admin.game-presets.update', $preset) }}">
                        @csrf
                        @if(!$isNew) @method('PUT') @endif

                        {{-- Basic identity --}}
                        <div class="grid grid-cols-12 card-gap mb-3">
                            <div class="col-span-6 md:col-span-12">
                                <label class="form-label" for="gp-name">Název hry *</label>
                                <input id="gp-name" type="text" name="name"
                                       class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name', $preset?->name) }}"
                                       required maxlength="100" placeholder="Minecraft Java Edition">
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-span-6 md:col-span-12">
                                <label class="form-label" for="gp-slug">
                                    Slug *
                                    <span class="f-light f-12">(unikátní, pouze písmena, číslice, pomlčka)</span>
                                </label>
                                <input id="gp-slug" type="text" name="game_slug"
                                       class="form-control @error('game_slug') is-invalid @enderror"
                                       value="{{ old('game_slug', $preset?->game_slug) }}"
                                       required maxlength="60" placeholder="minecraft-java">
                                @error('game_slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="gp-desc">Popis (volitelné)</label>
                            <textarea id="gp-desc" name="description"
                                      class="form-control @error('description') is-invalid @enderror"
                                      rows="2" maxlength="500">{{ old('description', $preset?->description) }}</textarea>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Pterodactyl IDs --}}
                        <h6 class="mergecolor mb-2 mt-4">Pterodactyl identifikátory</h6>
                        <div class="grid grid-cols-12 card-gap mb-3">
                            <div class="col-span-3 md:col-span-6 sm:col-span-12">
                                <label class="form-label" for="gp-nest">Nest ID *</label>
                                <input id="gp-nest" type="number" name="nest_id"
                                       class="form-control @error('nest_id') is-invalid @enderror"
                                       value="{{ old('nest_id', $preset?->nest_id ?? 1) }}"
                                       required min="1" max="9999">
                                @error('nest_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-span-3 md:col-span-6 sm:col-span-12">
                                <label class="form-label" for="gp-egg">Egg ID *</label>
                                <input id="gp-egg" type="number" name="egg_id"
                                       class="form-control @error('egg_id') is-invalid @enderror"
                                       value="{{ old('egg_id', $preset?->egg_id ?? 1) }}"
                                       required min="1" max="9999">
                                @error('egg_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-span-3 md:col-span-6 sm:col-span-12">
                                <label class="form-label" for="gp-sort">Pořadí</label>
                                <input id="gp-sort" type="number" name="sort_order"
                                       class="form-control"
                                       value="{{ old('sort_order', $preset?->sort_order ?? 0) }}"
                                       min="0" max="255">
                            </div>
                            <div class="col-span-3 md:col-span-6 sm:col-span-12 d-flex align-items-end pb-1">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="gp-active"
                                           name="is_active" value="1"
                                           @checked(old('is_active', $preset?->is_active ?? true))>
                                    <label class="form-check-label" for="gp-active">Aktivní</label>
                                </div>
                            </div>
                        </div>

                        {{-- Resource defaults --}}
                        <h6 class="mergecolor mb-2 mt-4">Výchozí zdroje</h6>
                        <div class="grid grid-cols-12 card-gap mb-3">
                            <div class="col-span-4 md:col-span-6 sm:col-span-12">
                                <label class="form-label" for="gp-mem">
                                    RAM (MB) *
                                    <span class="f-light f-12">min 128</span>
                                </label>
                                <input id="gp-mem" type="number" name="default_memory_mb"
                                       class="form-control @error('default_memory_mb') is-invalid @enderror"
                                       value="{{ old('default_memory_mb', $preset?->default_memory_mb ?? 1024) }}"
                                       required min="128" max="131072" step="128">
                                <span class="f-12 seccolor" id="gp-mem-label">
                                    = {{ number_format(($preset?->default_memory_mb ?? 1024) / 1024, 2) }} GB
                                </span>
                                @error('default_memory_mb')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-span-4 md:col-span-6 sm:col-span-12">
                                <label class="form-label" for="gp-disk">
                                    Disk (MB) *
                                    <span class="f-light f-12">min 512</span>
                                </label>
                                <input id="gp-disk" type="number" name="default_disk_mb"
                                       class="form-control @error('default_disk_mb') is-invalid @enderror"
                                       value="{{ old('default_disk_mb', $preset?->default_disk_mb ?? 10240) }}"
                                       required min="512" max="2097152" step="512">
                                <span class="f-12 seccolor" id="gp-disk-label">
                                    = {{ number_format(($preset?->default_disk_mb ?? 10240) / 1024, 2) }} GB
                                </span>
                                @error('default_disk_mb')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-span-4 md:col-span-6 sm:col-span-12">
                                <label class="form-label" for="gp-cpu">CPU limit (%) *</label>
                                <input id="gp-cpu" type="number" name="default_cpu_limit"
                                       class="form-control @error('default_cpu_limit') is-invalid @enderror"
                                       value="{{ old('default_cpu_limit', $preset?->default_cpu_limit ?? 100) }}"
                                       required min="1" max="800">
                                <span class="f-12 seccolor">100 % = 1 jádro, 200 % = 2 jádra</span>
                                @error('default_cpu_limit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="grid grid-cols-12 card-gap mb-3">
                            <div class="col-span-4 md:col-span-6 sm:col-span-12">
                                <label class="form-label" for="gp-swap">Swap (MB)</label>
                                <input id="gp-swap" type="number" name="default_swap_mb"
                                       class="form-control"
                                       value="{{ old('default_swap_mb', $preset?->default_swap_mb ?? 0) }}"
                                       min="0" max="16384">
                            </div>
                            <div class="col-span-4 md:col-span-6 sm:col-span-12">
                                <label class="form-label" for="gp-io">IO weight</label>
                                <input id="gp-io" type="number" name="default_io_weight"
                                       class="form-control"
                                       value="{{ old('default_io_weight', $preset?->default_io_weight ?? 500) }}"
                                       min="10" max="1000">
                                <span class="f-12 seccolor">10 – 1000, výchozí 500</span>
                            </div>
                        </div>

                        {{-- Docker / startup --}}
                        <h6 class="mergecolor mb-2 mt-4">Docker & startup (volitelné přepisy)</h6>
                        <div class="mb-3">
                            <label class="form-label" for="gp-docker">Docker image</label>
                            <input id="gp-docker" type="text" name="docker_image"
                                   class="form-control @error('docker_image') is-invalid @enderror"
                                   value="{{ old('docker_image', $preset?->docker_image) }}"
                                   maxlength="255" placeholder="ghcr.io/pterodactyl/yolks:java_21">
                            @error('docker_image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="gp-startup">Startup příkaz</label>
                            <input id="gp-startup" type="text" name="startup"
                                   class="form-control @error('startup') is-invalid @enderror"
                                   value="{{ old('startup', $preset?->startup) }}"
                                   maxlength="1000" placeholder="java -Xms128M -Xmx@{{SERVER_MEMORY}}M ...">
                            @error('startup')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Environment variables --}}
                        <div class="mb-4">
                            <label class="form-label" for="gp-env">
                                Environment proměnné
                                <span class="f-light f-12">(JSON objekt, klíč:hodnota)</span>
                            </label>
                            <textarea id="gp-env" name="environment_json"
                                      class="form-control font-monospace @error('environment_json') is-invalid @enderror"
                                      rows="6"
                                      placeholder='{"SERVER_JARFILE": "server.jar", "MC_VERSION": "latest"}'>{{ old('environment_json', $preset?->environment ? json_encode($preset->environment, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '') }}</textarea>
                            @error('environment_json')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="f-12 seccolor mt-1">
                                Musí být validní JSON objekt. Proměnné jsou specifické pro každý egg.
                            </div>
                        </div>

                        <div class="d-flex gap-2 border-top pt-3 mt-1">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i data-feather="save" style="width:13px;height:13px"></i>
                                {{ $isNew ? 'Vytvořit preset' : 'Uložit změny' }}
                            </button>
                            <a href="{{ route('admin.game-presets.index') }}" class="btn btn-outline-secondary btn-sm">Zpět</a>
                        </div>
                    </form>
                </x-panel.card>
            </div>

            {{-- Sidebar help --}}
            <div class="col-span-4 xl:col-span-12">
                <x-panel.card title="Nápověda k presetům">
                    <p class="seccolor f-13">
                        Preset odpovídá jednomu Pterodactyl <strong>Egg</strong> (šabloně hry).
                        <br><br>
                        <strong>Nest ID</strong> a <strong>Egg ID</strong> najdete v Pterodactyl admin panelu v sekci
                        <em>Nests → Eggs</em>.
                        <br><br>
                        <strong>Environment</strong> jsou proměnné specifické pro egg — např. verze Java, JAR soubor,
                        RCON heslo. Každý egg má jiné proměnné; podívejte se do záložky
                        <em>Variables</em> na detailu eggu.
                        <br><br>
                        Hodnoty z presetu lze přepsat per-service přes <code>resources</code> JSON na záznamu služby.
                    </p>
                </x-panel.card>

                @if(!$isNew && $preset !== null)
                    <x-panel.card title="Nebezpečná zóna">
                        <form method="POST" action="{{ route('admin.game-presets.destroy', $preset) }}"
                              onsubmit="return confirm('Opravdu smazat preset {{ addslashes($preset->name) }}?')">
                            @csrf
                            @method('DELETE')
                            <p class="seccolor f-13 mb-2">Smazání je nevratné. Existující služby nejsou dotčeny.</p>
                            <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                <i data-feather="trash-2" style="width:13px;height:13px"></i>
                                Smazat preset
                            </button>
                        </form>
                    </x-panel.card>
                @endif
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    (function () {
        function mb2gb(el, label) {
            el.addEventListener('input', function () {
                var gb = (parseInt(this.value, 10) || 0) / 1024;
                label.textContent = '= ' + gb.toFixed(2) + ' GB';
            });
        }
        mb2gb(document.getElementById('gp-mem'), document.getElementById('gp-mem-label'));
        mb2gb(document.getElementById('gp-disk'), document.getElementById('gp-disk-label'));

        // JSON validation for environment textarea
        var envArea = document.getElementById('gp-env');
        if (envArea) {
            envArea.closest('form').addEventListener('submit', function (e) {
                var raw = envArea.value.trim();
                if (raw === '') return;
                try {
                    var parsed = JSON.parse(raw);
                    if (typeof parsed !== 'object' || Array.isArray(parsed)) {
                        e.preventDefault();
                        alert('Environment musí být JSON objekt (ne pole). Příklad: {"KEY": "value"}');
                    }
                } catch (err) {
                    e.preventDefault();
                    alert('Environment obsahuje nevalidní JSON: ' + err.message);
                }
            });
        }
    })();
    </script>
    @endpush
@endsection
