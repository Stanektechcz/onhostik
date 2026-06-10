@extends('layouts.panel')

@php($breadcrumbTitle = __('panel.nav.orders'))

@section('title', __('panel.nav.orders'))

@section('content')
    <div class="container-fluid">
        <x-panel.card :title="__('panel.nav.orders')">
            <p class="f-light mb-0">{{ __('panel.common.placeholder') }}</p>
        </x-panel.card>
    </div>
@endsection