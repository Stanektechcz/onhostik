@extends('layouts.panel')

@php
    $breadcrumbTitle = 'DNS Manager';
    $breadcrumbItems = ['DNS Manager' => ''];
@endphp

@section('title', 'DNS Manager')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="container">
        <div class="grid grid-cols-12 card-gap">

            {{-- ── Zone list ────────────────────────────────────────── --}}
            <div class="col-span-8 xl:col-span-12">
                <div class="card">
                    <div class="card-header card-no-border">
                        <div class="header-top">
                            <h5>
                                <svg data-feather="globe" style="width:18px;height:18px;vertical-align:-3px" class="me-1"></svg>
                                Vaše DNS zóny
                            </h5>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        @if ($zones->isEmpty())
                            <p class="text-muted">Zatím nemáte žádné DNS zóny. Přidejte doménu vpravo.</p>
                        @else
                        <div class="table-responsive">
                            <table class="table table-borderless recent-table">
                                <thead>
                                    <tr>
                                        <th>Doména</th>
                                        <th>Stav</th>
                                        <th>Záznamy</th>
                                        <th>Přidáno</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach ($zones as $zone)
                                <tr>
                                    <td>
                                        <a href="{{ route('panel.dns-manager.show', $zone) }}" class="f-w-500">
                                            {{ $zone->domain }}
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge badge-light-{{ $zone->status->color() }} f-11">
                                            {{ $zone->status->label() }}
                                        </span>
                                    </td>
                                    <td class="text-muted f-12">{{ $zone->records_count }}</td>
                                    <td class="text-muted f-12">{{ $zone->created_at->format('d.m.Y') }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('panel.dns-manager.show', $zone) }}" class="btn btn-outline-primary btn-xs me-1">
                                            <svg data-feather="edit-2" style="width:12px;height:12px"></svg>
                                        </a>
                                        <form method="POST" action="{{ route('panel.dns-manager.destroy', $zone) }}"
                                              class="inline"
                                              onsubmit="return confirm('Smazat zónu {{ $zone->domain }} i se všemi záznamy?')">
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

            {{-- ── Add zone ─────────────────────────────────────────── --}}
            <div class="col-span-4 xl:col-span-12">
                <div class="card">
                    <div class="card-header card-no-border">
                        <div class="header-top">
                            <h5>Přidat DNS zónu</h5>
                        </div>
                    </div>
                    <div class="card-body custom-input pt-0">
                        <form method="POST" action="{{ route('panel.dns-manager.store') }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Doménové jméno</label>
                                <input type="text" name="domain"
                                       class="form-control @error('domain') is-invalid @enderror"
                                       placeholder="vasedomena.cz"
                                       value="{{ old('domain') }}" required>
                                @error('domain')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">Přidejte doménu, kterou chcete spravovat přes tento DNS Manager.</div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <svg data-feather="plus" style="width:14px;height:14px" class="me-1"></svg>
                                Přidat zónu
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-body p-3 f-12">
                        <p class="f-w-600 mb-1">
                            <svg data-feather="info" style="width:13px;height:13px" class="me-1 font-primary"></svg>
                            Jak nastavit NS záznamy
                        </p>
                        <p class="text-muted mb-1">Přejděte k registrátoru vaší domény a nastavte tyto NS záznamy:</p>
                        <code class="block f-12 text-dark mb-0">ns1.onhost.cz</code>
                        <code class="block f-12 text-dark">ns2.onhost.cz</code>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
