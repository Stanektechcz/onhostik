@extends('layouts.front')

@section('title', $post->title . ' — OnHost Blog')
@section('meta_description', $post->excerpt ?? Str::limit(strip_tags($post->body ?? ''), 160))

@section('content')

    {{-- HERO --}}
    <div class="top-header">
        <div class="total-grad-inverse"></div>
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
    <section class="shopping blog sec-normal pt-80 pb-80 sec-bg2 motpath bg-seccolorstyle">
        <div class="container">
            <div class="row">

                {{-- Content --}}
                <div class="col-md-8">
                    <div class="sec-main sec-bg1 bg-colorstyle noshadow">
                        {{-- Article meta: date + social share --}}
                        <div class="row text-blog py-3 px-4 border-bottom">
                            <div class="col-sm-12 col-md-6 p-0">
                                <div class="timer d-flex align-items-center seccolor">
                                    <i class="icon-calendar"></i>
                                    <span class="ps-2 pe-4">{{ $post->published_at?->format('d. M Y') }}</span>
                                    @if($post->author)
                                        <i class="icon-man"></i>
                                        <span class="ps-2">{{ $post->author->name }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-sm-12 col-md-6 p-0">
                                <div class="social-icon d-flex gap-2">
                                    <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(request()->url()) }}" target="_blank" title="Facebook">
                                        <i class="fab fa-facebook-f bg-seccolorstyle noshadow"></i>
                                    </a>
                                    <a href="https://twitter.com/intent/tweet?url={{ urlencode(request()->url()) }}&text={{ urlencode($post->title) }}" target="_blank" title="Twitter">
                                        <i class="fab fa-x-twitter bg-seccolorstyle noshadow"></i>
                                    </a>
                                    <a href="https://www.linkedin.com/shareArticle?url={{ urlencode(request()->url()) }}" target="_blank" title="LinkedIn">
                                        <i class="fab fa-linkedin-in bg-seccolorstyle noshadow"></i>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="p-5">
                        @if($post->excerpt)
                            <p class="lead seccolor border-bottom pb-4 mb-4">{{ $post->excerpt }}</p>
                        @endif
                        <div class="blog-content mergecolor">
                            {!! $post->body !!}
                        </div>
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
