@extends('layouts.panel')

@section('title', 'Oznámení portálu')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 card-gap">
        {{-- Create form --}}
        <div class="col-span-4 xl:col-span-12">
            <x-panel.card title="Nové oznámení">
                <form method="POST" action="{{ route('admin.portal-announcements.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label">Nadpis <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control form-control-sm @error('title') is-invalid @enderror"
                            value="{{ old('title') }}" maxlength="255" required>
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Text oznámení <span class="text-danger">*</span></label>
                        <textarea name="body" class="form-control form-control-sm @error('body') is-invalid @enderror"
                            rows="4" required>{{ old('body') }}</textarea>
                        @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Typ <span class="text-danger">*</span></label>
                        <select name="type" class="form-select form-select-sm @error('type') is-invalid @enderror">
                            <option value="info"    @selected(old('type') === 'info')>Info</option>
                            <option value="warning" @selected(old('type') === 'warning')>Warning</option>
                            <option value="success" @selected(old('type') === 'success')>Success</option>
                            <option value="danger"  @selected(old('type') === 'danger')>Danger</option>
                        </select>
                        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Cílová skupina <span class="text-danger">*</span></label>
                        <select name="target_audience" class="form-select form-select-sm @error('target_audience') is-invalid @enderror">
                            <option value="all"       @selected(old('target_audience') === 'all')>Všichni</option>
                            <option value="customers" @selected(old('target_audience') === 'customers')>Zákazníci</option>
                            <option value="resellers" @selected(old('target_audience') === 'resellers')>Resellers</option>
                        </select>
                        @error('target_audience')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Platnost do</label>
                        <input type="datetime-local" name="expires_at"
                            class="form-control form-control-sm @error('expires_at') is-invalid @enderror"
                            value="{{ old('expires_at') }}">
                        @error('expires_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm">Vytvořit oznámení</button>
                </form>
            </x-panel.card>
        </div>

        {{-- Announcements table --}}
        <div class="col-span-8 xl:col-span-12">
            <x-panel.card title="Oznámení">
                @if($announcements->isEmpty())
                    <p class="text-muted">Žádná oznámení.</p>
                @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nadpis</th>
                                <th>Typ</th>
                                <th>Skupina</th>
                                <th class="text-center">Publikováno</th>
                                <th>Publikováno dne</th>
                                <th>Platnost do</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($announcements as $announcement)
                            <tr>
                                <td class="f-12 text-muted">{{ $announcement->id }}</td>
                                <td class="f-w-500">{{ $announcement->title }}</td>
                                <td>
                                    @php
                                        $typeColors = [
                                            'info'    => 'primary',
                                            'warning' => 'warning',
                                            'success' => 'success',
                                            'danger'  => 'danger',
                                        ];
                                        $tc = $typeColors[$announcement->type] ?? 'secondary';
                                    @endphp
                                    <span class="badge bg-{{ $tc }}">{{ ucfirst($announcement->type) }}</span>
                                </td>
                                <td>
                                    @php
                                        $audienceLabels = [
                                            'all'       => 'Všichni',
                                            'customers' => 'Zákazníci',
                                            'resellers' => 'Resellers',
                                        ];
                                    @endphp
                                    <span class="badge bg-secondary">{{ $audienceLabels[$announcement->target_audience] ?? $announcement->target_audience }}</span>
                                </td>
                                <td class="text-center">
                                    <form method="POST" action="{{ route('admin.portal-announcements.update', $announcement) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-xs {{ $announcement->is_published ? 'btn-success' : 'btn-outline-secondary' }}"
                                            title="{{ $announcement->is_published ? 'Klikem zrušíte publikování' : 'Klikem publikujete' }}">
                                            {{ $announcement->is_published ? 'Ano' : 'Ne' }}
                                        </button>
                                    </form>
                                </td>
                                <td class="f-12">
                                    {{ $announcement->published_at?->format('d.m.Y H:i') ?? '—' }}
                                </td>
                                <td class="f-12">
                                    @if($announcement->expires_at)
                                        @if($announcement->expires_at->isPast())
                                            <span class="text-danger">{{ $announcement->expires_at->format('d.m.Y H:i') }}</span>
                                        @else
                                            {{ $announcement->expires_at->format('d.m.Y H:i') }}
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.portal-announcements.destroy', $announcement) }}"
                                        onsubmit="return confirm('Smazat oznámení?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-outline-danger">Smazat</button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $announcements->links() }}</div>
                @endif
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
