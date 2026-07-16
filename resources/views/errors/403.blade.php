@extends('layouts.cuba-standalone')
@section('title', '403 — Přístup zakázán')
@section('content')
<div class="error-wrapper">
    <div class="container">
        <svg><use href="{{ asset('panel/svg/icon-sprite.svg#error-403') }}"></use></svg>
        <div class="grid grid-cols-12">
            <div class="col-start-4 md:col-start-0 col-span-6 md:col-span-12">
                <h3 class="[@media(max-width:575px)]:text-center">Přístup zakázán</h3>
                <p class="sub-content [@media(max-width:575px)]:text-center [@media(max-width:767px)]:px-[15px]">
                    Nemáte oprávnění pro přístup k této stránce.
                    Ověřte svá přístupová práva nebo kontaktujte správce.
                </p>
            </div>
        </div>
        <div class="[@media(max-width:575px)]:text-center">
            <a class="btn btn-primary btn-lg text-white hover:text-white !rounded-lg me-2" href="{{ url('/panel') }}">
                Zákaznický panel
            </a>
            <a class="btn btn-outline-primary btn-lg !rounded-lg" href="{{ url('/') }}">
                Domovská stránka
            </a>
        </div>
    </div>
</div>
@endsection
