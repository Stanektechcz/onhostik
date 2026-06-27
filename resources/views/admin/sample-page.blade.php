@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Ukázková stránka';
    $breadcrumbItems = ['Ukázková stránka' => ''];
@endphp

@section('title', 'Ukázková stránka')

@section('content')
<div class="container-fluid">
    <div class="container">
        <div class="grid grid-cols-12 card-gap">
            <div class="col-span-12">
                <div class="card">
                    <div class="card-header card-no-border">
                        <div class="header-top"><h5>Ukázková stránka</h5></div>
                    </div>
                    <div class="card-body">
                        <p class="f-m-light">
                            Tato stránka slouží jako šablona pro nové sekce systému. Obsah bude doplněn
                            v rámci dalšího vývoje OnHost platformy.
                        </p>
                        <p class="f-m-light">
                            OnHost je moderní hostingová platforma nabízející webhosting, VPS, mailhosting
                            a správu domén. Zákaznický panel umožňuje kompletní správu hostingových služeb
                            včetně objednávek, fakturace a technické podpory.
                        </p>
                        <p class="f-m-light">
                            Administrační systém je postaven na Laravel 11 s Cuba Tailwind admin dashboardem.
                            Všechny komponenty jsou plně responzivní a optimalizované pro moderní prohlížeče.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
