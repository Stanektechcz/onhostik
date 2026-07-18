@extends('layouts.panel')

@php
    $breadcrumbTitle = 'API tokeny';
    $breadcrumbItems = ['Účet' => '#', 'API tokeny' => ''];
@endphp

@section('title', 'API tokeny')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    @if(session('new_token'))
        <div class="alert alert-success border-0 mb-4" role="alert">
            <h6 class="alert-heading mb-2">
                <i data-feather="check-circle" style="width:16px;height:16px;"></i>
                Token vytvořen — zkopírujte ho nyní
            </h6>
            <div class="flex gap-2 items-center">
                <code class="grow p-2 rounded" style="background:rgba(0,0,0,.06);word-break:break-all;font-size:13px;">
                    {{ session('new_token') }}
                </code>
                <button class="btn btn-sm btn-outline-success" onclick="navigator.clipboard.writeText('{{ session('new_token') }}');this.textContent='Zkopírováno!'">
                    Kopírovat
                </button>
            </div>
            <div class="f-12 mt-1 text-muted">Token se zobrazí pouze jednou. Po zavření stránky ho nelze znovu zobrazit.</div>
        </div>
    @endif

    <div class="grid grid-cols-12 gap-4">
        <div class="col-span-12 md:col-span-8">
            <div class="card card-no-border">
                <div class="card-header">
                    <h5>Aktivní API tokeny</h5>
                    <span class="f-light f-12">{{ $tokens->count() }} / 5 tokenů</span>
                </div>
                <div class="card-body">
                    @if($tokens->isEmpty())
                        <p class="f-light f-12 mb-0">Nemáte žádné API tokeny. Vytvořte první níže.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Název</th>
                                        <th>Oprávnění</th>
                                        <th>Požadavky (7d)</th>
                                        <th>Celkem / Chyby</th>
                                        <th>Naposledy použit</th>
                                        <th>Vytvořen</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($tokens as $token)
                                        @php
                                            $stat     = $stats[$token->id]   ?? null;
                                            $rec      = $recent[$token->id]  ?? null;
                                            $total    = (int) ($stat?->total  ?? 0);
                                            $errCount = (int) ($stat?->errors ?? 0);
                                            $r7d      = (int) ($rec?->recent_total ?? 0);
                                        @endphp
                                        <tr>
                                            <td class="f-w-500">{{ $token->name }}</td>
                                            <td>
                                                @foreach($token->abilities as $ability)
                                                    <span class="badge {{ $ability === 'read' ? 'badge-light-secondary' : 'badge-light-primary' }} f-10">{{ $ability }}</span>
                                                @endforeach
                                            </td>
                                            <td class="f-12">
                                                <span class="{{ $r7d > 0 ? 'txt-primary f-w-500' : 'f-light' }}">{{ number_format($r7d) }}</span>
                                            </td>
                                            <td class="f-12">
                                                {{ number_format($total) }}
                                                @if($errCount > 0)
                                                    / <span class="txt-danger">{{ $errCount }} chyb</span>
                                                @endif
                                            </td>
                                            <td class="f-light f-12">
                                                {{ $token->last_used_at?->diffForHumans() ?? 'Nikdy' }}
                                            </td>
                                            <td class="f-light f-12">{{ $token->created_at->format('d.m.Y') }}</td>
                                            <td>
                                                <form method="POST"
                                                      action="{{ route('panel.account.api-tokens.destroy', $token->id) }}"
                                                      onsubmit="return confirm('Smazat token {{ $token->name }}?')">
                                                    @csrf @method('DELETE')
                                                    <button class="btn btn-outline-danger btn-xs">
                                                        <i data-feather="trash-2" style="width:11px;height:11px;"></i>
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

        <div class="col-span-12 md:col-span-4">
            <div class="card card-no-border">
                <div class="card-header">
                    <h5>Nový token</h5>
                </div>
                <div class="card-body">
                    @if($tokens->count() >= 5)
                        <div class="alert alert-warning f-12">Dosažen limit 5 tokenů. Nejprve smažte starý.</div>
                    @else
                        <form method="POST" action="{{ route('panel.account.api-tokens.store') }}">
                            @csrf
                            <div class="mb-3 custom-input">
                                <label class="form-label">Název tokenu</label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                       placeholder="např. Můj skript" maxlength="80" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Oprávnění</label>
                                @php
                                    $abilityLabels = [
                                        'read'           => ['label' => 'Číst data', 'desc' => 'Profil, služby, faktury, domény, monitoring'],
                                        'write:tickets'  => ['label' => 'Tickety (zápis)', 'desc' => 'Vytvořit, odpovědět, uzavřít ticket'],
                                        'write:credit'   => ['label' => 'Kredit (dobíjení)', 'desc' => 'Vytvořit fakturu pro dobití kreditu'],
                                        'write:orders'   => ['label' => 'Objednávky (zápis)', 'desc' => 'Vytvořit nové objednávky'],
                                        'manage:tokens'  => ['label' => 'Správa tokenů', 'desc' => 'Vytvářet a mazat API tokeny'],
                                    ];
                                @endphp
                                @foreach($abilityLabels as $key => $info)
                                    <div class="form-check mb-1">
                                        <input class="form-check-input" type="checkbox"
                                               name="abilities[]" value="{{ $key }}" id="ability_{{ $key }}"
                                               {{ $key === 'read' ? 'checked disabled' : '' }}>
                                        @if($key === 'read')
                                            <input type="hidden" name="abilities[]" value="read">
                                        @endif
                                        <label class="form-check-label f-12" for="ability_{{ $key }}">
                                            <span class="f-w-500">{{ $info['label'] }}</span>
                                            <span class="f-light block" style="font-size:11px;">{{ $info['desc'] }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            <button type="submit" class="btn btn-primary text-white w-full">
                                <i data-feather="hash" style="width:13px;height:13px;"></i> Vytvořit token
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="card card-no-border mt-3">
                <div class="card-body">
                    <h6 class="mb-2">Jak používat API</h6>
                    <p class="f-12 f-light mb-2">Přidejte token jako Bearer header:</p>
                    <code class="f-11 block p-2 rounded" style="background:rgba(0,0,0,.05);">
                        Authorization: Bearer &lt;váš-token&gt;
                    </code>
                    <div class="mt-3 f-12 f-light">
                        <strong>Dostupné endpointy:</strong><br>
                        <code>GET /api/v1/profile</code><br>
                        <code>GET /api/v1/services</code><br>
                        <code>GET /api/v1/services/{id}</code><br>
                        <code>GET /api/v1/invoices</code>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
