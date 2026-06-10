@extends('layouts.front')

@section('title', __('front.auth.register_title'))

@section('content')
    <section class="top-header sec-bg6 pb-150 bg-colorstyle">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="wrapper bg-seccolorstyle p-5 rounded mt-5" data-aos="fade-up">
                        <h1 class="heading mergecolor pb-4 f-26">{{ __('front.auth.register_title') }}</h1>

                        <form method="POST" action="{{ route('register') }}">
                            @csrf
                            <div class="general-input mb-3">
                                <label class="seccolor d-block pb-1" for="name">{{ __('front.auth.name') }}</label>
                                <input id="name" class="fill-input w-100" type="text" name="name"
                                       value="{{ old('name') }}" required autofocus autocomplete="name">
                                @error('name')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                            </div>

                            <div class="general-input mb-3">
                                <label class="seccolor d-block pb-1" for="email">{{ __('front.auth.email') }}</label>
                                <input id="email" class="fill-input w-100" type="email" name="email"
                                       value="{{ old('email') }}" required autocomplete="username">
                                @error('email')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                            </div>

                            <div class="general-input mb-3">
                                <label class="seccolor d-block pb-1" for="password">{{ __('front.auth.password') }}</label>
                                <input id="password" class="fill-input w-100" type="password" name="password"
                                       required autocomplete="new-password">
                                @error('password')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                            </div>

                            <div class="general-input mb-4">
                                <label class="seccolor d-block pb-1" for="password_confirmation">{{ __('front.auth.password_confirm') }}</label>
                                <input id="password_confirmation" class="fill-input w-100" type="password"
                                       name="password_confirmation" required autocomplete="new-password">
                            </div>

                            <button type="submit" class="btn btn-default-yellow-fill w-100">
                                {{ __('front.auth.register_button') }}
                            </button>
                        </form>

                        <p class="seccolor pt-4 mb-0">
                            {{ __('front.auth.have_account') }}
                            <a href="{{ route('login') }}" class="purple">{{ __('front.auth.login_button') }}</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
