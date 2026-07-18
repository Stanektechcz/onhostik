@extends('layouts.panel')

@section('title', 'Okna údržby')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 card-gap">

        {{-- Create form --}}
        <div class="col-span-4 xl:col-span-12">
            <x-panel.card title="Naplánovat okno údržby">
                <form method="POST" action="{{ route('admin.maintenance-windows.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Název <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control form-control-sm" maxlength="255"
                               value="{{ old('title') }}" required>
                        @error('title')<div class="text-danger f-12">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Popis</label>
                        <textarea name="description" class="form-control form-control-sm" rows="3">{{ old('description') }}</textarea>
                        @error('description')<div class="text-danger f-12">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Server ID <span class="text-muted">(volitelné)</span></label>
                        <input type="number" name="server_id" class="form-control form-control-sm"
                               value="{{ old('server_id') }}">
                        @error('server_id')<div class="text-danger f-12">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Začátek <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="starts_at" class="form-control form-control-sm"
                               value="{{ old('starts_at') }}" required>
                        @error('starts_at')<div class="text-danger f-12">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Konec <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="ends_at" class="form-control form-control-sm"
                               value="{{ old('ends_at') }}" required>
                        @error('ends_at')<div class="text-danger f-12">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="notify_customers" value="1" id="notify_customers"
                               class="form-check-input" {{ old('notify_customers') ? 'checked' : '' }}>
                        <label class="form-check-label" for="notify_customers">Notifikovat zákazníky</label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Naplánovat</button>
                </form>
            </x-panel.card>
        </div>

        {{-- Table --}}
        <div class="col-span-8 xl:col-span-12">
            <x-panel.card title="Okna údržby">

                {{-- Filter --}}
                <form method="GET" action="{{ route('admin.maintenance-windows.index') }}" class="flex gap-2 mb-3">
                    <select name="status" class="form-select form-select-sm w-auto">
                        <option value="">Všechny statusy</option>
                        @foreach(['scheduled','in_progress','completed','cancelled'] as $s)
                            <option value="{{ $s }}" {{ $status === $s ? 'selected' : '' }}>{{ $s }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-sm btn-secondary">Filtrovat</button>
                    @if($status)
                        <a href="{{ route('admin.maintenance-windows.index') }}" class="btn btn-sm btn-outline-secondary">Zrušit filtr</a>
                    @endif
                </form>

                @if($windows->isEmpty())
                    <p class="text-muted">Žádná okna údržby.</p>
                @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Název</th>
                                <th>Server ID</th>
                                <th>Začátek</th>
                                <th>Konec</th>
                                <th>Notifikace</th>
                                <th>Status</th>
                                <th>Akce</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($windows as $window)
                            <tr>
                                <td class="f-12 text-muted">{{ $window->id }}</td>
                                <td class="f-w-500">{{ $window->title }}</td>
                                <td>{{ $window->server_id ?? '—' }}</td>
                                <td class="f-12">{{ $window->starts_at instanceof \Carbon\Carbon ? $window->starts_at->format('d.m.Y H:i') : \Carbon\Carbon::parse($window->starts_at)->format('d.m.Y H:i') }}</td>
                                <td class="f-12">{{ $window->ends_at instanceof \Carbon\Carbon ? $window->ends_at->format('d.m.Y H:i') : \Carbon\Carbon::parse($window->ends_at)->format('d.m.Y H:i') }}</td>
                                <td>
                                    @if($window->notify_customers)
                                        <span class="badge bg-success">Ano</span>
                                    @else
                                        <span class="badge bg-secondary">Ne</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $statusMap = [
                                            'scheduled'   => 'bg-primary',
                                            'in_progress' => 'bg-warning text-dark',
                                            'completed'   => 'bg-success',
                                            'cancelled'   => 'bg-secondary',
                                        ];
                                    @endphp
                                    <span class="badge {{ $statusMap[$window->status] ?? 'bg-secondary' }}">
                                        {{ $window->status }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex gap-2 items-center flex-wrap">
                                        {{-- Inline status PATCH --}}
                                        <form method="POST" action="{{ route('admin.maintenance-windows.update', $window) }}" class="flex gap-1">
                                            @csrf
                                            @method('PATCH')
                                            <select name="status" class="form-select form-select-sm" style="width:130px">
                                                @foreach(['scheduled','in_progress','completed','cancelled'] as $s)
                                                    <option value="{{ $s }}" {{ $window->status === $s ? 'selected' : '' }}>{{ $s }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn btn-xs btn-outline-primary">Uložit</button>
                                        </form>
                                        {{-- DELETE --}}
                                        <form method="POST" action="{{ route('admin.maintenance-windows.destroy', $window) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-xs btn-outline-danger"
                                                    onclick="return confirm('Opravdu smazat?')">Smazat</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $windows->appends(request()->query())->links() }}</div>
                @endif

            </x-panel.card>
        </div>

    </div>
</div>
@endsection
