@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Dunning — upomínky po splatnosti';
    $breadcrumbItems = ['Dunning' => ''];
@endphp

@section('title', 'Dunning — upomínky po splatnosti')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- KPI strip --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <x-panel.stat-widget
                label="Celkem po splatnosti"
                :value="$stats['total']"
                icon="alert-triangle"
                color="danger" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-panel.stat-widget
                label="Pozastavený dunning"
                :value="$stats['paused']"
                icon="pause-circle"
                color="secondary" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-panel.stat-widget
                label="Kritické (>7 dní)"
                :value="$stats['critical']"
                icon="alert-octagon"
                color="warning" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-panel.stat-widget
                label="1–3 dny po splatnosti"
                :value="$stats['stage1']"
                icon="clock"
                color="info" />
        </div>
    </div>

    <x-panel.card title="Faktury po splatnosti">
        <div class="table-responsive">
            <table class="table table-hover f-13">
                <thead>
                    <tr>
                        <th>Faktura</th>
                        <th>Zákazník</th>
                        <th class="text-end">Částka</th>
                        <th>Splatnost</th>
                        <th>Dní po spl.</th>
                        <th class="text-center">1d</th>
                        <th class="text-center">3d</th>
                        <th class="text-center">7d</th>
                        <th>Dunning</th>
                        <th class="text-end">Akce</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                        @php
                            $daysOverdue = (int) max(0, $invoice->due_date?->diffInDays(now()) ?? 0);
                            $paused      = $invoice->isDunningPaused();
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('admin.invoices.show', $invoice) }}" class="f-12">
                                    {{ $invoice->number }}
                                </a>
                            </td>
                            <td>
                                @if($invoice->customer)
                                    <a href="{{ route('admin.customers.show', $invoice->customer) }}" class="f-12">
                                        {{ $invoice->customer->email }}
                                    </a>
                                @else
                                    <span class="f-light f-12">—</span>
                                @endif
                            </td>
                            <td class="text-end f-w-600 f-12">
                                <x-panel.money :money="$invoice->total" />
                            </td>
                            <td class="f-12">{{ $invoice->due_date?->format('d.m.Y') ?? '—' }}</td>
                            <td>
                                <span class="badge badge-light-{{ $daysOverdue >= 7 ? 'danger' : ($daysOverdue >= 3 ? 'warning' : 'info') }} f-11">
                                    {{ $daysOverdue }}d
                                </span>
                            </td>
                            {{-- Milestone checks --}}
                            <td class="text-center">
                                @if($invoice->reminder_1d_sent_at)
                                    <i data-feather="check-circle" style="width:14px;height:14px;color:#00c767;"></i>
                                @else
                                    <i data-feather="circle" style="width:14px;height:14px;color:#ced4da;"></i>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($invoice->reminder_3d_sent_at)
                                    <i data-feather="check-circle" style="width:14px;height:14px;color:#00c767;"></i>
                                @else
                                    <i data-feather="circle" style="width:14px;height:14px;color:#ced4da;"></i>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($invoice->reminder_7d_sent_at)
                                    <i data-feather="check-circle" style="width:14px;height:14px;color:#00c767;"></i>
                                @else
                                    <i data-feather="circle" style="width:14px;height:14px;color:#ced4da;"></i>
                                @endif
                            </td>
                            <td>
                                @if($paused)
                                    <span class="badge badge-light-secondary f-10">
                                        <i data-feather="pause" style="width:10px;height:10px;"></i>
                                        Poz. do {{ $invoice->dunning_paused_until?->format('d.m.Y') }}
                                    </span>
                                @else
                                    <span class="badge badge-light-success f-10">
                                        <i data-feather="play" style="width:10px;height:10px;"></i>
                                        Aktivní
                                    </span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($paused)
                                    <form method="POST" action="{{ route('admin.dunning.resume', $invoice) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-outline-success">
                                            <i data-feather="play" style="width:11px;height:11px;"></i> Obnovit
                                        </button>
                                    </form>
                                @else
                                    <button type="button" class="btn btn-xs btn-outline-secondary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#pause-modal-{{ $invoice->id }}">
                                        <i data-feather="pause" style="width:11px;height:11px;"></i> Pozastavit
                                    </button>

                                    {{-- Pause modal --}}
                                    <div class="modal fade" id="pause-modal-{{ $invoice->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-sm">
                                            <div class="modal-content">
                                                <form method="POST" action="{{ route('admin.dunning.pause', $invoice) }}">
                                                    @csrf
                                                    <div class="modal-header">
                                                        <h6 class="modal-title f-13">Pozastavit dunning — {{ $invoice->number }}</h6>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <label class="form-label f-12">Pozastavit na (dní)</label>
                                                        <input type="number" name="days" class="form-control form-control-sm"
                                                               value="7" min="1" max="90" required>
                                                        <p class="f-11 f-light mt-2 mb-0">
                                                            Zákazník nebude upomínán ani pozastaven po dobu pauzy.
                                                        </p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Zrušit</button>
                                                        <button type="submit" class="btn btn-sm btn-warning">Pozastavit</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center f-light f-13 py-4">
                                <i data-feather="check-circle" style="width:20px;height:20px;color:#00c767;"></i>
                                Žádné faktury po splatnosti.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $invoices->links() }}
    </x-panel.card>
</div>
@endsection
