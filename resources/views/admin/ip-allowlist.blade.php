@extends('layouts.panel')

@php
    $breadcrumbTitle = 'IP Allowlist';
    $breadcrumbItems = ['Admin' => route('admin.dashboard'), 'Zabezpečení' => '#', 'IP Allowlist' => ''];
@endphp

@section('title', 'IP Allowlist pro admin panel')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    @if($entries->isEmpty())
        <div class="alert alert-light-warning mb-3">
            <i data-feather="alert-triangle" style="width:14px;height:14px"></i>
            <strong>Allowlist je prázdný</strong> — přístup není omezen. Přidejte alespoň jeden záznam pro aktivaci ochrany.
        </div>
    @else
        <div class="alert alert-light-info mb-3 f-12">
            <i data-feather="info" style="width:14px;height:14px"></i>
            Vaše aktuální IP adresa: <strong>{{ $currentIp }}</strong>
            @if($entries->where('is_active', true)->filter(fn ($e) => $e->containsIp($currentIp))->isNotEmpty())
                <span class="badge bg-success ms-1">v allowlistu</span>
            @else
                <span class="badge bg-danger ms-1">NENÍ v allowlistu</span>
            @endif
        </div>
    @endif

    <div class="grid grid-cols-12 gap-3">
        <div class="col-span-12 lg:col-span-8">
            <div class="card">
                <div class="card-header py-3">
                    <h6 class="mb-0">Povolené IP adresy a rozsahy</h6>
                </div>
                <div class="card-body p-0">
                    @if($entries->isEmpty())
                        <div class="text-center py-4 text-muted f-12">
                            <i data-feather="shield-off" style="width:28px;height:28px;" class="mb-2 block mx-auto"></i>
                            Žádné záznamy — ochrana není aktivní.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>CIDR</th>
                                        <th>Popis</th>
                                        <th>Přidal</th>
                                        <th>Stav</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($entries as $entry)
                                        <tr>
                                            <td class="font-monospace font-semibold f-12">{{ $entry->cidr }}</td>
                                            <td class="f-12">{{ $entry->label ?? '—' }}</td>
                                            <td class="f-12 text-muted">{{ $entry->createdBy?->name ?? '—' }}</td>
                                            <td>
                                                @if($entry->is_active)
                                                    <span class="badge bg-success">Aktivní</span>
                                                @else
                                                    <span class="badge bg-secondary">Neaktivní</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="flex gap-1">
                                                    <form method="POST" action="{{ route('admin.ip-allowlist.update', $entry) }}">
                                                        @csrf @method('PUT')
                                                        <input type="hidden" name="is_active" value="{{ $entry->is_active ? '0' : '1' }}">
                                                        <input type="hidden" name="label" value="{{ $entry->label }}">
                                                        <button type="submit" class="btn btn-xs btn-outline-{{ $entry->is_active ? 'secondary' : 'success' }}" title="{{ $entry->is_active ? 'Deaktivovat' : 'Aktivovat' }}">
                                                            <i data-feather="{{ $entry->is_active ? 'pause' : 'play' }}" style="width:12px;height:12px"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="{{ route('admin.ip-allowlist.destroy', $entry) }}"
                                                          onsubmit="return confirm('Smazat tento záznam?')">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="btn btn-xs btn-outline-danger">
                                                            <i data-feather="trash-2" style="width:12px;height:12px"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-span-12 lg:col-span-4">
            <div class="card">
                <div class="card-header py-3">
                    <h6 class="mb-0">Přidat IP / rozsah</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.ip-allowlist.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label f-12 f-w-600">CIDR notace</label>
                            <input type="text" name="cidr" maxlength="50"
                                value="{{ old('cidr') }}"
                                class="form-control form-control-sm font-monospace @error('cidr') is-invalid @enderror"
                                placeholder="Např. 192.168.1.0/24 nebo 1.2.3.4/32">
                            @error('cidr')<div class="invalid-feedback f-12">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label f-12 f-w-600">Popis (volitelný)</label>
                            <input type="text" name="label" maxlength="120"
                                value="{{ old('label') }}"
                                class="form-control form-control-sm @error('label') is-invalid @enderror"
                                placeholder="Např. Kancelář Praha">
                            @error('label')<div class="invalid-feedback f-12">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-full">
                            <i data-feather="plus" style="width:13px;height:13px"></i>
                            Přidat
                        </button>
                    </form>

                    <hr class="my-3">
                    <p class="f-12 text-muted mb-1">
                        <strong>Příklady CIDR:</strong>
                    </p>
                    <ul class="f-12 text-muted mb-0 ps-3">
                        <li><code>1.2.3.4/32</code> — jedna IP adresa</li>
                        <li><code>10.0.0.0/8</code> — celá privátní síť</li>
                        <li><code>192.168.1.0/24</code> — podsíť /24</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
