@extends('layouts.panel')

@php
    $isNew = $server === null;
    $breadcrumbTitle = $isNew ? 'Nový server' : 'Upravit: ' . $server->name;
    $breadcrumbItems = [__('panel.nav.admin_servers') => route('admin.servers.index'), $breadcrumbTitle => ''];
@endphp

@section('title', $breadcrumbTitle)

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <div class="row justify-content-center">
            <div class="col-span-8 xl:col-span-12">
                <x-panel.card :title="$breadcrumbTitle">
                    <form method="POST" action="{{ $isNew ? route('admin.servers.store') : route('admin.servers.update', $server) }}">
                        @csrf
                        @if(!$isNew) @method('PUT') @endif

                        <div class="grid grid-cols-12 card-gap">
                            <div class="col-span-6 md:col-span-12 mb-3">
                                <label class="form-label" for="s-name">Název serveru</label>
                                <input id="s-name" type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name', $server?->name) }}" required maxlength="100">
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-span-6 md:col-span-12 mb-3">
                                <label class="form-label" for="s-driver">Driver / typ</label>
                                <select id="s-driver" name="driver" class="form-select @error('driver') is-invalid @enderror" required>
                                    @foreach($drivers as $driver)
                                        <option value="{{ $driver->value }}" @selected(old('driver', $server?->driver?->value) === $driver->value)>
                                            {{ $driver->label() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('driver')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="s-url">API URL</label>
                            <input id="s-url" type="url" name="api_url" class="form-control @error('api_url') is-invalid @enderror"
                                   value="{{ old('api_url', $server?->api_url) }}" placeholder="https://panel.example.com:8006">
                            @error('api_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="grid grid-cols-12 card-gap">
                            <div class="col-span-4 md:col-span-6 sm:col-span-12 mb-3">
                                <label class="form-label" for="s-status">Status</label>
                                <select id="s-status" name="status" class="form-select" required>
                                    <option value="active" @selected(old('status', $server?->status ?? 'active') === 'active')>Aktivní</option>
                                    <option value="maintenance" @selected(old('status', $server?->status) === 'maintenance')>Údržba</option>
                                    <option value="offline" @selected(old('status', $server?->status) === 'offline')>Offline</option>
                                </select>
                            </div>
                            <div class="col-span-4 md:col-span-6 sm:col-span-12 mb-3">
                                <label class="form-label" for="s-max">Max. služeb</label>
                                <input id="s-max" type="number" name="max_services" class="form-control"
                                       value="{{ old('max_services', $server?->max_services) }}" min="1" placeholder="∞">
                                <div class="form-text">Prázdné = bez limitu</div>
                            </div>
                            <div class="col-span-4 md:col-span-12 mb-3 d-flex align-items-end gap-3 pb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="s-mock" name="mock_mode" value="1"
                                           @checked(old('mock_mode', $server?->mock_mode ?? true))>
                                    <label class="form-check-label" for="s-mock">Mock mode</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="s-default" name="is_default" value="1"
                                           @checked(old('is_default', $server?->is_default ?? false))>
                                    <label class="form-check-label" for="s-default">Výchozí</label>
                                </div>
                            </div>
                        </div>

                        <hr>
                        <p class="f-w-600 mb-3">API přihlašovací údaje <span class="f-light f-12">(šifrováno AES-256 — prázdné pole = ponechat stávající)</span></p>

                        <div class="grid grid-cols-12 card-gap">
                            <div class="col-span-6 md:col-span-12 mb-3">
                                <label class="form-label" for="cred-user">Uživatel / realm</label>
                                <input id="cred-user" type="text" name="cred_api_user" class="form-control"
                                       value="{{ old('cred_api_user') }}" placeholder="{{ $server ? '(ponechat stávající)' : 'root@pam' }}" autocomplete="off">
                            </div>
                            <div class="col-span-6 md:col-span-12 mb-3">
                                <label class="form-label" for="cred-pass">Heslo / API key</label>
                                <input id="cred-pass" type="password" name="cred_api_password" class="form-control"
                                       value="{{ old('cred_api_password') }}" placeholder="{{ $server ? '(ponechat stávající)' : '' }}" autocomplete="new-password">
                            </div>
                            <div class="col-span-6 md:col-span-12 mb-3">
                                <label class="form-label" for="cred-token-id">Token ID</label>
                                <input id="cred-token-id" type="text" name="cred_api_token_id" class="form-control"
                                       value="{{ old('cred_api_token_id') }}" placeholder="{{ $server ? '(ponechat stávající)' : 'mytoken' }}" autocomplete="off">
                            </div>
                            <div class="col-span-6 md:col-span-12 mb-3">
                                <label class="form-label" for="cred-token">Token secret / API token</label>
                                <input id="cred-token" type="password" name="cred_api_token" class="form-control"
                                       value="{{ old('cred_api_token') }}" placeholder="{{ $server ? '(ponechat stávající)' : 'uuid-...' }}" autocomplete="new-password">
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-2">
                            <button type="submit" class="btn btn-primary">{{ __('panel.admin.save') }}</button>
                            <a href="{{ route('admin.servers.index') }}" class="btn btn-outline-secondary">{{ __('panel.common.back') }}</a>
                        </div>
                    </form>
                </x-panel.card>
            </div>
        </div>
    </div>
@endsection
