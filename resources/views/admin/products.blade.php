@extends('layouts.panel')

@php($breadcrumbTitle = __('panel.nav.admin_products'))

@section('title', __('panel.nav.admin_products'))

@section('content')
    <div class="container-fluid">
        <x-panel.card :title="__('panel.nav.admin_products')">
            <p class="f-light mb-0">{{ __('panel.common.placeholder') }}</p>
        </x-panel.card>
    </div>
@endsection