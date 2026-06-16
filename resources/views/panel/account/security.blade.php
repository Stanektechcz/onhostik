@extends('layouts.panel')

@php($breadcrumbTitle = __('panel.nav.security'))

@section('title', __('panel.nav.security'))

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <div class="row">
            <div class="col-xl-6">
                <x-panel.card :title="__('panel.account.password_section')">
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <div class="bg-light-primary rounded p-2 flex-shrink-0">
                            <i data-feather="lock" class="font-primary" style="width:20px;height:20px"></i>
                        </div>
                        <div>
                            <p class="mb-1 f-14 f-w-500">{{ __('panel.account.login_section') }}</p>
                            <p class="mb-0 f-12 f-light">{{ $user?->email }}</p>
                        </div>
                    </div>

                    <div class="border-top pt-3">
                        <p class="f-light f-12 mb-3">{{ __('panel.account.password_info') }}</p>
                        <a href="{{ route('password.request') }}" class="btn btn-outline-primary btn-sm">
                            <i data-feather="key" style="width:13px;height:13px"></i>
                            {{ __('panel.account.change_password') }}
                        </a>
                    </div>
                </x-panel.card>

                <x-panel.card :title="__('panel.account.two_factor')">
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <div class="bg-light-secondary rounded p-2 flex-shrink-0">
                            <i data-feather="shield" class="font-secondary" style="width:20px;height:20px"></i>
                        </div>
                        <div>
                            <p class="mb-1 f-14 f-w-500">{{ __('panel.account.two_factor') }}</p>
                            <span class="badge badge-light-warning">{{ __('panel.account.two_factor_coming') }}</span>
                        </div>
                    </div>
                    <p class="f-light f-12 mb-0">{{ __('panel.account.two_factor_note') }}</p>
                </x-panel.card>
            </div>

            <div class="col-xl-6">
                <x-panel.card :title="__('panel.account.login_section')">
                    <table class="table table-borderless mb-0">
                        <tr>
                            <td class="f-light ps-0 f-12" style="width:140px;">{{ __('panel.account.name') }}</td>
                            <td class="f-w-500">{{ $user?->name ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="f-light ps-0 f-12">E-mail</td>
                            <td>
                                {{ $user?->email ?? '—' }}
                                @if($user?->email_verified_at)
                                    <span class="badge badge-light-success ms-1 f-10">
                                        <i data-feather="check" style="width:10px;height:10px"></i>
                                        {{ __('panel.account.email_verified') }}
                                    </span>
                                @else
                                    <span class="badge badge-light-warning ms-1 f-10">{{ __('panel.account.email_not_verified') }}</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="f-light ps-0 f-12">{{ __('panel.account.last_login') }}</td>
                            <td class="f-12">
                                @if($user?->last_login_at)
                                    {{ $user->last_login_at->format('d.m.Y H:i') }}
                                    <span class="f-light">({{ $user->last_login_at->diffForHumans() }})</span>
                                @else
                                    <span class="f-light">{{ __('panel.account.never_logged') }}</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="f-light ps-0 f-12">{{ __('panel.common.status') }}</td>
                            <td>
                                @if($user?->is_active)
                                    <span class="badge badge-light-success">{{ __('panel.common.active') }}</span>
                                @else
                                    <span class="badge badge-light-danger">{{ __('panel.common.inactive') }}</span>
                                @endif
                            </td>
                        </tr>
                    </table>
                </x-panel.card>

                <x-panel.card title="Rychlé akce">
                    <div class="d-flex flex-column gap-2">
                        <a href="{{ route('panel.account.profile') }}" class="btn btn-outline-secondary btn-sm text-start">
                            <i data-feather="user" style="width:13px;height:13px"></i>
                            {{ __('panel.nav.profile') }}
                        </a>
                        <a href="{{ route('panel.account.billing') }}" class="btn btn-outline-secondary btn-sm text-start">
                            <i data-feather="file-text" style="width:13px;height:13px"></i>
                            {{ __('panel.nav.billing_details') }}
                        </a>
                    </div>
                </x-panel.card>
            </div>
        </div>
    </div>
@endsection
