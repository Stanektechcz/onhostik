@extends('layouts.auth')

@section('title', __('front.auth.reset_title'))

@section('content')
    <h2 class="mergecolor mb-2"><b>{{ __('front.auth.reset_title') }}</b></h2>
    <p class="seccolor mb-5">Zadejte e-mail a zašleme vám odkaz pro nastavení nového hesla.</p>

    @if(session('status'))
        <div class="alert alert-success mb-4" role="alert">{{ session('status') }}</div>
    @endif

    <div class="cd-filter-block mb-0">
        <div class="cd-filter-content">
            <form method="POST" action="{{ route('password.email') }}">
                @csrf
                <div class="row">
                    <div class="col-md-8 position-relative mb-3">
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
                    <div class="col-md-12 mt-4 position-relative">
                        <button type="submit" class="btn btn-default-yellow-fill">
                            {{ __('front.auth.reset_send_link') }} <i class="fas fa-paper-plane ps-1 f-15"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <p class="seccolor pt-4 mb-0 f-14">
        Vzpomněli jste si na heslo?
        <a href="{{ route('login') }}" class="purple">{{ __('front.auth.login_button') }}</a>
    </p>
@endsection
