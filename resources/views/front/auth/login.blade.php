@extends('layouts.front')

@section('title', __('front.auth.login_title'))

@section('content')
    <section class="top-header sec-bg6 pb-150 bg-colorstyle">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="wrapper bg-seccolorstyle p-5 rounded mt-5" data-aos="fade-up">
                        <h1 class="heading mergecolor pb-4 f-26">{{ __('front.auth.login_title') }}</h1>

                        @if(session('status'))
                            <div class="alert alert-success" role="alert">{{ session('status') }}</div>
                        @endif

                        <form method="POST" action="{{ route('login') }}">
                            @csrf
                            <div class="general-input mb-3">
                                <label class="seccolor d-block pb-1" for="email">{{ __('front.auth.email') }}</label>
                                <input id="email" class="fill-input w-100" type="email" name="email"
                                       value="{{ old('email') }}" required autofocus autocomplete="username">
                                @error('email')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                            </div>

                            <div class="general-input mb-3">
                                <label class="seccolor d-block pb-1" for="password">{{ __('front.auth.password') }}</label>
                                <input id="password" class="fill-input w-100" type="password" name="password"
                                       required autocomplete="current-password">
                                @error('password')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <label class="seccolor mb-0">
                                    <input type="checkbox" name="remember"> {{ __('front.auth.remember_me') }}
                                </label>
                                <a href="{{ route('password.request') }}" class="purple">{{ __('front.auth.forgot_password') }}</a>
                            </div>

                            <button type="submit" class="btn btn-default-yellow-fill w-100">
                                {{ __('front.auth.login_button') }}
                            </button>
                        </form>

                        <p class="seccolor pt-4 mb-0">
                            {{ __('front.auth.no_account') }}
                            <a href="{{ route('register') }}" class="purple">{{ __('front.auth.register_button') }}</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
