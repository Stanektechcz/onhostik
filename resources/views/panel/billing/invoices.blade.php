@extends('layouts.panel')

@php($breadcrumbTitle = __('panel.nav.invoices'))

@section('title', __('panel.nav.invoices'))

@section('content')
    <div class="container-fluid">
        <x-panel.card :title="__('panel.nav.invoices')">
            <p class="f-light mb-0">{{ __('panel.common.placeholder') }}</p>
        </x-panel.card>
    </div>
@endsection