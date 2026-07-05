@extends('layouts.panel')

@php
    $breadcrumbTitle = $zone->domain;
    $breadcrumbItems = ['DNS Manager' => route('panel.dns-manager.index'), $zone->domain => ''];
    $typeColors = [
        'A'     => 'success',
        'AAAA'  => 'success',
        'CNAME' => 'info',
        'MX'    => 'warning',
        'TXT'   => 'secondary',
        'NS'    => 'primary',
        'SRV'   => 'info',
        'CAA'   => 'danger',
        'PTR'   => 'light',
    ];
@endphp

@section('title', $zone->domain . ' — DNS')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="container">
        <div class="grid grid-cols-12 card-gap">

            {{-- ── Record table ─────────────────────────────────────── --}}
            <div class="col-span-8 xl:col-span-12">
                <div class="card">
                    <div class="card-header card-no-border">
                        <div class="header-top">
                            <h5>
                                <svg data-feather="list" style="width:18px;height:18px;vertical-align:-3px" class="me-1"></svg>
                                DNS záznamy — <span class="font-monospace">{{ $zone->domain }}</span>
                            </h5>
                            <div>
                                <span class="badge badge-light-{{ $zone->status->color() }}">{{ $zone->status->label() }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        @if ($records->isEmpty())
                            <p class="text-muted">Žádné záznamy. Přidejte první záznam pomocí formuláře vpravo.</p>
                        @else
                        <div class="table-responsive">
                            <table class="table table-borderless recent-table f-13">
                                <thead>
                                    <tr>
                                        <th>Typ</th>
                                        <th>Název</th>
                                        <th>Obsah</th>
                                        <th>TTL</th>
                                        <th>Priorita</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach ($records as $record)
                                <tr>
                                    <td>
                                        <span class="badge badge-light-{{ $typeColors[$record->type->value] ?? 'secondary' }} f-11 px-2">
                                            {{ $record->type->value }}
                                        </span>
                                    </td>
                                    <td class="font-monospace f-12">{{ $record->name }}</td>
                                    <td class="font-monospace f-12 text-break" style="max-width:280px">{{ $record->content }}</td>
                                    <td class="text-muted f-12">{{ number_format($record->ttl) }}</td>
                                    <td class="text-muted f-12">{{ $record->priority !== null ? $record->priority : '—' }}</td>
                                    <td class="text-end">
                                        <form method="POST"
                                              action="{{ route('panel.dns-manager.records.destroy', [$zone, $record]) }}"
                                              class="d-inline"
                                              onsubmit="return confirm('Smazat záznam?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-xs">
                                                <svg data-feather="trash-2" style="width:12px;height:12px"></svg>
                                            </button>
                                        </form>
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

            {{-- ── Add record ───────────────────────────────────────── --}}
            <div class="col-span-4 xl:col-span-12">
                <div class="card">
                    <div class="card-header card-no-border">
                        <div class="header-top">
                            <h5>Přidat DNS záznam</h5>
                        </div>
                    </div>
                    <div class="card-body custom-input pt-0">
                        <form method="POST" action="{{ route('panel.dns-manager.records.store', $zone) }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label f-12">Typ</label>
                                <select name="type" class="form-select form-select-sm @error('type') is-invalid @enderror">
                                    @foreach(\App\Domains\Dns\Enums\DnsRecordType::cases() as $t)
                                        <option value="{{ $t->value }}" {{ old('type') === $t->value ? 'selected' : '' }}>
                                            {{ $t->value }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label f-12">Název</label>
                                <input type="text" name="name"
                                       class="form-control form-control-sm font-monospace @error('name') is-invalid @enderror"
                                       placeholder="@ nebo subdoména"
                                       value="{{ old('name', '@') }}" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label f-12">Obsah</label>
                                <input type="text" name="content"
                                       class="form-control form-control-sm font-monospace @error('content') is-invalid @enderror"
                                       placeholder="IP nebo hodnota"
                                       value="{{ old('content') }}" required>
                                @error('content')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label f-12">TTL</label>
                                    <input type="number" name="ttl"
                                           class="form-control form-control-sm @error('ttl') is-invalid @enderror"
                                           value="{{ old('ttl', 3600) }}" min="60" max="86400" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label f-12">Priorita</label>
                                    <input type="number" name="priority"
                                           class="form-control form-control-sm @error('priority') is-invalid @enderror"
                                           value="{{ old('priority') }}" min="0" max="65535"
                                           placeholder="MX/SRV">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                <svg data-feather="plus" style="width:14px;height:14px" class="me-1"></svg>
                                Přidat záznam
                            </button>
                        </form>
                    </div>
                </div>

                <div class="mt-2">
                    <a href="{{ route('panel.dns-manager.index') }}" class="btn btn-outline-secondary btn-sm">
                        <svg data-feather="arrow-left" style="width:13px;height:13px" class="me-1"></svg>
                        Zpět na seznam zón
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
