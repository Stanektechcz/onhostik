@props(['title' => null, 'items' => []])
@if($title)
@php
    $homeRoute = request()->routeIs('admin.*') ? route('admin.dashboard')
               : (request()->routeIs('partner.*') ? route('partner.dashboard') : route('panel.dashboard'));
@endphp
<div class="container-fluid">
    <div class="page-title">
        <div class="grid grid-cols-12 gap-3">
            <div class="col-span-6"><h3>{{ $title }}</h3></div>
            <div class="col-span-6">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ $homeRoute }}"><i data-feather="home"></i></a></li>
                    @foreach($items as $label => $url)
                        @if($loop->last)
                            <li class="breadcrumb-item active">{{ $label }}</li>
                        @else
                            <li class="breadcrumb-item"><a href="{{ $url }}">{{ $label }}</a></li>
                        @endif
                    @endforeach
                </ol>
            </div>
        </div>
    </div>
</div>
@endif
