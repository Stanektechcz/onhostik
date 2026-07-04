@extends('layouts.panel')

@php
    $breadcrumbTitle = 'SSH klíče';
    $breadcrumbItems = [__('panel.nav.account') => '#', 'SSH klíče' => ''];
@endphp

@section('title', 'SSH klíče')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="container">
        <div class="grid grid-cols-12 card-gap">

            {{-- ── SSH key list ─────────────────────────────────────── --}}
            <div class="col-span-8 xl:col-span-12">
                <div class="card">
                    <div class="card-header card-no-border">
                        <div class="header-top">
                            <h5>
                                <svg data-feather="key" style="width:18px;height:18px;vertical-align:-3px" class="me-1"></svg>
                                Vaše SSH klíče
                            </h5>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        @if ($keys->isEmpty())
                            <p class="text-muted">Zatím nemáte žádné SSH klíče.</p>
                        @else
                        <div class="table-responsive">
                            <table class="table table-borderless recent-table">
                                <thead>
                                    <tr>
                                        <th>Název</th>
                                        <th>Otisk klíče</th>
                                        <th>Přidáno</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach ($keys as $key)
                                <tr>
                                    <td>
                                        <span class="f-w-500">{{ $key->name }}</span>
                                    </td>
                                    <td>
                                        <code class="f-12 text-muted">{{ $key->fingerprint ?? '—' }}</code>
                                    </td>
                                    <td class="text-muted f-12">
                                        {{ $key->created_at->format('d.m.Y') }}
                                    </td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('panel.account.ssh-keys.destroy', $key) }}"
                                              onsubmit="return confirm('Opravdu smazat klíč «{{ $key->name }}»?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-xs">
                                                <svg data-feather="trash-2" style="width:12px;height:12px"></svg>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ── Add new key ──────────────────────────────────────── --}}
            <div class="col-span-4 xl:col-span-12">
                <div class="card">
                    <div class="card-header card-no-border">
                        <div class="header-top">
                            <h5>Přidat SSH klíč</h5>
                        </div>
                    </div>
                    <div class="card-body custom-input pt-0">
                        <form method="POST" action="{{ route('panel.account.ssh-keys.store') }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Název klíče</label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                       placeholder="např. MacBook Pro"
                                       value="{{ old('name') }}" maxlength="100" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Veřejný klíč (public key)</label>
                                <textarea name="public_key" rows="5"
                                          class="form-control font-monospace f-12 @error('public_key') is-invalid @enderror"
                                          placeholder="ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIB… comment"
                                          required>{{ old('public_key') }}</textarea>
                                @error('public_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">Podporované typy: ssh-rsa, ssh-ed25519, ecdsa-sha2-*</div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <svg data-feather="plus" style="width:14px;height:14px" class="me-1"></svg>
                                Přidat klíč
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
