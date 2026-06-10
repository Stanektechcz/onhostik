@extends('layouts.panel')

@php($breadcrumbTitle = __('panel.nav.services'))

@section('title', __('panel.nav.services'))

@section('content')
    <div class="container-fluid">
        <x-panel.card :title="__('panel.nav.services')">
            <p class="f-light mb-0">{{ __('panel.common.placeholder') }}</p>
        </x-panel.card>
    </div>
@endsection