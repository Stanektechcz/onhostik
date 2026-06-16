@extends('layouts.auth')

@section('title', __('front.auth.login_title'))

@section('content')
    <h2 class="mergecolor mb-2"><b>{{ __('front.auth.login_title') }}</b></h2>
    <p class="seccolor mb-5">Vítejte zpět — přihlaste se do klientské zóny.</p>

    @if(session('status'))
        <div class="alert alert-success mb-4" role="alert">{{ session('status') }}</div>
    @endif

    <div class="cd-filter-block mb-0">
        <div class="cd-filter-content">
            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="row">
                    <div class="col-md-6 position-relative mb-3">
                        <div class="general-input">
                            <label class="seccolor d-block pb-1" for="email">
                                <i class="fas fa-envelope me-1"></i>{{ __('front.auth.email') }}
                            </label>
                            <input id="email" class="fill-input w-100" type="email" name="email"
                                   value="{{ old('email') }}" required autofocus autocomplete="username"
                                   placeholder="vas@email.cz">
                            @error('email')<div class="text-danger mt-1 f-13">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-6 position-relative mb-3">
                        <div class="general-input">
                            <label class="seccolor d-block pb-1" for="password">
                                <i class="fas fa-lock me-1"></i>{{ __('front.auth.password') }}
                            </label>
                            <input id="password" class="fill-input w-100" type="password" name="password"
                                   required autocomplete="current-password" placeholder="••••••••">
                            @error('password')<div class="text-danger mt-1 f-13">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-12 mt-4 position-relative">
                        <button type="submit" class="btn btn-default-yellow-fill me-3">
                            {{ __('front.auth.login_button') }} <i class="fas fa-lock ps-1 f-15"></i>
                        </button>
                        <a href="{{ route('password.request') }}" class="golink me-3 position-relative seccolor">
                            {{ __('front.auth.forgot_password') }}
                        </a>
                        <ul class="list d-inline">
                            <li>
                                <input name="remember" type="checkbox" id="remember" class="filter">
                                <label for="remember" class="checkbox-label c-grey seccolor">{{ __('front.auth.remember_me') }}</label>
                            </li>
                        </ul>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <p class="seccolor pt-4 mb-0 f-14">
        {{ __('front.auth.no_account') }}
        <a href="{{ route('register') }}" class="purple">{{ __('front.auth.register_button') }}</a>
    </p>
@endsection
