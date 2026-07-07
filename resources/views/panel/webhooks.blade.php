@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Webhooky';
    $breadcrumbItems = ['Vývojář' => route('panel.developer.index'), 'Webhooky' => ''];
@endphp

@section('title', 'Odchozí webhooky')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="row g-4">
        {{-- Webhook list --}}
        <div class="col-md-8">
            <div class="card card-no-border">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5>Odchozí webhooky</h5>
                    <span class="f-light f-12">{{ $webhooks->count() }} / {{ $maxWebhooks }}</span>
                </div>
                <div class="card-body">
                    @if($webhooks->isEmpty())
                        <p class="f-light f-12 mb-0">Zatím nemáte žádné webhooky. Vytvořte první pomocí formuláře.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Název</th>
                                        <th>URL</th>
                                        <th>Události</th>
                                        <th>Stav</th>
                                        <th>Doručení</th>
                                        <th>Přidán</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($webhooks as $webhook)
                                        <tr>
                                            <td class="f-w-500">{{ $webhook->name }}</td>
                                            <td class="f-12 f-light" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                                title="{{ $webhook->url }}">
                                                {{ $webhook->url }}
                                            </td>
                                            <td>
                                                @foreach($webhook->events as $evt)
                                                    <span class="badge badge-light-primary f-10">{{ $evt }}</span>
                                                @endforeach
                                            </td>
                                            <td>
                                                @if($webhook->is_active)
                                                    <span class="badge badge-light-success f-10">Aktivní</span>
                                                @else
                                                    <span class="badge badge-light-secondary f-10">Neaktivní</span>
                                                @endif
                                            </td>
                                            <td class="f-12">
                                                <a href="{{ route('panel.webhooks.deliveries', $webhook) }}" class="txt-primary">
                                                    {{ $webhook->deliveries_count }}
                                                </a>
                                            </td>
                                            <td class="f-light f-12">{{ $webhook->created_at?->format('d.m.Y') }}</td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <form method="POST" action="{{ route('panel.webhooks.toggle', $webhook) }}">
                                                        @csrf
                                                        <button type="submit"
                                                                class="btn btn-xs {{ $webhook->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}"
                                                                title="{{ $webhook->is_active ? 'Deaktivovat' : 'Aktivovat' }}">
                                                            <i data-feather="{{ $webhook->is_active ? 'pause' : 'play' }}"
                                                               style="width:11px;height:11px;"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="{{ route('panel.webhooks.destroy', $webhook) }}"
                                                          onsubmit="return confirm('Smazat webhook {{ $webhook->name }}?')">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="btn btn-xs btn-outline-danger"
                                                                title="Smazat">
                                                            <i data-feather="trash-2" style="width:11px;height:11px;"></i>
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

        {{-- Create form --}}
        <div class="col-md-4">
            <div class="card card-no-border">
                <div class="card-header">
                    <h5>Nový webhook</h5>
                </div>
                <div class="card-body">
                    @if($webhooks->count() >= $maxWebhooks)
                        <div class="alert alert-warning f-12">Dosažen limit {{ $maxWebhooks }} webhooků. Nejprve smažte starý.</div>
                    @else
                        <form method="POST" action="{{ route('panel.webhooks.store') }}">
                            @csrf
                            <div class="mb-3 custom-input">
                                <label class="form-label">Název</label>
                                <input type="text" name="name" value="{{ old('name') }}"
                                       class="form-control @error('name') is-invalid @enderror"
                                       placeholder="např. Moje integrace" maxlength="100" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3 custom-input">
                                <label class="form-label">URL endpointu</label>
                                <input type="url" name="url" value="{{ old('url') }}"
                                       class="form-control @error('url') is-invalid @enderror"
                                       placeholder="https://vas-server.cz/webhook" maxlength="500" required>
                                @error('url')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Události</label>
                                @error('events')
                                    <div class="text-danger f-12 mb-1">{{ $message }}</div>
                                @enderror
                                @php
                                    $eventGroups = [
                                        'Služby'   => ['service.created', 'service.status_changed', 'service.suspended', 'service.terminated'],
                                        'Faktury'  => ['invoice.created', 'invoice.paid', 'invoice.overdue'],
                                        'Monitoring' => ['monitor.down', 'monitor.up'],
                                        'Tickety'  => ['ticket.created', 'ticket.replied', 'ticket.closed'],
                                        'Vše'      => ['*'],
                                    ];
                                    $oldEvents = old('events', []);
                                @endphp
                                @foreach($eventGroups as $groupName => $groupEvents)
                                    <div class="f-12 f-w-500 mt-2 mb-1 text-muted">{{ $groupName }}</div>
                                    @foreach($groupEvents as $evt)
                                        <div class="form-check mb-1">
                                            <input class="form-check-input" type="checkbox"
                                                   name="events[]" value="{{ $evt }}"
                                                   id="evt_{{ str_replace(['.','*'], ['_','all'], $evt) }}"
                                                   {{ in_array($evt, $oldEvents, true) ? 'checked' : '' }}>
                                            <label class="form-check-label f-12"
                                                   for="evt_{{ str_replace(['.','*'], ['_','all'], $evt) }}">
                                                <code style="font-size:11px;">{{ $evt }}</code>
                                            </label>
                                        </div>
                                    @endforeach
                                @endforeach
                            </div>

                            <div class="mb-3 custom-input">
                                <label class="form-label">Tajný klíč (nepovinné)</label>
                                <input type="text" name="secret" value="{{ old('secret') }}"
                                       class="form-control @error('secret') is-invalid @enderror"
                                       placeholder="Použit pro HMAC podpis" maxlength="255">
                                <div class="f-11 f-light mt-1">
                                    Pokud zadáte, každý požadavek bude obsahovat hlavičku
                                    <code>X-Webhook-Signature</code>.
                                </div>
                                @error('secret')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <button type="submit" class="btn btn-primary text-white w-100">
                                <i data-feather="rss" style="width:13px;height:13px;"></i>
                                Vytvořit webhook
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="card card-no-border mt-3">
                <div class="card-body">
                    <h6 class="mb-2">Formát požadavku</h6>
                    <p class="f-12 f-light mb-2">
                        Webhooky jsou odesílány jako <code>POST</code> s JSON tělem a hlavičkami:
                    </p>
                    <code class="f-11 d-block p-2 rounded mb-1" style="background:rgba(0,0,0,.05);">
                        Content-Type: application/json<br>
                        X-Webhook-Event: invoice.paid<br>
                        X-Webhook-Signature: sha256=...
                    </code>
                    <p class="f-11 f-light mt-2 mb-0">
                        Váš endpoint musí odpovědět stavovým kódem <strong>2xx</strong> do 10 sekund.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
