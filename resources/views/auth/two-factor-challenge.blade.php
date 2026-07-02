@extends('layouts.cuba-standalone')
@section('title', 'Dvoufázové ověřování')

@push('styles')
<style>
.authentication-main { min-height: 100vh; display: flex; align-items: center; justify-content: center; }
.login-card { width: 100%; max-width: 420px; margin: 0 auto; padding: 32px; }
</style>
@endpush

@section('content')
<div class="authentication-main">
    <div class="container-fluid p-0">
        <div class="row m-0 justify-content-center align-items-center min-vh-100">
            <div class="col-12 col-md-6 col-lg-4">
                <div class="login-card login-dark">
                    <div>
                        <div class="text-center mb-4">
                            <a href="{{ url('/') }}">
                                <img class="for-light" src="{{ asset('panel/images/logo/logo-onhost.svg') }}" alt="OnHost" style="max-width:140px;">
                                <img class="for-dark" src="{{ asset('panel/images/logo/logo-onhost-white.svg') }}" alt="OnHost" style="max-width:140px;">
                            </a>
                        </div>

                        <div class="login-main">
                            <div class="text-center mb-4">
                                <div style="width:60px;height:60px;border-radius:50%;background:rgba(var(--theme-default),.1);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                                    <i data-feather="shield" style="width:28px;height:28px;color:rgba(var(--theme-default),1);"></i>
                                </div>
                                <h4>Dvoufázové ověřování</h4>
                                <p class="f-light f-13">Zadejte kód z vaší autentifikační aplikace nebo záložní kód.</p>
                            </div>

                            @if(session('status'))
                                <div class="alert alert-light-success mb-3">{{ session('status') }}</div>
                            @endif

                            @if($errors->any())
                                <div class="alert alert-light-danger mb-3">
                                    @foreach($errors->all() as $error)
                                        <p class="mb-0">{{ $error }}</p>
                                    @endforeach
                                </div>
                            @endif

                            {{-- TOTP code form --}}
                            <form method="POST" action="{{ url('/user/two-factor-challenge') }}"
                                  id="totp-form" class="theme-form">
                                @csrf
                                <div class="form-group mb-3">
                                    <label class="col-form-label">Ověřovací kód (6 číslic)</label>
                                    <input class="form-control" type="text" name="code"
                                           inputmode="numeric" autocomplete="one-time-code"
                                           pattern="[0-9]{6}" maxlength="6"
                                           placeholder="000000" autofocus>
                                </div>
                                <div class="form-group mb-0">
                                    <button type="submit" class="btn btn-primary btn-block w-full">
                                        Ověřit kód
                                    </button>
                                </div>
                            </form>

                            <hr class="my-3">

                            {{-- Recovery code form --}}
                            <p class="f-light f-12 text-center mb-2">Nemáte přístup k aplikaci? Použijte záložní kód:</p>
                            <form method="POST" action="{{ url('/user/two-factor-challenge') }}"
                                  id="recovery-form" class="theme-form">
                                @csrf
                                <div class="form-group mb-3">
                                    <label class="col-form-label">Záložní kód</label>
                                    <input class="form-control" type="text" name="recovery_code"
                                           autocomplete="one-time-code"
                                           placeholder="xxxx-xxxx-xxxx">
                                </div>
                                <div class="form-group mb-0">
                                    <button type="submit" class="btn btn-outline-primary btn-block w-full">
                                        Použít záložní kód
                                    </button>
                                </div>
                            </form>

                            <p class="mt-3 text-center f-12">
                                <a href="{{ url('/login') }}" class="f-light">Zpět na přihlášení</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
