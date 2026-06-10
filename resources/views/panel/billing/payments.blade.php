@extends('layouts.panel')

@php($breadcrumbTitle = __('panel.nav.payments'))

@section('title', __('panel.nav.payments'))

@section('content')
    <div class="container-fluid">
        <x-panel.card :title="__('panel.nav.payments')">
            <p class="f-light mb-0">{{ __('panel.common.placeholder') }}</p>
        </x-panel.card>
    </div>
@endsection