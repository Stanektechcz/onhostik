@extends('layouts.cuba-standalone')
@section('title', 'Potvrdit heslo')

@section('content')
<div class="authentication-main mt-0">
    <div class="container-fluid p-0">
        <div class="row m-0 justify-content-center align-items-center min-vh-100">
            <div class="col-12 col-md-6 col-lg-4">
                <div class="login-card login-dark">
                    <div>
                        <div class="text-center mb-3">
                            <a href="{{ url('/') }}">
                                <img class="for-light" src="{{ asset('panel/images/logo/logo.png') }}"
                                     alt="OnHost" style="max-width:130px;">
                            </a>
                        </div>
                        <div class="login-main">
                            <div class="text-center mb-4">
                                <div style="width:56px;height:56px;border-radius:50%;background:rgba(var(--theme-default),.1);display:flex;align-items:center;justify-content:center;margin:0 auto 10px;">
                                    <i data-feather="lock" style="width:26px;height:26px;color:rgba(var(--theme-default),1);"></i>
                                </div>
                                <h4>Potvrdit heslo</h4>
                                <p class="f-light f-13">
                                    Pro pokračování zadejte své heslo. Toto je bezpečnostní opatření.
                                </p>
                            </div>

                            @if($errors->any())
                                <div class="alert alert-light-danger mb-3">
                                    @foreach($errors->all() as $error)
                                        <p class="mb-0">{{ $error }}</p>
                                    @endforeach
                                </div>
                            @endif

                            <form method="POST" action="{{ url('/user/confirm-password') }}" class="theme-form">
                                @csrf
                                <div class="form-group mb-3">
                                    <label class="col-form-label">Heslo</label>
                                    <div class="form-input position-relative">
                                        <input class="form-control" type="password" name="password"
                                               placeholder="Zadejte heslo" required autofocus>
                                        <div class="show-hide"><span class="show"></span></div>
                                    </div>
                                </div>
                                <div class="form-group mb-0">
                                    <button type="submit" class="btn btn-primary btn-block w-full">
                                        Potvrdit
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
