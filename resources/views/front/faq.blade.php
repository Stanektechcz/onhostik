@extends('layouts.front')

@section('title', __('front.pages.faq.title'))

@section('content')
    <x-front.page-banner :title="__('front.pages.faq.title')" />

    <section class="services bg-colorstyle pb-150">
        <div class="container">
            <p class="seccolor mb-0" data-aos="fade-up">{{ __('front.pages.placeholder_note') }}</p>
        </div>
    </section>
@endsection