@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Znalostní báze';
    $breadcrumbItems = ['Znalostní báze' => ''];
    $grouped = $articles->getCollection()->groupBy('category');
@endphp

@section('title', 'Znalostní báze')

@section('content')
<div class="container-fluid">
    <div class="container">
        <div class="grid grid-cols-12 card-gap">

            {{-- Hero search --}}
            <div class="col-span-12">
                <div class="knowledgebase-bg">
                    <div style="height:260px;background:linear-gradient(135deg,rgba(var(--theme-default),.12),rgba(var(--theme-default),.03));border-radius:8px;display:flex;align-items:center;justify-content:center;">
                        <i data-feather="book-open" style="width:80px;height:80px;opacity:.12;"></i>
                    </div>
                </div>
                <div class="knowledgebase-search">
                    <div>
                        <h3 class="txt-dark">Jak vám můžeme pomoci?</h3>
                        <form class="form-inline" method="GET" action="{{ route('panel.kb.index') }}">
                            <div class="form-group w-full">
                                <i data-feather="search"></i>
                                <input class="form-control-plaintext w-full" type="text" name="q"
                                       value="{{ $search }}" placeholder="Zadejte otázku nebo klíčové slovo…">
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Stat widgets --}}
            <div class="col-span-4 xl:col-span-6 sm:col-span-12">
                <div class="card bg-primary">
                    <div class="card-body">
                        <div class="flex faq-widgets">
                            <div class="grow faq-flex">
                                <h5>Články</h5>
                                <p>Procházejte naši databázi znalostí a najděte odpovědi na nejčastější otázky o hostingových službách.</p>
                            </div>
                            <i data-feather="book-open"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-span-4 xl:col-span-6 sm:col-span-12">
                <div class="card bg-primary">
                    <div class="card-body">
                        <div class="flex faq-widgets">
                            <div class="grow faq-flex">
                                <h5>Znalostní báze</h5>
                                <p>Přes {{ $articles->total() }} článků k tématům hosting, domény, e-mail a správa serveru.</p>
                            </div>
                            <i data-feather="aperture"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-span-4 xl:col-span-12">
                <div class="card bg-primary">
                    <div class="card-body">
                        <div class="flex faq-widgets">
                            <div class="grow faq-flex">
                                <h5>Podpora</h5>
                                <p>Nenašli jste odpověď? Naši technici jsou vám k dispozici přes ticket systém nebo e-mail.</p>
                            </div>
                            <i data-feather="file-text"></i>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Browse articles --}}
            <div class="col-span-12">
                <div class="header-faq">
                    <h5 class="mb-0">Procházet články dle kategorie</h5>
                </div>
                <div class="grid grid-cols-12">
                    <div class="col-span-12">
                        <div class="card">
                            <div class="card-header">
                                <h5>Články</h5>
                            </div>
                            <div class="card-body">
                                @if($articles->isEmpty())
                                    <div class="text-center py-5">
                                        <i data-feather="book-open" style="width:40px;height:40px;" class="text-muted block mx-auto mb-3"></i>
                                        <h5 class="f-light">Žádné články k dispozici</h5>
                                    </div>
                                @else
                                <div class="grid grid-cols-12 gap-3 browse">
                                    @foreach($grouped as $cat => $catArticles)
                                    <div class="col-span-4 xl:col-span-6 md:col-span-12 xl-50">
                                        <div class="browse-articles {{ !$loop->first ? 'browse-bottom' : '' }}">
                                            <h6>
                                                <span><i data-feather="archive"></i></span>
                                                {{ $cat ?: 'Obecné' }}
                                            </h6>
                                            <ul>
                                                @foreach($catArticles->take(5) as $article)
                                                <li>
                                                    <a href="{{ route('panel.kb.show', $article->slug) }}">
                                                        <span><i data-feather="file-text"></i></span>
                                                        <span>{{ Str::limit($article->title, 52) }}</span>
                                                    </a>
                                                </li>
                                                @endforeach
                                                @if($catArticles->count() > 5)
                                                <li>
                                                    <a href="{{ route('panel.kb.index') }}?cat={{ urlencode($cat) }}">
                                                        <span><i data-feather="arrow-right"></i></span>
                                                        <span>Zobrazit vše ({{ $catArticles->count() }})</span>
                                                    </a>
                                                </li>
                                                @endif
                                            </ul>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Podpora --}}
            <div class="col-span-12">
                <div class="header-faq">
                    <h5>Potřebujete další pomoc?</h5>
                </div>
                <div class="card">
                    <div class="card-body text-center py-4">
                        <i data-feather="headphones" style="width:40px;height:40px;" class="text-muted block mx-auto mb-3"></i>
                        <h5 class="f-light mb-2">Kontaktujte naši podporu</h5>
                        <p class="f-light f-13 mb-3">Pokud jste odpověď v bázi nenašli, naši technici vám pomohou.</p>
                        <a href="{{ route('panel.support.index') }}" class="btn btn-primary text-white">
                            <i data-feather="message-square" style="width:14px;height:14px;"></i>
                            Otevřít ticket
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection

@php use Illuminate\Support\Str; @endphp
