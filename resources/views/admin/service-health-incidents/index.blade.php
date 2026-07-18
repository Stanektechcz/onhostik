@extends('layouts.panel')

@section('title', 'Incidenty zdraví služeb')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- Filter --}}
    <x-panel.card title="Filtr">
        <form method="GET" action="{{ route('admin.service-health-incidents.index') }}" class="flex gap-2 items-end flex-wrap">
            <div>
                <label class="form-label mb-1 small">Service ID</label>
                <input type="number" name="service_id" class="form-control form-control-sm" value="{{ $serviceId }}" placeholder="všechny" style="width:140px">
            </div>
            <div>
                <label class="form-label mb-1 small">Stav</label>
                <select name="status" class="form-select form-select-sm" style="width:160px">
                    <option value="">— vše —</option>
                    @foreach(['open' => 'Otevřený', 'investigating' => 'Vyšetřování', 'resolved' => 'Vyřešený'] as $val => $label)
                        <option value="{{ $val }}" @selected($status === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-sm btn-primary">Filtrovat</button>
            @if($serviceId || $status)
                <a href="{{ route('admin.service-health-incidents.index') }}" class="btn btn-sm btn-outline-secondary">Zrušit filtr</a>
            @endif
        </form>
    </x-panel.card>

    {{-- Table --}}
    <x-panel.card title="Incidenty{{ $serviceId ? ' – Service #'.$serviceId : '' }}{{ $status ? ' – '.ucfirst($status) : '' }}">
        @if($incidents->isEmpty())
            <p class="text-muted">Žádné incidenty.</p>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Service ID</th>
                        <th>Závažnost</th>
                        <th>Název</th>
                        <th>Stav</th>
                        <th>Vytvořeno</th>
                        <th>Vyřešeno</th>
                        <th>Aktualizovat stav</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($incidents as $incident)
                    @php
                        $severityColor = match($incident->severity) {
                            'critical' => 'danger',
                            'warning'  => 'warning',
                            default    => 'info',
                        };
                        $statusColor = match($incident->status) {
                            'open'          => 'danger',
                            'investigating' => 'warning',
                            default         => 'success',
                        };
                        $statusLabel = match($incident->status) {
                            'open'          => 'Otevřený',
                            'investigating' => 'Vyšetřování',
                            default         => 'Vyřešený',
                        };
                    @endphp
                    <tr>
                        <td>{{ $incident->id }}</td>
                        <td>{{ $incident->service_id }}</td>
                        <td><span class="badge bg-{{ $severityColor }}">{{ $incident->severity }}</span></td>
                        <td class="truncate" style="max-width:240px">{{ $incident->title }}</td>
                        <td><span class="badge bg-{{ $statusColor }}">{{ $statusLabel }}</span></td>
                        <td class="text-nowrap">{{ $incident->created_at->format('d.m.Y H:i') }}</td>
                        <td class="text-nowrap">{{ $incident->resolved_at ? $incident->resolved_at->format('d.m.Y H:i') : '—' }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.service-health-incidents.update', $incident->id) }}" class="flex gap-1">
                                @csrf @method('PATCH')
                                <select name="status" class="form-select form-select-sm" style="width:140px">
                                    <option value="open"          @selected($incident->status === 'open')>Otevřený</option>
                                    <option value="investigating" @selected($incident->status === 'investigating')>Vyšetřování</option>
                                    <option value="resolved"      @selected($incident->status === 'resolved')>Vyřešený</option>
                                </select>
                                <button type="submit" class="btn btn-sm btn-outline-primary">Uložit</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $incidents->withQueryString()->links() }}</div>
        @endif
    </x-panel.card>

    {{-- Create form --}}
    <x-panel.card title="Nový incident">
        <form method="POST" action="{{ route('admin.service-health-incidents.store') }}" class="grid grid-cols-12 gap-3">
            @csrf

            <div class="col-span-12 md:col-span-2">
                <label class="form-label">Service ID <span class="text-danger">*</span></label>
                <input type="number" name="service_id" class="form-control @error('service_id') is-invalid @enderror"
                       value="{{ old('service_id', $serviceId) }}" required>
                @error('service_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-span-12 md:col-span-2">
                <label class="form-label">Závažnost <span class="text-danger">*</span></label>
                <select name="severity" class="form-select @error('severity') is-invalid @enderror" required>
                    <option value="">— vyberte —</option>
                    <option value="info"     @selected(old('severity') === 'info')>info</option>
                    <option value="warning"  @selected(old('severity') === 'warning')>warning</option>
                    <option value="critical" @selected(old('severity') === 'critical')>critical</option>
                </select>
                @error('severity')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-span-12 md:col-span-2">
                <label class="form-label">Výchozí stav <span class="text-danger">*</span></label>
                <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                    <option value="">— vyberte —</option>
                    <option value="open"          @selected(old('status') === 'open')>Otevřený</option>
                    <option value="investigating" @selected(old('status') === 'investigating')>Vyšetřování</option>
                    <option value="resolved"      @selected(old('status') === 'resolved')>Vyřešený</option>
                </select>
                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-span-12 md:col-span-6">
                <label class="form-label">Název <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                       value="{{ old('title') }}" maxlength="255" required>
                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-span-12">
                <label class="form-label">Popis</label>
                <textarea name="description" rows="4" class="form-control @error('description') is-invalid @enderror">{{ old('description') }}</textarea>
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-span-12">
                <button type="submit" class="btn btn-primary">Vytvořit incident</button>
            </div>
        </form>
    </x-panel.card>
</div>
@endsection
