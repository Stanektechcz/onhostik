@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Přihláška do partnerského programu';
    $iconSprite      = asset('panel/svg/icon-sprite.svg');
@endphp

@section('title', 'Přihláška do partnerského programu')

@section('content')
<div class="container-fluid py-4">
    <div class="grid grid-cols-12 justify-center">
        <div class="col-span-12 lg:col-span-7">

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Partnerský program OnHost</h5>
                </div>
                <div class="card-body">
                    <div class="grid grid-cols-12 gap-3 mb-4">
                        <div class="col-span-4 text-center">
                            <div class="h4 text-dark font-bold mb-0">Bronze</div>
                            <div class="text-muted small">5 % provize</div>
                            <div class="text-muted" style="font-size:11px">start</div>
                        </div>
                        <div class="col-span-4 text-center">
                            <div class="h4 text-secondary font-bold mb-0">Silver</div>
                            <div class="text-muted small">8 % provize</div>
                            <div class="text-muted" style="font-size:11px">od 100 000 Kč celkových výplat</div>
                        </div>
                        <div class="col-span-4 text-center">
                            <div class="h4 text-warning font-bold mb-0">Gold</div>
                            <div class="text-muted small">12 % provize</div>
                            <div class="text-muted" style="font-size:11px">od 500 000 Kč celkových výplat</div>
                        </div>
                    </div>
                    <ul class="list-unstyled small text-muted mb-0">
                        <li class="mb-1">✓ Provize z každé úspěšně zprostředkované objednávky</li>
                        <li class="mb-1">✓ Přístup k marketingovým materiálům a bannerům</li>
                        <li class="mb-1">✓ Výplata na bankovní účet nebo IBAN</li>
                        <li>✓ Automatický upgrade tier při dosažení milníků</li>
                    </ul>
                </div>
            </div>

            @if($errors->any())
                <div class="alert alert-danger mb-3">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Přihláška — vyplňte prosím</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('partner.apply.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label font-semibold">Vaše jméno</label>
                            <input type="text" class="form-control" value="{{ $user->name }}" disabled>
                        </div>

                        <div class="mb-3">
                            <label class="form-label font-semibold">Email</label>
                            <input type="text" class="form-control" value="{{ $user->email }}" disabled>
                        </div>

                        <div class="mb-3">
                            <label for="payout_method" class="form-label font-semibold">Způsob výplaty</label>
                            <select name="payout_method" id="payout_method" class="form-select @error('payout_method') is-invalid @enderror" required>
                                <option value="">— vyberte —</option>
                                <option value="bank_czk" {{ old('payout_method') === 'bank_czk' ? 'selected' : '' }}>Bankovní účet CZK</option>
                                <option value="bank_eur" {{ old('payout_method') === 'bank_eur' ? 'selected' : '' }}>Bankovní účet EUR (IBAN)</option>
                                <option value="paypal" {{ old('payout_method') === 'paypal' ? 'selected' : '' }}>PayPal</option>
                                <option value="crypto" {{ old('payout_method') === 'crypto' ? 'selected' : '' }}>Kryptoměna (USDT/BTC)</option>
                            </select>
                            @error('payout_method') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="payout_info" class="form-label font-semibold">Číslo účtu / IBAN / PayPal / adresa peněženky</label>
                            <textarea name="payout_info" id="payout_info" rows="2"
                                      class="form-control @error('payout_info') is-invalid @enderror"
                                      placeholder="Zadejte detaily pro výplatu" required>{{ old('payout_info') }}</textarea>
                            @error('payout_info') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-4 form-check">
                            <input type="checkbox" name="agree_terms" id="agree_terms" value="1"
                                   class="form-check-input @error('agree_terms') is-invalid @enderror"
                                   {{ old('agree_terms') ? 'checked' : '' }}>
                            <label class="form-check-label" for="agree_terms">
                                Souhlasím s <a href="#" class="txt-primary">podmínkami partnerského programu</a>
                            </label>
                            @error('agree_terms') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="flex gap-2">
                            <button type="submit" class="btn btn-primary">Odeslat přihlášku</button>
                            <a href="{{ route('panel.dashboard') }}" class="btn btn-outline-secondary">Zrušit</a>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
