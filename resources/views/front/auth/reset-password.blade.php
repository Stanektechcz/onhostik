@extends('layouts.front')

@section('title', __('front.auth.reset_title'))

@section('content')
    <section class="top-header sec-bg6 pb-150 bg-colorstyle">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="wrapper bg-seccolorstyle p-5 rounded mt-5" data-aos="fade-up">
                        <h1 class="heading mergecolor pb-4 f-26">{{ __('front.auth.reset_title') }}</h1>

                        <form method="POST" action="{{ route('password.update') }}">
                            @csrf
                            <input type="hidden" name="token" value="{{ $request->route('token') }}">

                            <div class="general-input mb-3">
                                <label class="seccolor d-block pb-1" for="email">{{ __('front.auth.email') }}</label>
                                <input id="email" class="fill-input w-100" type="email" name="email"
                                       value="{{ old('email', $request->email) }}" required autocomplete="username">
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
                                {{ __('front.auth.reset_button') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
