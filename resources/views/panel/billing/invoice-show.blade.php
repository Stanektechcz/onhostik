@extends('layouts.panel')

@php($breadcrumbTitle = $invoice->number)
@php($breadcrumbItems = [__('panel.nav.invoices') => route('panel.billing.invoices'), $invoice->number => ''])

@section('title', $invoice->number)

@section('content')
    <div class="container-fluid">
        <x-panel.card :title="$invoice->number" :subtitle="__('panel.common.detail')">
            <p class="f-light mb-2"><x-panel.money :money="$invoice->total" /></p>
            <p class="f-light mb-0">{{ __('panel.common.placeholder') }}</p>
        </x-panel.card>
    </div>
@endsection
