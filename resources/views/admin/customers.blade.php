@extends('layouts.panel')

@php($breadcrumbTitle = __('panel.nav.admin_customers'))

@section('title', __('panel.nav.admin_customers'))

@section('content')
    <div class="container-fluid">
        <x-panel.card :title="__('panel.nav.admin_customers')">
            <p class="f-light mb-0">{{ __('panel.common.placeholder') }}</p>
        </x-panel.card>
    </div>
@endsection