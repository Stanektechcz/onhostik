@extends('layouts.auth')

@section('title', __('front.auth.register_title'))

@section('content')
    <h2 class="mergecolor mb-2"><b>{{ __('front.auth.register_title') }}</b></h2>
    <p class="seccolor mb-5">Vytvořte si účet a objednejte hosting během 2 minut.</p>

    <div class="cd-filter-block mb-0">
        <div class="cd-filter-content">
            <form method="POST" action="{{ route('register') }}">
                @csrf
                <div class="row">
                    <div class="col-md-12 position-relative mb-3">
                        <div class="general-input">
                            <label class="seccolor d-block pb-1" for="name">
                                <i class="fas fa-user me-1"></i>{{ __('front.auth.name') }}
                            </label>
                            <input id="name" class="fill-input w-100" type="text" name="name"
                                   value="{{ old('name') }}" required autofocus autocomplete="name"
                                   placeholder="Jan Novák">
                            @error('name')<div class="text-danger mt-1 f-13">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-12 position-relative mb-3">
                        <div class="general-input">
                            <label class="seccolor d-block pb-1" for="email">
                                <i class="fas fa-envelope me-1"></i>{{ __('front.auth.email') }}
                            </label>
                            <input id="email" class="fill-input w-100" type="email" name="email"
                                   value="{{ old('email') }}" required autocomplete="username"
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
                                   required autocomplete="new-password" placeholder="min. 8 znaků">
                            @error('password')<div class="text-danger mt-1 f-13">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-6 position-relative mb-3">
                        <div class="general-input">
                            <label class="seccolor d-block pb-1" for="password_confirmation">
                                <i class="fas fa-lock me-1"></i>{{ __('front.auth.password_confirm') }}
                            </label>
                            <input id="password_confirmation" class="fill-input w-100" type="password"
                                   name="password_confirmation" required autocomplete="new-password"
                                   placeholder="zopakujte heslo">
                        </div>
                    </div>
                    <div class="col-md-12 mt-4 position-relative">
                        <button type="submit" class="btn btn-default-yellow-fill">
                            {{ __('front.auth.register_button') }} <i class="fas fa-user-plus ps-1 f-15"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <p class="seccolor pt-4 mb-0 f-14">
        {{ __('front.auth.have_account') }}
        <a href="{{ route('login') }}" class="purple">{{ __('front.auth.login_button') }}</a>
    </p>
@endsection
