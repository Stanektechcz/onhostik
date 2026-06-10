@extends('layouts.panel')

@php($breadcrumbTitle = __('panel.nav.orders').' #'.$order->id)
@php($breadcrumbItems = [__('panel.nav.orders') => route('panel.orders.index'), '#'.$order->id => ''])

@section('title', __('panel.nav.orders').' #'.$order->id)

@section('content')
    <div class="container-fluid">
        <x-panel.card :title="__('panel.nav.orders').' #'.$order->id" :subtitle="__('panel.common.detail')">
            <p class="f-light mb-0">{{ __('panel.common.placeholder') }}</p>
        </x-panel.card>
    </div>
@endsection
