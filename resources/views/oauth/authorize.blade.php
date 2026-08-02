@extends('layouts.panel')

@section('title', 'Autorizace aplikace')

@php
    $breadcrumbTitle = 'Autorizace aplikace';
    $breadcrumbItems = ['Autorizace' => ''];
    $scopeString = implode(' ', $scopes);
@endphp

@section('content')
<div class="container-fluid">
    <div class="grid grid-cols-12">
        <div class="col-span-12 md:col-span-8 lg:col-span-6 md:col-start-3 lg:col-start-4">
            <div class="card">
                <div class="card-header card-no-border">
                    <div class="header-top">
                        <h5>Povolit přístup aplikaci</h5>
                        <p class="f-m-light mt-1">
                            Aplikace <strong>{{ $app->name }}</strong> žádá o přístup k vašemu účtu
                            <strong>{{ auth()->user()->email }}</strong>.
                        </p>
                    </div>
                </div>

                <div class="card-body">
                    <p class="f-w-500 mb-2">Aplikace bude moci:</p>
                    <ul class="mb-4 ps-3">
                        @foreach ($scopes as $scope)
                            <li class="mb-1">
                                <i data-feather="check" class="text-success me-1" style="width:14px;height:14px;"></i>
                                {{ $scopeLabels[$scope] ?? $scope }}
                            </li>
                        @endforeach
                    </ul>

                    <div class="alert alert-light-secondary" role="alert">
                        <i data-feather="corner-up-right" class="me-1" style="width:14px;height:14px;"></i>
                        Po potvrzení vás přesměrujeme na
                        <span class="f-w-500">{{ parse_url($redirectUri, PHP_URL_HOST) ?: $redirectUri }}</span>.
                    </div>

                    <div class="flex gap-2 items-center mt-3">
                        <form method="POST" action="{{ route('oauth.authorize.approve') }}">
                            @csrf
                            <input type="hidden" name="client_id" value="{{ $app->client_id }}">
                            <input type="hidden" name="redirect_uri" value="{{ $redirectUri }}">
                            <input type="hidden" name="scope" value="{{ $scopeString }}">
                            <input type="hidden" name="state" value="{{ $state }}">
                            <input type="hidden" name="code_challenge" value="{{ $codeChallenge }}">
                            <input type="hidden" name="code_challenge_method" value="{{ $codeChallengeMethod }}">
                            <button type="submit" class="btn btn-primary text-white">
                                <i data-feather="check" class="me-1" style="width:14px;height:14px;"></i>
                                Povolit přístup
                            </button>
                        </form>

                        <form method="POST" action="{{ route('oauth.authorize.deny') }}">
                            @csrf
                            <input type="hidden" name="client_id" value="{{ $app->client_id }}">
                            <input type="hidden" name="redirect_uri" value="{{ $redirectUri }}">
                            <input type="hidden" name="state" value="{{ $state }}">
                            <button type="submit" class="btn btn-light">Zamítnout</button>
                        </form>
                    </div>

                    <p class="f-12 f-light mt-3 mb-0">
                        Přístup můžete kdykoli odvolat smazáním tokenu v sekci
                        <a href="{{ route('panel.developer.index') }}">Vývojář</a>.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
