@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Developer Portal';
    $breadcrumbItems = ['Účet' => '#', 'Developer Portal' => ''];
@endphp

@section('title', 'Developer Portal')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    @if(session('new_token'))
        <div class="alert alert-success border-0 mb-4">
            <h6 class="alert-heading mb-2">API token vytvořen — zkopírujte ho nyní</h6>
            <div class="flex gap-2 items-center">
                <code class="grow p-2 rounded" style="background:rgba(0,0,0,.06);word-break:break-all;font-size:13px;">
                    {{ session('new_token') }}
                </code>
                <button class="btn btn-sm btn-outline-success"
                        onclick="navigator.clipboard.writeText('{{ session('new_token') }}');this.textContent='Zkopírováno!'">
                    Kopírovat
                </button>
            </div>
            <div class="f-12 mt-1 text-muted">Token se zobrazí pouze jednou.</div>
        </div>
    @endif

    @if(session('new_secret'))
        <div class="alert alert-warning border-0 mb-4">
            <h6 class="alert-heading mb-2">Client secret — zkopírujte ho nyní</h6>
            <div class="flex gap-2 items-center">
                <code class="grow p-2 rounded" style="background:rgba(0,0,0,.06);word-break:break-all;font-size:13px;">
                    {{ session('new_secret') }}
                </code>
                <button class="btn btn-sm btn-outline-warning"
                        onclick="navigator.clipboard.writeText('{{ session('new_secret') }}');this.textContent='Zkopírováno!'">
                    Kopírovat
                </button>
            </div>
            <div class="f-12 mt-1 text-muted">Secret se zobrazí pouze jednou. Po zavření stránky ho nelze znovu zobrazit.</div>
        </div>
    @endif

    <div class="grid grid-cols-12 gap-4">

        {{-- API Tokens --}}
        <div class="col-span-12 lg:col-span-6">
            <div class="card card-no-border">
                <div class="card-header flex justify-between items-center">
                    <h5 class="mb-0">API tokeny (Sanctum)</h5>
                    <span class="f-light f-12">{{ $tokens->count() }} / 5</span>
                </div>
                <div class="card-body">
                    @forelse($tokens as $token)
                        <div class="flex items-center justify-between border rounded p-2 mb-2">
                            <div>
                                <div class="font-semibold small">{{ $token->name }}</div>
                                <div class="text-muted" style="font-size:11px">
                                    Abilities: {{ implode(', ', $token->abilities) }}
                                    &nbsp;·&nbsp; {{ $token->created_at->format('d.m.Y') }}
                                </div>
                            </div>
                            <form method="POST" action="{{ route('panel.account.api-tokens.destroy', $token->id) }}"
                                  onsubmit="return confirm('Smazat token?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Zrušit</button>
                            </form>
                        </div>
                    @empty
                        <p class="text-muted small">Žádné aktivní tokeny.</p>
                    @endforelse

                    @if($tokens->count() < 5)
                        <hr>
                        <form method="POST" action="{{ route('panel.account.api-tokens.store') }}" class="mt-3">
                            @csrf
                            <div class="mb-2">
                                <input type="text" name="name" class="form-control form-control-sm @error('name') is-invalid @enderror"
                                       placeholder="Název tokenu" value="{{ old('name') }}" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-2">
                                <label class="small text-muted mb-1">Oprávnění</label>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($abilities as $ability)
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" name="abilities[]"
                                                   value="{{ $ability }}" id="ab_{{ $ability }}"
                                                   {{ $ability === 'read' ? 'checked disabled' : '' }}>
                                            <label class="form-check-label small" for="ab_{{ $ability }}">{{ $ability }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">Vytvořit token</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        {{-- OAuth Applications --}}
        <div class="col-span-12 lg:col-span-6">
            <div class="card card-no-border">
                <div class="card-header flex justify-between items-center">
                    <h5 class="mb-0">OAuth aplikace</h5>
                    <span class="f-light f-12">{{ $oauthApps->count() }} / 10</span>
                </div>
                <div class="card-body">
                    @forelse($oauthApps as $app)
                        <div class="border rounded p-3 mb-3">
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="font-semibold">{{ $app->name }}</div>
                                    <div class="text-muted" style="font-size:11px">
                                        Client ID: <code>{{ $app->client_id }}</code>
                                    </div>
                                    <div class="text-muted" style="font-size:11px">
                                        @if($app->last_used_at)
                                            Použito: {{ $app->last_used_at->diffForHumans() }}
                                        @else
                                            Nikdy nepoužito
                                        @endif
                                    </div>
                                </div>
                                <span class="badge bg-{{ $app->is_active ? 'success' : 'secondary' }}">
                                    {{ $app->is_active ? 'Aktivní' : 'Neaktivní' }}
                                </span>
                            </div>
                            <div class="flex gap-2 mt-2">
                                <form method="POST" action="{{ route('panel.developer.oauth-apps.regen', $app) }}">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-xs btn-outline-warning btn-sm"
                                            onclick="return confirm('Obnovit secret? Starý přestane fungovat.')">
                                        Obnovit secret
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('panel.developer.oauth-apps.destroy', $app) }}"
                                      onsubmit="return confirm('Smazat aplikaci?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger btn-sm">Smazat</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted small">Žádné OAuth aplikace.</p>
                    @endforelse

                    @if($oauthApps->count() < 10)
                        <hr>
                        <form method="POST" action="{{ route('panel.developer.oauth-apps.store') }}" class="mt-3">
                            @csrf
                            <div class="mb-2">
                                <input type="text" name="name" class="form-control form-control-sm @error('name') is-invalid @enderror"
                                       placeholder="Název aplikace" value="{{ old('name') }}" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-2">
                                <textarea name="redirect_uris" class="form-control form-control-sm" rows="2"
                                          placeholder="Redirect URI (jedna per řádek, nepovinné)">{{ old('redirect_uris') }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">Přidat aplikaci</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        {{-- API Quickstart --}}
        <div class="col-span-12">
            <div class="card card-no-border">
                <div class="card-header"><h5 class="mb-0">API – rychlý start</h5></div>
                <div class="card-body">
                    <div class="grid grid-cols-12 gap-3">
                        <div class="col-span-12 md:col-span-4">
                            <h6>Sanctum token (doporučeno)</h6>
                            <pre class="bg-light rounded p-2 small mb-0"><code>curl -H "Authorization: Bearer &lt;token&gt;" \
  https://{{ request()->getHost() }}/api/v1/me</code></pre>
                        </div>
                        <div class="col-span-12 md:col-span-4">
                            <h6>Endpoint přehled</h6>
                            <ul class="small list-unstyled mb-0">
                                <li><code>GET /api/v1/me</code> – profil zákazníka</li>
                                <li><code>GET /api/v1/sluzby</code> – seznam služeb</li>
                                <li><code>GET /api/v1/faktury</code> – faktury</li>
                                <li><code>GET /api/v1/tickety</code> – tickety</li>
                                <li><code>POST /api/v1/tickety</code> – nový ticket</li>
                            </ul>
                        </div>
                        <div class="col-span-12 md:col-span-4">
                            <h6>Dokumentace</h6>
                            <a href="{{ route('api.docs') }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                Otevřít OpenAPI docs
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
