@extends('layouts.front')

@section('title', $post->title . ' — OnHost Blog')
@section('meta_description', $post->excerpt ?? Str::limit(strip_tags($post->body ?? ''), 160))

@section('content')

    {{-- HERO --}}
    <div class="top-header overlay">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-12">
                    <div class="wrapper">
                        <div class="plans badge feat bg-purple mb-3">{{ $post->category }}</div>
                        <h1 class="heading">{{ $post->title }}</h1>
                        <div class="subheading">
                            <i class="fas fa-calendar-alt me-2"></i>{{ $post->published_at?->format('d. m. Y') }}
                            @if($post->author)
                                &nbsp;·&nbsp;
                                <i class="fas fa-user me-1"></i>{{ $post->author->name }}
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ARTICLE --}}
    <section class="services blog sec-normal pt-80 pb-5 bg-colorstyle">
        <div class="container">
            <div class="row">

                {{-- Content --}}
                <div class="col-md-8">
                    <div class="sec-main sec-bg1 bg-colorstyle p-5">
                        @if($post->excerpt)
                            <p class="lead seccolor border-bottom pb-4 mb-4">{{ $post->excerpt }}</p>
                        @endif
                        <div class="blog-content mergecolor">
                            {!! $post->body !!}
                        </div>
                    </div>

                    {{-- Related --}}
                    @if($related->isNotEmpty())
                        <div class="mt-5">
                            <h4 class="mergecolor mb-4">Související články</h4>
                            <div class="row">
                                @foreach($related as $r)
                                    <div class="col-md-4 mb-4">
                                        <div class="service-section bg-colorstyle noshadow">
                                            <div class="plans badge feat bg-dark">{{ $r->category }}</div>
                                            <div class="title mt-2 mergecolor f-15">
                                                <a href="{{ route('front.blog.show', $r->slug) }}" class="mergecolor">{{ $r->title }}</a>
                                            </div>
                                            <p class="subtitle seccolor f-13">{{ Str::limit($r->excerpt, 80) }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Sidebar --}}
                <div class="col-md-4">
                    <div class="sec-main sec-bg1 bg-colorstyle p-4 mb-4">
                        <h5 class="mergecolor mb-3">Sdílet článek</h5>
                        <div class="d-flex gap-2">
                            <a href="https://twitter.com/intent/tweet?url={{ urlencode(request()->url()) }}&text={{ urlencode($post->title) }}"
                               target="_blank" rel="noopener" class="btn btn-default-yellow-fill">
                                <i class="fab fa-twitter"></i> Twitter
                            </a>
                            <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode(request()->url()) }}"
                               target="_blank" rel="noopener" class="btn btn-default-grad-purple-fill">
                                <i class="fab fa-linkedin"></i> LinkedIn
                            </a>
                        </div>
                    </div>

                    <div class="sec-main sec-bg1 bg-colorstyle p-4 mb-4">
                        <h5 class="mergecolor mb-3">O OnHost</h5>
                        <p class="seccolor f-14">Moderní hostingové služby s férovými cenami, AI asistentem a technickou podporou v češtině.</p>
                        <a href="{{ route('front.webhosting') }}" class="btn btn-default-yellow-fill w-100">Zobrazit tarify</a>
                    </div>

                    <div class="sec-main sec-bg1 bg-colorstyle p-4">
                        <h5 class="mergecolor mb-3">Potřebujete pomoc?</h5>
                        <p class="seccolor f-14">Naši technici jsou tu pro vás — tickets, chat nebo e-mail.</p>
                        <a href="{{ route('front.contact') }}" class="btn btn-default-grad-purple-fill w-100">Kontaktovat podporu</a>
                    </div>
                </div>

            </div>
        </div>
    </section>

@endsection

@php
use Illuminate\Support\Str;
@endphp
