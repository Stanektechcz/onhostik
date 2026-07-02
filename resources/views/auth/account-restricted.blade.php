@extends('layouts.cuba-standalone')
@section('title', 'Účet omezen')
@section('content')
<div class="login-card login-dark">
    <div>
        <div class="login-main">
            <div class="restricted-account text-center">
                <a href="{{ url('/') }}">
                    <img src="{{ asset('panel/images/logo/logo-onhost.svg') }}" alt="OnHost" class="for-light" style="max-width:140px;">
                    <img src="{{ asset('panel/images/logo/logo-onhost-white.svg') }}" alt="OnHost" class="for-dark" style="max-width:140px;">
                </a>
                <div class="my-4">
                    <i data-feather="lock" style="width:64px;height:64px;color:rgba(var(--theme-default),1);"></i>
                </div>
                <h2>Přístup omezen</h2>
                <p class="f-light f-14 mb-4">
                    Váš účet byl dočasně omezen. Kontaktujte naši podporu pro obnovení přístupu.
                </p>
                <a class="btn btn-primary text-white d-block mb-3" href="{{ url('/') }}">
                    Domovská stránka
                </a>
                <a class="btn btn-outline-secondary d-block" href="{{ url('/panel/podpora') }}">
                    <i data-feather="message-square" style="width:14px;height:14px;"></i>
                    Kontaktovat podporu
                </a>
                <p class="mt-4 f-light f-12">
                    Nebo nás kontaktujte na
                    <a href="mailto:info@onhost.cz">info@onhost.cz</a>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
