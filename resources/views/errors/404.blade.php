@extends('layouts.front')

@section('title', '404 — Stránka nenalezena')
@section('meta_description', 'Stránka nenalezena — Onhost.cz')

@section('content')
    <section class="sec-normal notfound pt-150">
        <div class="total-grad-pink-blue-intense"></div>
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-sm-12 col-md-8 col-lg-6">
                    <img class="svg" src="{{ asset('front/patterns/notfound.svg') }}" alt="404 — stránka nenalezena" width="100%" height="100%">
                </div>
            </div>
            <div class="col-md-12 text-center pt-5">
                <p class="text-white f-18">Stránka nenalezena — adresa neexistuje nebo byla přesunuta.</p>
                <a href="{{ route('front.home') }}" class="btn btn-default-grad-purple-fill mt-3 me-2">Zpět na úvod</a>
                <a href="{{ route('front.support') }}" class="btn btn-default-yellow-fill mt-3">Kontaktovat podporu</a>
            </div>
        </div>
    </section>
@endsection
