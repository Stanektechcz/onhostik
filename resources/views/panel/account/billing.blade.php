@extends('layouts.panel')

@php($breadcrumbTitle = __('panel.nav.billing_details'))

@section('title', __('panel.nav.billing_details'))

@section('content')
    <div class="container-fluid">
        <x-panel.card :title="__('panel.nav.billing_details')">
            <p class="f-light mb-0">{{ __('panel.common.placeholder') }}</p>
        </x-panel.card>
    </div>
@endsection