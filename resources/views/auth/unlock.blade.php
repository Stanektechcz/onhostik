@extends('layouts.cuba-standalone')
@section('title', 'Odemknout účet')
@section('content')
<div class="authentication-main mt-0">
    <div class="container-fluid p-0">
        <div class="row m-0">
            <div class="col-12 p-0">
                <div class="login-card login-dark">
                    <div>
                        <div>
                            <a class="logo" href="{{ url('/') }}">
                                <img class="for-light" src="{{ asset('panel/images/logo/logo-onhost.svg') }}" alt="OnHost" style="max-width:140px;">
                                <img class="for-dark" src="{{ asset('panel/images/logo/logo-onhost-white.svg') }}" alt="OnHost" style="max-width:140px;">
                            </a>
                        </div>
                        <div class="login-main">
                            <div class="text-center mb-4">
                                <h4>Odemknout účet</h4>
                                <p class="f-light">Zadejte heslo pro odemčení relace</p>
                            </div>
                            <form class="theme-form" method="POST" action="{{ url('/login') }}">
                                @csrf
                                <div class="form-group">
                                    <label class="col-form-label">E-mail</label>
                                    <input class="form-control" type="email" name="email"
                                           value="{{ auth()->user()?->email ?? '' }}" readonly>
                                </div>
                                <div class="form-group">
                                    <label class="col-form-label">Heslo</label>
                                    <div class="form-input position-relative">
                                        <input class="form-control" type="password" name="password"
                                               placeholder="*********" required>
                                        <div class="show-hide">
                                            <span class="show"></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group mb-0">
                                    <div class="checkbox-primary p-0 checkbox">
                                        <input id="remember-unlock" type="checkbox" name="remember">
                                        <label for="remember-unlock">Zapamatovat si přihlášení</label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <button class="btn btn-primary btn-block w-full" type="submit">
                                        Odemknout
                                    </button>
                                </div>
                                <p class="mt-3 mb-0 text-center">
                                    <a class="link" href="{{ route('login') }}">Přihlásit se jiným účtem</a>
                                </p>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
