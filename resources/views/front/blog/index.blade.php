@extends('layouts.front')

@section('title', 'Blog — Novinky, návody a tipy z oblasti hostingu')
@section('meta_description', 'OnHost blog: nejnovější zprávy, technické návody, tipy pro správu webu, hosting a domény. Čtěte pro lepší výsledky v online podnikání.')

@section('content')

    {{-- HERO --}}
    <div class="top-header">
        <div class="total-grad-inverse"></div>
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="wrapper">
                        <h1 class="heading">Blog & Novinky</h1>
                        <div class="subheading">Technické návody, tipy a aktuální zprávy o serverech a službách OnHost.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- BLOG GRID --}}
    <section class="services blog sec-normal pt-80 pb-5 bg-colorstyle">
        <div class="container">
            <div class="row">

                {{-- Posts --}}
                <div class="col-md-8">
                    @if($posts->isEmpty())
                        <div class="text-center py-5">
                            <i class="icon-drives f-60 seccolor mb-3 d-block"></i>
                            <h4 class="mergecolor">Žádné příspěvky zatím</h4>
                            <p class="seccolor">Brzy přidáme první články a návody.</p>
                        </div>
                    @else
                        <div class="service-wrap">
                            <div class="row">
                                @foreach($posts as $post)
                                    <div class="col-md-12 col-lg-12 col-xl-6 mb-5">
                                        {{-- Antler action-content overlay --}}
                                        <div class="action-content">
                                            <div class="action rounded-bottom">
                                                <div class="metatag">
                                                    <div class="kudos">
                                                        <a href="{{ route('front.blog.show', $post->slug) }}" title="Číst">
                                                            <i class="icon-favorite ps-0"></i>
                                                        </a>
                                                    </div>
                                                    <div class="rating">
                                                        <i class="fas fa-star c-yellow"></i>
                                                        <i class="fas fa-star c-yellow"></i>
                                                        <i class="fas fa-star c-yellow"></i>
                                                        <i class="fas fa-star c-yellow"></i>
                                                        <i class="fas fa-star c-yellow"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="service-section m-0 bg-seccolorstyle noshadow">
                                            <div class="plans badge feat bg-dark">{{ $post->category }}</div>
                                            <div class="title mt-0 mergecolor">
                                                <a href="{{ route('front.blog.show', $post->slug) }}" class="mergecolor">{{ $post->title }}</a>
                                            </div>
                                            <p class="subtitle seccolor">{{ $post->excerpt }}</p>
                                            <hr>
                                            <div class="small d-flex align-items-center seccolor">
                                                <i class="icon-calendar text-dark seccolor"></i>
                                                <span class="ps-2 pe-4">{{ $post->published_at?->format('d.m.Y') }}</span>
                                                @if($post->author)
                                                    <i class="icon-man text-dark seccolor"></i>
                                                    <span class="ps-2">{{ $post->author->name }}</span>
                                                @endif
                                                <a href="{{ route('front.blog.show', $post->slug) }}" class="btn btn-default-yellow-fill ms-auto f-12 py-1 px-3">Číst dál</a>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Pagination --}}
                        <div class="d-flex justify-content-center mt-4">
                            {{ $posts->links() }}
                        </div>
                    @endif
                </div>

                {{-- Sidebar --}}
                <div class="col-md-4">
                    <div class="sec-main sec-bg1 bg-colorstyle p-4 mb-4">
                        <h5 class="mergecolor mb-3">Kategorie</h5>
                        <ul class="list-unstyled seccolor">
                            @foreach($categories as $cat)
                                <li class="py-1 border-bottom">
                                    <a href="{{ route('front.blog.index') }}?kategorie={{ $cat }}" class="seccolor">
                                        <i class="fas fa-tag purple me-2"></i>{{ $cat }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    @if(isset($recent) && $recent->isNotEmpty())
                        <div class="sec-main sec-bg1 bg-colorstyle p-4">
                            <h5 class="mergecolor mb-3">Nejnovější příspěvky</h5>
                            <ul class="list-unstyled">
                                @foreach($recent as $r)
                                    <li class="py-2 border-bottom">
                                        <a href="{{ route('front.blog.show', $r->slug) }}" class="mergecolor d-block">{{ $r->title }}</a>
                                        <span class="small seccolor">{{ $r->published_at?->format('d.m.Y') }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="services help sec-bg2 pt-4 pb-80 bg-colorstyle">
        <div class="container">
            <div class="service-wrap">
                <div class="row">
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle">
                            <a href="{{ route('front.kb') }}" class="help-item" title="Znalostní báze">
                                <div class="img"><i class="icon-drives f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Znalostní báze</div>
                                    <div class="description seccolor">Podrobné návody krok za krokem pro technicky zdatné.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle">
                            <a href="{{ route('front.faq') }}" class="help-item" title="FAQ">
                                <div class="img"><i class="icon-support f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Časté dotazy</div>
                                    <div class="description seccolor">Rychlé odpovědi na nejčastější otázky o hostingu.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-4">
                        <div class="help-container bg-seccolorstyle">
                            <a href="{{ route('front.contact') }}" class="help-item" title="Kontakt">
                                <div class="img"><i class="icon-emailopen f-40 purple"></i></div>
                                <div class="inform">
                                    <div class="title mergecolor">Kontaktujte nás</div>
                                    <div class="description seccolor">Nemůžete najít co hledáte? Napište nám — odpovíme do hodiny.</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

@endsection
