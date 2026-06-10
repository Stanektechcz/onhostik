@extends('layouts.panel')

@php($breadcrumbTitle = __('panel.nav.support'))

@section('title', __('panel.nav.support'))

@section('content')
    <div class="container-fluid">
        <x-panel.card :title="__('panel.nav.support')">
            <p class="f-light mb-0">{{ __('panel.common.placeholder') }}</p>
        </x-panel.card>
    </div>
@endsection