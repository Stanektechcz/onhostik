@extends('layouts.panel')

@php($breadcrumbTitle = __('panel.nav.new_order'))

@section('title', __('panel.nav.new_order'))

@section('content')
    <div class="container-fluid">
        <x-panel.card :title="__('panel.nav.new_order')">
            <p class="f-light mb-0">{{ __('panel.common.placeholder') }}</p>
        </x-panel.card>
    </div>
@endsection