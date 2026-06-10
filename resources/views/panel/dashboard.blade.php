@extends('layouts.panel')

@php($breadcrumbTitle = __('panel.nav.dashboard'))

@section('title', __('panel.nav.dashboard'))

@section('content')
    <div class="container-fluid">
        <div class="grid grid-cols-12 gap-4">
            <div class="col-span-12 md:col-span-6 xl:col-span-3">
                <x-panel.stat-widget :label="__('panel.nav.services')" value="0" icon="server" color="primary" />
            </div>
            <div class="col-span-12 md:col-span-6 xl:col-span-3">
                <x-panel.stat-widget :label="__('panel.nav.domains')" value="0" icon="globe" color="secondary" />
            </div>
            <div class="col-span-12 md:col-span-6 xl:col-span-3">
                <x-panel.stat-widget :label="__('panel.nav.invoices')" value="0" icon="file-text" color="warning" />
            </div>
            <div class="col-span-12 md:col-span-6 xl:col-span-3">
                <x-panel.stat-widget :label="__('panel.nav.credits')" value="—" icon="dollar-sign" color="success" />
            </div>
        </div>

        <x-panel.card :title="__('panel.nav.dashboard')">
            <p class="f-light mb-0">{{ __('panel.common.placeholder') }}</p>
        </x-panel.card>
    </div>
@endsection
