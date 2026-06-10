@extends('layouts.panel')

@php($breadcrumbTitle = __('panel.nav.profile'))

@section('title', __('panel.nav.profile'))

@section('content')
    <div class="container-fluid">
        <x-panel.card :title="__('panel.nav.profile')">
            <p class="f-light mb-0">{{ __('panel.common.placeholder') }}</p>
        </x-panel.card>
    </div>
@endsection