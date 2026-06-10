@extends('layouts.panel')

@php($breadcrumbTitle = $domain->domain)
@php($breadcrumbItems = [__('panel.nav.domains') => route('panel.domains.index'), $domain->domain => ''])

@section('title', $domain->domain)

@section('content')
    <div class="container-fluid">
        <x-panel.card :title="$domain->domain" :subtitle="__('panel.common.detail')">
            <p class="f-light mb-0">{{ __('panel.common.placeholder') }}</p>
        </x-panel.card>
    </div>
@endsection
