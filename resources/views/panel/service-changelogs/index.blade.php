@extends('layouts.panel')

@section('title', 'Changelog služeb')

@section('content')
<x-panel.flash />

<x-panel.card title="Výběr služby">
    <form method="GET" action="{{ route('panel.service-changelogs.index') }}" class="grid grid-cols-12 gap-2 items-end">
        <div class="col-auto">
            <label for="service_id" class="form-label">Služba</label>
            <select id="service_id" name="service_id" class="form-select" onchange="this.form.submit()">
                <option value="">Vyberte službu</option>
                @foreach ($services as $service)
                    <option value="{{ $service->id }}" @selected(request('service_id') == $service->id)>
                        {{ $service->label ?? $service->domain ?? '#' . $service->id }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">Zobrazit</button>
            @if (request('service_id'))
                <a href="{{ route('panel.service-changelogs.index') }}" class="btn btn-secondary">Zrušit</a>
            @endif
        </div>
    </form>
</x-panel.card>

@if (request('service_id'))
    <x-panel.card title="Changelog">
        @if ($changelogs instanceof \Illuminate\Pagination\LengthAwarePaginator && $changelogs->total() === 0)
            <p class="text-muted text-center py-3">Pro tuto službu nejsou žádné záznamy.</p>
        @elseif ($changelogs->count() > 0)
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Typ události</th>
                            <th>Shrnutí</th>
                            <th>Způsobil</th>
                            <th>Datum</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($changelogs as $entry)
                            <tr>
                                <td><code>{{ $entry->event_type }}</code></td>
                                <td>{{ $entry->summary }}</td>
                                <td>{{ $entry->caused_by ?? '—' }}</td>
                                <td class="text-nowrap">{{ $entry->created_at?->format('d.m.Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($changelogs->hasPages())
                <div class="mt-3">
                    {{ $changelogs->withQueryString()->links() }}
                </div>
            @endif
        @else
            <p class="text-muted text-center py-3">Pro tuto službu nejsou žádné záznamy.</p>
        @endif
    </x-panel.card>
@else
    <x-panel.card title="Changelog">
        <p class="text-muted text-center py-3">Vyberte službu pro zobrazení changelogy.</p>
    </x-panel.card>
@endif
@endsection
