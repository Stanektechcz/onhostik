@extends('layouts.panel')

@section('title', 'Dunning konfigurace')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 gap-4">
        <div class="col-span-12 md:col-span-8">
            <x-panel.card title="Dunning kroky">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Krok</th>
                                <th>Název</th>
                                <th class="text-right">Dní po splatnosti</th>
                                <th>Akce</th>
                                <th>Šablona</th>
                                <th>Aktivní</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($configs as $cfg)
                            <tr>
                                <td><span class="badge bg-primary">{{ $cfg->step }}</span></td>
                                <td>{{ $cfg->name }}</td>
                                <td class="text-right">{{ $cfg->days_after_due }}</td>
                                <td>
                                    @php $colors = ['email'=>'info','suspend'=>'warning','cancel'=>'danger']; @endphp
                                    <span class="badge bg-{{ $colors[$cfg->action] ?? 'secondary' }}">{{ $cfg->action }}</span>
                                </td>
                                <td class="text-muted small">{{ $cfg->email_template ?? '—' }}</td>
                                <td>
                                    @if($cfg->is_active)
                                        <span class="badge bg-success">Ano</span>
                                    @else
                                        <span class="badge bg-secondary">Ne</span>
                                    @endif
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.dunning-configs.destroy', $cfg) }}">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Smazat</button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">Žádná konfigurace dunning.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $configs->links() }}</div>
            </x-panel.card>
        </div>

        <div class="col-span-12 md:col-span-4">
            <x-panel.card title="Přidat krok">
                <form method="POST" action="{{ route('admin.dunning-configs.store') }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label">Název</label>
                        <input type="text" name="name" class="form-control" maxlength="100" value="{{ old('name') }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Krok (pořadí)</label>
                        <input type="number" name="step" class="form-control" min="1" max="10" value="{{ old('step', 1) }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Dní po splatnosti</label>
                        <input type="number" name="days_after_due" class="form-control" min="0" max="365" value="{{ old('days_after_due', 3) }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Akce</label>
                        <select name="action" class="form-select">
                            <option value="email" @selected(old('action') === 'email')>E-mail</option>
                            <option value="suspend" @selected(old('action') === 'suspend')>Pozastavit</option>
                            <option value="cancel" @selected(old('action') === 'cancel')>Zrušit</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Šablona e-mailu</label>
                        <input type="text" name="email_template" class="form-control" maxlength="100" value="{{ old('email_template') }}">
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="dc_active" value="1" @checked(old('is_active', true))>
                        <label class="form-check-label" for="dc_active">Aktivní</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-full">Přidat</button>
                </form>
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
