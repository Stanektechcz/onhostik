@extends('layouts.panel')

@php
    $isNew = $partner === null;
    $breadcrumbTitle = $isNew ? 'Nový partner' : 'Upravit: ' . $partner->user?->name;
    $breadcrumbItems = ['Partneři' => route('admin.partners.index'), $breadcrumbTitle => ''];
@endphp

@section('title', $breadcrumbTitle)

@section('content')
<div class="container-fluid">
    <x-panel.flash />
    @if($errors->any())
        <div class="alert alert-light-danger mb-3">
            @foreach($errors->all() as $err)<p class="mb-0 f-12">{{ $err }}</p>@endforeach
        </div>
    @endif

    <div class="grid grid-cols-12 card-gap">
        <div class="col-span-12 xl:col-span-8">
            <div class="card">
                <div class="card-header card-no-border pb-0">
                    <div class="header-top">
                        <h5>{{ $isNew ? 'Vytvořit partner profil' : 'Upravit partner profil' }}</h5>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST"
                          action="{{ $isNew ? route('admin.partners.store') : route('admin.partners.update', $partner) }}">
                        @csrf
                        @if(!$isNew) @method('PUT') @endif

                        {{-- User select (only for new) --}}
                        @if($isNew)
                            <div class="mb-3">
                                <label class="form-label f-12 f-light" for="user_id">Uživatel <span class="txt-danger">*</span></label>
                                <select id="user_id" name="user_id" class="form-select" required>
                                    <option value="">— Vyberte uživatele —</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}" @selected(old('user_id') == $user->id)>
                                            {{ $user->name }} ({{ $user->email }})
                                        </option>
                                    @endforeach
                                </select>
                                <small class="f-light f-12">Zobrazují se pouze uživatelé bez existujícího partner profilu.</small>
                                @error('user_id')<div class="text-danger f-12 mt-1">{{ $message }}</div>@enderror
                            </div>
                        @else
                            <div class="mb-3">
                                <label class="form-label f-12 f-light">Uživatel</label>
                                <input type="text" class="form-control" value="{{ $partner->user?->name }} ({{ $partner->user?->email }})" disabled>
                            </div>
                        @endif

                        {{-- Referral code --}}
                        <div class="mb-3">
                            <label class="form-label f-12 f-light" for="referral_code">Referral kód <span class="txt-danger">*</span></label>
                            <div class="input-group">
                                <input id="referral_code" type="text" name="referral_code" class="form-control text-uppercase"
                                       value="{{ old('referral_code', $partner?->referral_code) }}"
                                       pattern="[A-Z0-9]{4,16}" maxlength="16" required
                                       placeholder="např. PARTNER01">
                                @if($isNew)
                                    <button type="button" class="btn btn-outline-secondary"
                                            onclick="generateCode()">
                                        <i data-feather="refresh-cw" style="width:13px;height:13px;"></i>
                                        Generovat
                                    </button>
                                @endif
                            </div>
                            <small class="f-light f-12">4–16 znaků, pouze velká písmena a číslice (A-Z, 0-9). Musí být unikátní.</small>
                            @error('referral_code')<div class="text-danger f-12 mt-1">{{ $message }}</div>@enderror
                        </div>

                        {{-- Status --}}
                        <div class="mb-3">
                            <label class="form-label f-12 f-light" for="status">Stav <span class="txt-danger">*</span></label>
                            <select id="status" name="status" class="form-select" required>
                                @foreach(\App\Domains\Partner\Enums\PartnerStatus::cases() as $s)
                                    <option value="{{ $s->value }}" @selected(old('status', $partner?->status->value ?? 'active') === $s->value)>
                                        {{ $s->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('status')<div class="text-danger f-12 mt-1">{{ $message }}</div>@enderror
                        </div>

                        {{-- Commission rate --}}
                        <div class="mb-3">
                            <label class="form-label f-12 f-light" for="commission_rate">Sazba provize (%) <span class="txt-danger">*</span></label>
                            <div class="input-group">
                                <input id="commission_rate" type="number" name="commission_rate_percent"
                                       class="form-control" style="max-width:120px;"
                                       min="0" max="100" step="0.1"
                                       value="{{ old('commission_rate_percent', $defaultRate) }}" required>
                                <span class="input-group-text">%</span>
                            </div>
                            <small class="f-light f-12">Procento z čisté hodnoty (subtotal bez DPH) každé zaplacené objednávky.</small>
                            @error('commission_rate_percent')<div class="text-danger f-12 mt-1">{{ $message }}</div>@enderror
                        </div>

                        {{-- Payout method --}}
                        <div class="mb-3">
                            <label class="form-label f-12 f-light" for="payout_method">Metoda výplaty</label>
                            <input id="payout_method" type="text" name="payout_method" class="form-control"
                                   value="{{ old('payout_method', $partner?->payout_method) }}"
                                   placeholder="např. Bankovní převod">
                            @error('payout_method')<div class="text-danger f-12 mt-1">{{ $message }}</div>@enderror
                        </div>

                        {{-- Payout details (encrypted) --}}
                        <div class="mb-4">
                            <label class="form-label f-12 f-light" for="payout_details">
                                Výplatní údaje
                                <span class="badge badge-light-info ms-1">Šifrováno</span>
                            </label>
                            <textarea id="payout_details" name="payout_details" class="form-control" rows="3"
                                      placeholder="Číslo účtu, IBAN, nebo jiné platební údaje…">{{ old('payout_details') }}</textarea>
                            @if(!$isNew && $partner?->payout_details_encrypted)
                                <small class="f-light f-12">
                                    <i data-feather="lock" style="width:11px;height:11px;"></i>
                                    Výplatní údaje jsou uloženy (šifrované). Vyplňte nové pro přepsání, jinak ponechte prázdné.
                                </small>
                            @else
                                <small class="f-light f-12">Uloženo šifrovaně pomocí AES-256. Viditelné pouze adminem.</small>
                            @endif
                            @error('payout_details')<div class="text-danger f-12 mt-1">{{ $message }}</div>@enderror
                        </div>

                        <div class="d-flex gap-3">
                            <button type="submit" class="btn btn-primary">
                                {{ $isNew ? 'Vytvořit profil' : 'Uložit změny' }}
                            </button>
                            <a href="{{ $isNew ? route('admin.partners.index') : route('admin.partners.show', $partner) }}"
                               class="btn btn-outline-secondary">Zrušit</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Info sidebar --}}
        <div class="col-span-12 xl:col-span-4">
            <div class="card">
                <div class="card-header card-no-border pb-0">
                    <div class="header-top"><h5>Nápověda</h5></div>
                </div>
                <div class="card-body">
                    <ul class="f-light f-12 ps-3 mb-0">
                        <li class="mb-2">Po vytvoření profilu obdrží uživatel oprávnění <code>access-partner</code> automaticky.</li>
                        <li class="mb-2">Referral kód musí být globálně unikátní. Použijte tlačítko "Generovat" pro náhodný kód.</li>
                        <li class="mb-2">Sazba provize platí pro všechny budoucí objednávky tohoto partnera. Existující provize se nemění.</li>
                        <li class="mb-2">Výplatní údaje jsou šifrované — nejsou viditelné v logu ani backupu.</li>
                        <li>Stav <strong>Paused</strong> zastaví nové referraly. Stav <strong>Banned</strong> blokuje vše.</li>
                    </ul>
                </div>
            </div>

            @if(!$isNew && $partner)
            <div class="card mt-3">
                <div class="card-header card-no-border pb-0">
                    <div class="header-top"><h5>Referral odkaz</h5></div>
                </div>
                <div class="card-body">
                    <div class="input-group">
                        <input type="text" id="ref-url-preview" class="form-control f-12"
                               value="{{ url('/') }}?ref={{ $partner->referral_code }}" readonly>
                        <button class="btn btn-outline-primary" type="button"
                                onclick="navigator.clipboard.writeText(document.getElementById('ref-url-preview').value)">
                            <i data-feather="copy" style="width:13px;height:13px;"></i>
                        </button>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

@if($isNew)
<script>
async function generateCode() {
    try {
        const r = await fetch('{{ route('admin.partners.generate-code') }}');
        const data = await r.json();
        document.getElementById('referral_code').value = data.code;
    } catch(e) {
        console.error(e);
    }
}
</script>
@endif
@endsection
