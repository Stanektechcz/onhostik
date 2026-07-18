@extends('layouts.panel')

@section('title', $endpoint->exists ? 'Upravit webhook endpoint' : 'Nový webhook endpoint')

@section('content')
<div class="container-fluid py-4" style="max-width:600px">

    <h1 class="h4 mb-4">{{ $endpoint->exists ? 'Upravit webhook endpoint' : 'Nový webhook endpoint' }}</h1>

    <div class="card">
        <div class="card-body">
            <form method="POST"
                  action="{{ $endpoint->exists
                      ? route('admin.webhooks.endpoint.update', $endpoint)
                      : route('admin.webhooks.endpoint.store') }}">
                @csrf
                @if($endpoint->exists) @method('PUT') @endif

                @if($errors->any())
                    <div class="alert alert-danger py-2">
                        <ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                @endif

                <div class="mb-3">
                    <label class="form-label">Název *</label>
                    <input type="text" name="name" value="{{ old('name', $endpoint->name) }}"
                           class="form-control @error('name') is-invalid @enderror" required maxlength="100">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                @if(!$endpoint->exists)
                <div class="mb-3">
                    <label class="form-label">Identifikátor zdroje (source) *</label>
                    <input type="text" name="source" value="{{ old('source') }}"
                           class="form-control @error('source') is-invalid @enderror"
                           required maxlength="50" placeholder="stripe">
                    <div class="form-text">URL: <code>POST /webhook/{source}</code>. Nelze změnit po vytvoření.</div>
                    @error('source')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                @endif

                <div class="mb-3">
                    <label class="form-label">Tajný klíč (HMAC secret)</label>
                    <input type="password" name="secret" autocomplete="new-password"
                           class="form-control @error('secret') is-invalid @enderror" maxlength="255"
                           placeholder="{{ $endpoint->exists ? '(prázdné = zachovat stávající)' : '' }}">
                    @error('secret')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Klíč se nikdy nezobrazuje ani nezapisuje do logů.</div>
                </div>

                <div class="grid grid-cols-12 gap-3 mb-3">
                    <div class="col-span-12 md:col-span-6">
                        <label class="form-label">Algoritmus podpisu</label>
                        <select name="signature_algo" class="form-select">
                            @foreach(['sha256' => 'SHA-256', 'sha512' => 'SHA-512', 'sha1' => 'SHA-1'] as $v => $l)
                                <option value="{{ $v }}" @selected(old('signature_algo', $endpoint->signature_algo ?? 'sha256') === $v)>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-span-12 md:col-span-6">
                        <label class="form-label">Hlavička podpisu</label>
                        <input type="text" name="signature_header"
                               value="{{ old('signature_header', $endpoint->signature_header ?? 'X-Signature') }}"
                               class="form-control" maxlength="100">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Povolené typy událostí</label>
                    <textarea name="allowed_events" rows="4" class="form-control" placeholder="payment_intent.succeeded&#10;charge.succeeded&#10;(prázdné = vše)"
                    >{{ old('allowed_events', $endpoint->exists && $endpoint->allowed_events ? implode("\n", $endpoint->allowed_events) : '') }}</textarea>
                    <div class="form-text">Jeden typ události na řádek. Prázdné = přijímat vše.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Popis</label>
                    <textarea name="description" rows="2" class="form-control" maxlength="500"
                    >{{ old('description', $endpoint->description) }}</textarea>
                </div>

                <div class="form-check mb-4">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                           @checked(old('is_active', $endpoint->is_active ?? true))>
                    <label class="form-check-label" for="is_active">Endpoint je aktivní</label>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">Uložit</button>
                    <a href="{{ route('admin.webhooks.inbound.index') }}" class="btn btn-outline-secondary btn-sm">Zpět</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
