@extends('layouts.cuba-standalone')
@section('title', '404 — Stránka nenalezena')
@section('content')
<div class="error-wrapper">
    <div class="container">
        <svg><use href="{{ asset('panel/assets/svg/icon-sprite.svg#error-404') }}"></use></svg>
        <div class="grid grid-cols-12">
            <div class="col-start-4 md:col-start-0 col-span-6 md:col-span-12">
                <h3>Stránka nenalezena</h3>
                <p class="sub-content [@media(max-width:767px)]:px-[15px]">
                    Tato stránka neexistuje nebo byla přesunuta. Zkontrolujte URL adresu nebo se vraťte na domovskou stránku.
                </p>
            </div>
        </div>
        <div>
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
