@extends('layouts.panel')

@php
    $isEdit = isset($user) && $user->exists;
    $breadcrumbTitle = $isEdit ? 'Upravit uživatele' : 'Přidat uživatele';
    $breadcrumbItems = ['Uživatelé' => route('admin.users.index'), $breadcrumbTitle => ''];
@endphp

@section('title', $breadcrumbTitle)

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="container edit-profile">
        <div class="grid grid-cols-12 card-gap">

            {{-- Left: profile summary --}}
            <div class="col-span-4 xl:col-span-12">
                <div class="card">
                    <div class="card-header card-no-border">
                        <div class="header-top"><h5>Profil uživatele</h5></div>
                    </div>
                    <div class="card-body text-center">
                        <div style="width:90px;height:90px;border-radius:50%;background:linear-gradient(135deg,rgba(var(--theme-default),.2),rgba(var(--theme-default),.05));display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                            <span class="f-w-700 f-24" style="color:rgba(var(--theme-default),1);">
                                {{ strtoupper(substr($user->name ?? 'N', 0, 2)) }}
                            </span>
                        </div>
                        <h5 class="mb-1">{{ $user->name ?? 'Nový uživatel' }}</h5>
                        <p class="f-light f-12">{{ $user->email ?? '' }}</p>
                        @foreach($user->getRoleNames() ?? [] as $r)
                            <span class="badge badge-light-primary me-1">{{ $r }}</span>
                        @endforeach
                        @if(isset($user) && $user->exists)
                        <hr>
                        <p class="f-light f-12">Registrován: {{ $user->created_at?->format('d.m.Y') }}</p>
                        <p class="f-light f-12">Poslední přihlášení: {{ $user->last_login_at?->format('d.m.Y H:i') ?? '—' }}</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Right: edit form --}}
            <div class="col-span-8 xl:col-span-12">
                <div class="card">
                    <div class="card-header card-no-border">
                        <div class="header-top">
                            <h5>{{ $isEdit ? 'Upravit profil' : 'Nový uživatel' }}</h5>
                        </div>
                    </div>
                    <div class="card-body custom-input">
                        <form method="POST"
                              action="{{ $isEdit ? route('admin.users.update', $user) : route('admin.users.store') }}">
                            @csrf
                            @if($isEdit) @method('PUT') @endif

                            <div class="grid grid-cols-12 gap-3">
                                <div class="col-span-6 sm:col-span-12">
                                    <label class="form-label">Jméno *</label>
                                    <input class="form-control @error('name') is-invalid @enderror"
                                           type="text" name="name"
                                           value="{{ old('name', $user->name ?? '') }}" required>
                                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-span-6 sm:col-span-12">
                                    <label class="form-label">E-mail *</label>
                                    <input class="form-control @error('email') is-invalid @enderror"
                                           type="email" name="email"
                                           value="{{ old('email', $user->email ?? '') }}" required>
                                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-span-6 sm:col-span-12">
                                    <label class="form-label">{{ $isEdit ? 'Nové heslo' : 'Heslo *' }}</label>
                                    <input class="form-control @error('password') is-invalid @enderror"
                                           type="password" name="password"
                                           {{ !$isEdit ? 'required' : '' }}
                                           placeholder="{{ $isEdit ? 'Ponechte prázdné pro beze změny' : '' }}">
                                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-span-6 sm:col-span-12">
                                    <label class="form-label">Potvrdit heslo</label>
                                    <input class="form-control" type="password" name="password_confirmation">
                                </div>
                                <div class="col-span-6 sm:col-span-12">
                                    <label class="form-label">Role</label>
                                    <select class="form-select" name="role">
                                        <option value="">Bez role (zákazník)</option>
                                        @foreach($roles ?? [] as $role)
                                            <option value="{{ $role->name }}"
                                                    @selected($user->hasRole($role->name) ?? false)>
                                                {{ $role->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-span-6 sm:col-span-12">
                                    <label class="form-label">Jazyk</label>
                                    <select class="form-select" name="locale">
                                        <option value="cs" @selected(($user->locale ?? 'cs') === 'cs')>Čeština</option>
                                        <option value="en" @selected(($user->locale ?? '') === 'en')>English</option>
                                    </select>
                                </div>
                                <div class="col-span-12">
                                    <div class="form-check checkbox-primary">
                                        <input class="form-check-input" type="checkbox" id="is_active"
                                               name="is_active" value="1"
                                               {{ ($user->is_active ?? true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_active">Aktivní účet</label>
                                    </div>
                                </div>
                                <div class="col-span-12 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary text-white">
                                        {{ $isEdit ? 'Uložit změny' : 'Vytvořit uživatele' }}
                                    </button>
                                    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">
                                        Zrušit
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
