@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Helpdesk webhooky';
    $breadcrumbItems = ['Integrace' => '#', 'Helpdesk webhooky' => ''];
@endphp

@section('title', 'Helpdesk webhooky')

@section('content')
<div class="container-fluid">
    <x-panel.flash />
    <div class="row g-3">

        {{-- Create form --}}
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header card-no-border"><h5>Nový webhook</h5></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.helpdesk-webhooks.store') }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label f-12">Název *</label>
                            <input type="text" name="name"
                                   class="form-control form-control-sm @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}" placeholder="Náš Jira / Slack…" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-2">
                            <label class="form-label f-12">URL *</label>
                            <input type="url" name="url"
                                   class="form-control form-control-sm @error('url') is-invalid @enderror"
                                   value="{{ old('url') }}" placeholder="https://..." required>
                            @error('url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-2">
                            <label class="form-label f-12">Secret (HMAC signing)</label>
                            <input type="text" name="secret" class="form-control form-control-sm"
                                   value="{{ old('secret') }}" placeholder="Nepovinné">
                        </div>
                        <div class="mb-2">
                            <label class="form-label f-12">Události *</label>
                            @foreach(\App\Domains\Support\Models\HelpdeskWebhook::EVENTS as $key => $label)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="events[]" value="{{ $key }}"
                                       id="event_{{ $loop->index }}"
                                       @checked(in_array($key, old('events', [])))>
                                <label class="form-check-label f-12" for="event_{{ $loop->index }}">{{ $label }}</label>
                            </div>
                            @endforeach
                            @error('events')<div class="text-danger f-11 mt-1">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-2">
                            <label class="form-label f-12">Timeout (s)</label>
                            <input type="number" name="timeout_seconds" class="form-control form-control-sm"
                                   value="{{ old('timeout_seconds', 10) }}" min="1" max="60">
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                            <label class="form-check-label f-12">Aktivní</label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-100">Přidat webhook</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            {{-- Webhook list --}}
            <div class="card mb-3">
                <div class="card-header card-no-border"><h5>Webhooky ({{ $webhooks->count() }})</h5></div>
                <div class="card-body pt-0">
                    @if($webhooks->isEmpty())
                        <p class="text-center f-light py-4">Žádné webhooky. Přidejte první.</p>
                    @else
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>Název / URL</th>
                                    <th>Události</th>
                                    <th>Poslední volání</th>
                                    <th>Selhání</th>
                                    <th>Stav</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($webhooks as $wh)
                                <tr>
                                    <td>
                                        <div class="f-w-500">{{ $wh->name }}</div>
                                        <div class="f-11 f-light text-truncate" style="max-width:200px;">{{ $wh->url }}</div>
                                    </td>
                                    <td>
                                        @foreach($wh->events as $ev)
                                            <span class="badge badge-light-primary f-10 me-1">{{ $ev }}</span>
                                        @endforeach
                                    </td>
                                    <td class="f-12">
                                        {{ $wh->last_fired_at ? $wh->last_fired_at->diffForHumans() : '—' }}
                                    </td>
                                    <td class="f-12 {{ $wh->failure_count > 0 ? 'text-danger' : 'f-light' }}">
                                        {{ $wh->failure_count }}
                                    </td>
                                    <td>
                                        @if($wh->is_active)
                                            <span class="badge badge-light-success f-10">Aktivní</span>
                                        @else
                                            <span class="badge badge-light-secondary f-10">Neaktivní</span>
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        <button class="btn btn-outline-secondary btn-xs"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editWh{{ $wh->id }}">
                                            Upravit
                                        </button>
                                        <form method="POST"
                                              action="{{ route('admin.helpdesk-webhooks.regenerate', $wh) }}"
                                              class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-info btn-xs"
                                                    title="Vygenerovat nový secret">⟳</button>
                                        </form>
                                        <form method="POST"
                                              action="{{ route('admin.helpdesk-webhooks.destroy', $wh) }}"
                                              class="d-inline"
                                              onsubmit="return confirm('Smazat webhook?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-xs">×</button>
                                        </form>
                                    </td>
                                </tr>

                                {{-- Edit modal --}}
                                <div class="modal fade" id="editWh{{ $wh->id }}" tabindex="-1">
                                    <div class="modal-dialog">
                                        <form method="POST"
                                              action="{{ route('admin.helpdesk-webhooks.update', $wh) }}"
                                              class="modal-content">
                                            @csrf @method('PUT')
                                            <div class="modal-header">
                                                <h6 class="modal-title">Upravit webhook</h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-2">
                                                    <label class="form-label f-12">Název *</label>
                                                    <input type="text" name="name" class="form-control form-control-sm"
                                                           value="{{ $wh->name }}" required>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label f-12">URL *</label>
                                                    <input type="url" name="url" class="form-control form-control-sm"
                                                           value="{{ $wh->url }}" required>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label f-12">Události *</label>
                                                    @foreach(\App\Domains\Support\Models\HelpdeskWebhook::EVENTS as $key => $label)
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox"
                                                               name="events[]" value="{{ $key }}"
                                                               @checked(in_array($key, $wh->events))>
                                                        <label class="form-check-label f-12">{{ $label }}</label>
                                                    </div>
                                                    @endforeach
                                                </div>
                                                <div class="row g-2 mb-2">
                                                    <div class="col">
                                                        <label class="form-label f-12">Timeout (s)</label>
                                                        <input type="number" name="timeout_seconds"
                                                               class="form-control form-control-sm"
                                                               value="{{ $wh->timeout_seconds }}" min="1" max="60">
                                                    </div>
                                                </div>
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="checkbox"
                                                           name="is_active" value="1" @checked($wh->is_active)>
                                                    <label class="form-check-label f-12">Aktivní</label>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="submit" class="btn btn-primary btn-sm">Uložit</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Recent delivery log --}}
            @if($recentLog->isNotEmpty())
            <div class="card">
                <div class="card-header card-no-border"><h5>Posledních 20 doručení</h5></div>
                <div class="card-body pt-0">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Webhook</th>
                                    <th>Událost</th>
                                    <th>Čas</th>
                                    <th>Status</th>
                                    <th>ms</th>
                                    <th>Stav</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentLog as $del)
                                <tr>
                                    <td class="f-12">{{ $del->webhook?->name ?? '#'.$del->helpdesk_webhook_id }}</td>
                                    <td><code class="f-11">{{ $del->event }}</code></td>
                                    <td class="f-12">{{ $del->fired_at->format('d.m. H:i:s') }}</td>
                                    <td class="f-12">{{ $del->status_code ?? '—' }}</td>
                                    <td class="f-12">{{ $del->duration_ms ?? '—' }}</td>
                                    <td>
                                        @if($del->success)
                                            <span class="badge badge-light-success f-10">OK</span>
                                        @else
                                            <span class="badge badge-light-danger f-10">Selhalo</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
