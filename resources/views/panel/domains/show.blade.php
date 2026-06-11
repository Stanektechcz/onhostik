@extends('layouts.panel')

@php($breadcrumbTitle = $domain->fqdn())
@php($breadcrumbItems = [__('panel.nav.domains') => route('panel.domains.index'), $domain->fqdn() => ''])

@section('title', $domain->fqdn())

@section('content')
    <div class="container-fluid">
        <x-panel.flash />

        <x-panel.card :title="$domain->fqdn()">
            <div class="row mb-3">
                <div class="col-md-3">
                    <p class="f-light f-12 mb-1">{{ __('panel.common.status') }}</p>
                    @if($domain->wedos_domain_id !== null)
                        <span class="badge badge-light-success">{{ __('panel.domains.registered') }}</span>
                    @else
                        <span class="badge badge-light-warning">{{ __('panel.domains.pending') }}</span>
                    @endif
                </div>
                <div class="col-md-3">
                    <p class="f-light f-12 mb-1">{{ __('panel.domains.registrar') }}</p>
                    <p class="mb-0 text-uppercase">{{ $domain->registrar ?? '—' }}</p>
                </div>
                <div class="col-md-3">
                    <p class="f-light f-12 mb-1">{{ __('panel.domains.registered_at') }}</p>
                    <p class="mb-0">{{ $domain->registered_at?->format('d.m.Y') ?? '—' }}</p>
                </div>
                <div class="col-md-3">
                    <p class="f-light f-12 mb-1">{{ __('panel.domains.expires_at') }}</p>
                    <p class="mb-0">{{ $domain->expires_at?->format('d.m.Y') ?? '—' }}</p>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <p class="f-light f-12 mb-1">{{ __('panel.domains.auto_renew') }}</p>
                    <p class="mb-0">{{ $domain->auto_renew ? __('panel.common.yes') : __('panel.common.no') }}</p>
                </div>
                <div class="col-md-3">
                    <p class="f-light f-12 mb-1">{{ __('panel.domains.nameservers') }}</p>
                    <p class="mb-0">{{ implode(', ', $domain->nameservers ?? []) ?: '—' }}</p>
                </div>
                <div class="col-md-3">
                    <p class="f-light f-12 mb-1">{{ __('panel.domains.service') }}</p>
                    <p class="mb-0">
                        @if($domain->service)
                            <a href="{{ route('panel.services.show', $domain->service) }}">{{ $domain->service->label }}</a>
                        @else
                            —
                        @endif
                    </p>
                </div>
            </div>
        </x-panel.card>
    </div>
@endsection
