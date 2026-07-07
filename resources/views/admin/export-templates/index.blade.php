@extends('layouts.panel')

@section('title', 'Šablony exportu')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="row g-4">
        <div class="col-md-8">
            <x-panel.card title="Šablony exportu zákazníků">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Název</th>
                                <th>Entita</th>
                                <th>Formát</th>
                                <th>Sdílená</th>
                                <th>Vytvořil</th>
                                <th>Vytvořeno</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($templates as $tpl)
                            <tr>
                                <td>{{ $tpl->name }}</td>
                                <td><span class="badge bg-secondary">{{ $tpl->entity_type }}</span></td>
                                <td><span class="badge bg-info text-dark">{{ strtoupper($tpl->format) }}</span></td>
                                <td>
                                    @if($tpl->is_shared)
                                        <span class="badge bg-success">Ano</span>
                                    @else
                                        <span class="badge bg-secondary">Ne</span>
                                    @endif
                                </td>
                                <td class="text-muted small">{{ $tpl->creator?->email ?? '—' }}</td>
                                <td class="text-muted small">{{ $tpl->created_at->format('d.m.Y') }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.export-templates.destroy', $tpl) }}">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Smazat</button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">Žádné šablony.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $templates->links() }}</div>
            </x-panel.card>
        </div>

        <div class="col-md-4">
            <x-panel.card title="Vytvořit šablonu">
                <form method="POST" action="{{ route('admin.export-templates.store') }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label">Název</label>
                        <input type="text" name="name" class="form-control" maxlength="100" value="{{ old('name') }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Entita</label>
                        <select name="entity_type" class="form-select">
                            <option value="customers">Zákazníci</option>
                            <option value="invoices">Faktury</option>
                            <option value="services">Služby</option>
                            <option value="orders">Objednávky</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Sloupce (oddělené čárkou)</label>
                        <input type="text" name="columns_raw" class="form-control" placeholder="id,email,created_at" value="{{ old('columns_raw') }}">
                        @if($errors->has('columns'))
                            <div class="text-danger small">{{ $errors->first('columns') }}</div>
                        @endif
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Formát</label>
                        <select name="format" class="form-select">
                            <option value="csv">CSV</option>
                            <option value="xlsx">XLSX</option>
                            <option value="json">JSON</option>
                        </select>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="is_shared" class="form-check-input" id="tpl_shared" value="1" @checked(old('is_shared'))>
                        <label class="form-check-label" for="tpl_shared">Sdílet s ostatními adminy</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Vytvořit</button>
                </form>
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
