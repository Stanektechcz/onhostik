@extends('layouts.front')

@section('title', $article->title . ' — Znalostní báze OnHost')
@section('meta_description', $article->excerpt ?? Str::limit(strip_tags($article->body ?? ''), 160))

@push('jsonld')
@php
echo '<script type="application/ld+json" nonce="' . ($cspNonce ?? '') . '">' . json_encode([
    '@context'    => 'https://schema.org',
    '@type'       => 'Article',
    'headline'    => $article->title,
    'description' => $article->excerpt ?? Str::limit(strip_tags($article->body ?? ''), 160),
    'datePublished' => $article->created_at?->toIso8601String(),
    'dateModified'  => $article->updated_at?->toIso8601String(),
    'url'           => route('front.kb.show', $article->slug),
    'inLanguage'    => 'cs',
    'publisher'     => ['@type' => 'Organization', 'name' => 'Onhost.cz', 'url' => url('/')],
    'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => route('front.kb.show', $article->slug)],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
@endphp
@endpush

@section('content')

    {{-- HERO --}}
    <div class="top-header overlay">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 col-md-12">
                    <div class="wrapper">
                        <div class="plans badge feat bg-purple mb-3">{{ $article->category }}</div>
                        <h1 class="heading">{{ $article->title }}</h1>
                        @if($article->excerpt)
                            <div class="subheading">{{ $article->excerpt }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ARTICLE CONTENT --}}
    <section class="sec-normal pt-80 pb-5 bg-colorstyle">
        <div class="container">
            <div class="row">

                {{-- Article body --}}
                <div class="col-md-8">
                    <div class="sec-main sec-bg1 bg-colorstyle p-5">
                        <div class="kb-content mergecolor">
                            {!! $article->body !!}
                        </div>
                    </div>

                    {{-- Back link --}}
                    <div class="mt-4">
                        <a href="{{ route('front.kb') }}" class="btn btn-default-grad-purple-fill">
                            <i class="fas fa-arrow-left me-2"></i>Zpět do znalostní báze
                        </a>
                    </div>
                </div>

                {{-- Sidebar --}}
                <div class="col-md-4">

                    {{-- Related articles --}}
                    @if($related->isNotEmpty())
                        <div class="sec-main sec-bg1 bg-colorstyle p-4 mb-4">
                            <h5 class="mergecolor mb-3">Související články</h5>
                            <ul class="list-unstyled">
                                @foreach($related as $r)
                                    <li class="py-2 border-bottom">
                                        <a href="{{ route('front.kb.show', $r->slug) }}" class="mergecolor d-block">
                                            <i class="fas fa-file-alt purple me-2"></i>{{ $r->title }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="sec-main sec-bg1 bg-colorstyle p-4 mb-4">
                        <h5 class="mergecolor mb-3">Potřebujete pomoc?</h5>
                        <p class="seccolor f-14">Nenašli jste odpověď? Otevřete ticket a náš tým vám pomůže.</p>
                        <a href="{{ route('front.contact') }}" class="btn btn-default-yellow-fill w-100">Kontaktovat podporu</a>
                    </div>

                    <div class="sec-main sec-bg1 bg-colorstyle p-4">
                        <h5 class="mergecolor mb-3">Rychlé odkazy</h5>
                        <ul class="list-unstyled seccolor">
                            <li class="py-1"><a href="{{ route('front.kb') }}" class="seccolor"><i class="fas fa-book purple me-2"></i>Celá znalostní báze</a></li>
                            <li class="py-1"><a href="{{ route('front.faq') }}" class="seccolor"><i class="fas fa-question-circle purple me-2"></i>Časté dotazy</a></li>
                            <li class="py-1"><a href="{{ route('front.blog.index') }}" class="seccolor"><i class="fas fa-rss purple me-2"></i>Blog a novinky</a></li>
                        </ul>
                    </div>
                </div>

            </div>
        </div>
    </section>

@endsection

@php
use Illuminate\Support\Str;
@endphp
