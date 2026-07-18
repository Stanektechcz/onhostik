@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Webhook logy';
    $breadcrumbItems = ['Webhook logy' => ''];
@endphp

@section('title', 'Webhook logy')

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        {{-- Summary cards --}}
        <div class="grid grid-cols-12 gap-3 mb-3">
            <div class="col-span-3 md:col-span-6 sm:col-span-12">
                <div class="card small-widget">
                    <div class="card-body">
                        <span class="f-light">Celkem</span>
                        <div class="flex items-end gap-1 mt-1">
                            <h4>{{ number_format((int) ($counts->total ?? 0)) }}</h4>
                        </div>
                        <div class="bg-gradient">
                            <i data-feather="layers"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-span-3 md:col-span-6 sm:col-span-12">
                <div class="card small-widget">
                    <div class="card-body">
                        <span class="f-light">Zpracováno</span>
                        <div class="flex items-end gap-1 mt-1">
                            <h4 class="text-success">{{ number_format((int) ($counts->processed ?? 0)) }}</h4>
                        </div>
                        <div class="bg-gradient">
                            <i data-feather="check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-span-3 md:col-span-6 sm:col-span-12">
                <div class="card small-widget">
                    <div class="card-body">
                        <span class="f-light">Chyby</span>
                        <div class="flex items-end gap-1 mt-1">
                            <h4 class="text-danger">{{ number_format((int) ($counts->errors ?? 0)) }}</h4>
                        </div>
                        <div class="bg-gradient">
                            <i data-feather="alert-triangle"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-span-3 md:col-span-6 sm:col-span-12">
                <div class="card small-widget">
                    <div class="card-body">
                        <span class="f-light">Neplatný podpis</span>
                        <div class="flex items-end gap-1 mt-1">
                            <h4 class="text-warning">{{ number_format((int) ($counts->invalid_sig ?? 0)) }}</h4>
                        </div>
                        <div class="bg-gradient">
                            <i data-feather="shield-off"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <x-panel.card title="Webhook logy">
            <form method="GET" action="{{ route('admin.webhook-logs.index') }}" class="flex gap-2 mb-3 flex-wrap items-center">
                <select name="provider" class="form-select" style="max-width: 160px;">
                    <option value="">Vše (provider)</option>
                    <option value="comgate" @selected($provider === 'comgate')>Comgate</option>
                    <option value="stripe"  @selected($provider === 'stripe')>Stripe</option>
                    <option value="gopay"   @selected($provider === 'gopay')>GoPay</option>
                </select>
                <select name="processed" class="form-select" style="max-width: 160px;">
                    <option value="">Vše (stav)</option>
                    <option value="1" @selected($processed === '1')>Zpracováno</option>
                    <option value="0" @selected($processed === '0')>Nezpracováno</option>
                </select>
                <input type="date" name="date_from" class="form-control" style="max-width: 145px;"
                       value="{{ $dateFrom }}" title="Od">
                <input type="date" name="date_to" class="form-control" style="max-width: 145px;"
                       value="{{ $dateTo }}" title="Do">
                <div class="form-check ms-1">
                    <input type="checkbox" class="form-check-input" id="has_error" name="has_error"
                           value="1" @checked($hasError)>
                    <label class="form-check-label f-12" for="has_error">Jen s chybou</label>
                </div>
                <button type="submit" class="btn btn-outline-primary btn-sm">Filtrovat</button>
                @if($provider || $processed !== '' || $dateFrom || $dateTo || $hasError)
                    <a href="{{ route('admin.webhook-logs.index') }}" class="btn btn-outline-secondary btn-sm">×</a>
                @endif
                <span class="f-light f-12 ms-auto">{{ $logs->total() }} záznamů</span>
            </form>

            @if($logs->isEmpty())
                <div class="text-center py-5">
                    <i data-feather="inbox" style="width:40px;height:40px;" class="text-muted mb-3"></i>
                    <h6 class="f-light mt-2">Žádné webhook logy.</h6>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Provider</th>
                                <th>Event ID</th>
                                <th>Podpis</th>
                                <th>Stav</th>
                                <th>Čas</th>
                                <th>Chyba</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($logs as $log)
                                <tr>
                                    <td class="f-12 f-light">#{{ $log->id }}</td>
                                    <td>
                                        @php
                                            $providerColor = match($log->provider) {
                                                'stripe'  => 'primary',
                                                'comgate' => 'info',
                                                default   => 'secondary',
                                            };
                                        @endphp
                                        <span class="badge badge-light-{{ $providerColor }}">
                                            {{ ucfirst($log->provider ?? '—') }}
                                        </span>
                                    </td>
                                    <td class="f-12">
                                        <span title="{{ $log->event_id }}">
                                            {{ $log->event_id ? mb_substr($log->event_id, 0, 20) . (mb_strlen($log->event_id) > 20 ? '…' : '') : '—' }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($log->signature_valid === true)
                                            <span class="badge badge-light-success"><i data-feather="check" style="width:10px;height:10px;"></i> OK</span>
                                        @elseif($log->signature_valid === false)
                                            <span class="badge badge-light-danger"><i data-feather="x" style="width:10px;height:10px;"></i> Chybný</span>
                                        @else
                                            <span class="badge badge-light-secondary">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($log->processed)
                                            <span class="badge badge-light-success">Zpracováno</span>
                                        @else
                                            <span class="badge badge-light-warning">Čeká</span>
                                        @endif
                                    </td>
                                    <td class="f-12 f-light">{{ $log->created_at?->format('d.m.Y H:i:s') }}</td>
                                    <td class="f-12 text-danger" style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                        title="{{ $log->error_message }}">
                                        {{ $log->error_message ? mb_substr($log->error_message, 0, 60) . (mb_strlen($log->error_message) > 60 ? '…' : '') : '' }}
                                    </td>
                                    <td>
                                        <button type="button"
                                                class="btn btn-outline-secondary btn-xs"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#log-payload-{{ $log->id }}"
                                                title="Zobrazit payload">
                                            <i data-feather="eye" style="width:12px;height:12px;"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr class="collapse" id="log-payload-{{ $log->id }}">
                                    <td colspan="8" class="bg-light p-3">
                                        <div class="flex justify-between items-start">
                                            <div class="grow">
                                                @if($log->ip_address)
                                                    <p class="f-12 mb-1"><span class="f-light">IP:</span> {{ $log->ip_address }}</p>
                                                @endif
                                                @if($log->processed_at)
                                                    <p class="f-12 mb-1"><span class="f-light">Zpracováno:</span> {{ $log->processed_at->format('d.m.Y H:i:s') }}</p>
                                                @endif
                                                @if($log->error_message)
                                                    <div class="alert alert-danger py-2 mb-2 f-12">
                                                        <strong>Chyba:</strong> {{ $log->error_message }}
                                                    </div>
                                                @endif
                                                @if($log->payload)
                                                    <pre class="f-11 bg-white border rounded p-2 mb-0" style="max-height:300px;overflow:auto;">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $logs->links() }}
                </div>
            @endif
        </x-panel.card>
    </div>
@endsection
