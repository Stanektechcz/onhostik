@extends('layouts.panel')

@php($breadcrumbTitle = $service->label)
@php($breadcrumbItems = [__('panel.nav.services') => route('panel.services.index'), $service->label => ''])

@section('title', $service->label)

@section('content')
    <div class="container-fluid">
        <x-panel.card :title="$service->label" :subtitle="__('panel.common.detail')">
            <p class="f-light mb-2">
                <x-panel.status-badge :status="$service->status" />
            </p>
            <p class="f-light mb-0">{{ __('panel.common.placeholder') }}</p>
        </x-panel.card>
    </div>
@endsection
