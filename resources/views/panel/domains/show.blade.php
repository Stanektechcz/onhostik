@extends('layouts.panel')

@php
    $breadcrumbTitle = $domain->fqdn();
    $breadcrumbItems = [__('panel.nav.domains') => route('panel.domains.index'), $domain->fqdn() => ''];
@endphp

@section('title', $domain->fqdn())

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        @php
            $expiresDays = $domain->expires_at ? now()->diffInDays($domain->expires_at, false) : null;
            $expiryClass = '';
            if ($expiresDays !== null) {
                if ($expiresDays < 0) $expiryClass = 'text-danger f-w-600';
                elseif ($expiresDays <= 30) $expiryClass = 'text-warning f-w-600';
            }
        @endphp

        <div class="grid grid-cols-12 card-gap">
            <div class="col-span-8 xl:col-span-12">
                <x-panel.card :title="$domain->fqdn()">
                    <div class="grid grid-cols-12 gap-3 mb-4">
                        <div class="col-span-3 sm:col-span-6">
                            <p class="f-light f-12 mb-1">{{ __('panel.common.status') }}</p>
                            @if($domain->wedos_domain_id !== null)
                                <span class="badge badge-light-success">{{ __('panel.domains.registered') }}</span>
                            @else
                                <span class="badge badge-light-warning">{{ __('panel.domains.pending') }}</span>
                            @endif
                        </div>
                        <div class="col-span-3 sm:col-span-6">
                            <p class="f-light f-12 mb-1">{{ __('panel.domains.registrar') }}</p>
                            <p class="mb-0 f-w-500 uppercase">{{ $domain->registrar ?? '—' }}</p>
                        </div>
                        <div class="col-span-3 sm:col-span-6">
                            <p class="f-light f-12 mb-1">{{ __('panel.domains.registered_at') }}</p>
                            <p class="mb-0">{{ $domain->registered_at?->format('d.m.Y') ?? '—' }}</p>
                        </div>
                        <div class="col-span-3 sm:col-span-6">
                            <p class="f-light f-12 mb-1">{{ __('panel.domains.expires_at') }}</p>
                            <p class="mb-0 {{ $expiryClass }}">
                                {{ $domain->expires_at?->format('d.m.Y') ?? '—' }}
                                @if($expiresDays !== null && $expiresDays >= 0 && $expiresDays <= 30)
                                    <br><span class="f-11 f-light">({{ $expiresDays }}d)</span>
                                @elseif($expiresDays !== null && $expiresDays < 0)
                                    <br><span class="f-11">{{ __('panel.domains.expired') }}</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-12 gap-3 mb-4">
                        <div class="col-span-4 sm:col-span-12">
                            <p class="f-light f-12 mb-1">{{ __('panel.domains.auto_renew') }}</p>
                            @if($domain->auto_renew)
                                <span class="badge badge-light-success">ON</span>
                            @else
                                <span class="badge badge-light-secondary">OFF</span>
                            @endif
                        </div>
                        <div class="col-span-4 sm:col-span-12">
                            <p class="f-light f-12 mb-1">{{ __('panel.domains.service') }}</p>
                            <p class="mb-0">
                                @if($domain->service)
                                    <a href="{{ route('panel.services.show', $domain->service) }}" class="f-w-500">
                                        {{ $domain->service->label }}
                                    </a>
                                @else
                                    <span class="f-light">—</span>
                                @endif
                            </p>
                        </div>
                        @if($domain->wedos_domain_id)
                            <div class="col-span-4 sm:col-span-12">
                                <p class="f-light f-12 mb-1">WEDOS ID</p>
                                <p class="mb-0 f-12 font-monospace">{{ $domain->wedos_domain_id }}</p>
                            </div>
                        @endif
                    </div>

                    {{-- Nameservers --}}
                    <div class="border-top pt-3 mt-1">
                        <div class="flex items-center justify-between mb-2">
                            <p class="f-light f-12 mb-0">{{ __('panel.domains.nameservers') }}</p>
                            <button type="button" class="btn btn-xs btn-outline-secondary"
                                    data-bs-toggle="collapse" data-bs-target="#ns-edit-form">
                                <i data-feather="edit-2" style="width:11px;height:11px"></i>
                                {{ __('panel.domains.nameservers_update') }}
                            </button>
                        </div>

                        @if(!empty($domain->nameservers))
                            <ul class="list-unstyled mb-2">
                                @foreach($domain->nameservers as $ns)
                                    <li class="mb-1 flex items-center gap-2">
                                        <i data-feather="server" class="font-secondary" style="width:12px;height:12px;flex-shrink:0"></i>
                                        <span class="f-13 font-monospace">{{ $ns }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="f-light f-12 mb-2">—</p>
                        @endif

                        <div class="collapse" id="ns-edit-form">
                            <form method="POST" action="{{ route('panel.domains.nameservers', $domain) }}"
                                  class="border rounded p-3 bg-light mt-2">
                                @csrf
                                @method('PUT')
                                @php $existingNs = $domain->nameservers ?? ['', '', '', '']; @endphp
                                @for($i = 0; $i < 4; $i++)
                                    <div class="mb-2">
                                        <label class="form-label f-11 f-light mb-1">NS{{ $i + 1 }}</label>
                                        <input type="text"
                                               name="nameservers[]"
                                               class="form-control form-control-sm font-monospace @error('nameservers.' . $i) is-invalid @enderror"
                                               value="{{ old('nameservers.' . $i, $existingNs[$i] ?? '') }}"
                                               placeholder="{{ __('panel.domains.nameservers_placeholder') }}"
                                               {{ $i === 0 ? 'required' : '' }}>
                                        @error('nameservers.' . $i)
                                            <div class="invalid-feedback f-11">{{ $message }}</div>
                                        @enderror
                                    </div>
                                @endfor
                                <p class="f-11 f-light mb-3">{{ __('panel.domains.nameservers_note') }}</p>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i data-feather="save" style="width:12px;height:12px"></i>
                                    {{ __('panel.domains.nameservers_update') }}
                                </button>
                            </form>
                        </div>
                    </div>

                    {{-- DNS Management --}}
                    <div class="border-top pt-3 mt-1 flex items-center justify-between">
                        <div>
                            <p class="f-w-500 mb-1">{{ __('panel.domains.dns_records') }}</p>
                            <p class="f-light f-12 mb-0">{{ __('panel.domains.dns_description') }}</p>
                        </div>
                        <a href="{{ route('panel.domains.dns', $domain) }}"
                           class="btn btn-outline-primary btn-sm flex items-center gap-1">
                            <i data-feather="globe" style="width:13px;height:13px"></i>
                            {{ __('panel.dns.manage') }}
                        </a>
                    </div>
                </x-panel.card>
            </div>

            <div class="col-span-4 xl:col-span-12">
                {{-- Transfer auth code --}}
                <x-panel.card :title="__('panel.domains.transfer')">
                    <p class="f-light f-12 mb-3">{{ __('panel.domains.transfer_note') }}</p>
                    @if($domain->auth_code)
                        <div class="input-group input-group-sm">
                            <input type="password" id="auth-code-input"
                                   class="form-control font-monospace f-12"
                                   value="{{ $domain->auth_code }}"
                                   readonly>
                            <button class="btn btn-outline-secondary" type="button"
                                    onclick="var i=document.getElementById('auth-code-input');i.type=i.type==='password'?'text':'password'">
                                <i data-feather="eye" style="width:13px;height:13px"></i>
                            </button>
                        </div>
                    @else
                        <p class="f-light f-12 mb-0">{{ __('panel.domains.auth_code_unavailable') }}</p>
                    @endif
                </x-panel.card>

                {{-- Auto-renew toggle --}}
                <x-panel.card :title="__('panel.domains.auto_renew')">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            @if($domain->auto_renew)
                                <span class="badge badge-light-success">ON</span>
                                <span class="f-12 f-light">{{ __('panel.domains.auto_renew_on') }}</span>
                            @else
                                <span class="badge badge-light-secondary">OFF</span>
                                <span class="f-12 f-light">{{ __('panel.domains.auto_renew_off') }}</span>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('panel.domains.auto-renew', $domain) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-{{ $domain->auto_renew ? 'warning' : 'success' }} btn-sm">
                                <i data-feather="{{ $domain->auto_renew ? 'toggle-right' : 'toggle-left' }}" style="width:14px;height:14px;"></i>
                                {{ $domain->auto_renew ? __('panel.domains.auto_renew_disable') : __('panel.domains.auto_renew_enable') }}
                            </button>
                        </form>
                    </div>
                </x-panel.card>

                {{-- Back --}}
                <div class="mt-1">
                    <a href="{{ route('panel.domains.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i data-feather="arrow-left" style="width:13px;height:13px"></i>
                        {{ __('panel.nav.domains') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
