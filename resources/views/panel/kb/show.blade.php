@extends('layouts.panel')

@php
    use Illuminate\Support\Str;
    $breadcrumbTitle = Str::limit($article->title, 40);
    $breadcrumbItems = ['Znalostní báze' => route('panel.kb.index'), $breadcrumbTitle => ''];
@endphp

@section('title', $article->title . ' | Znalostní báze')

@push('styles')
<style>
.kb-nav ul { list-style: none; padding: 0; margin: 0; }
.kb-nav ul li a { display: flex; align-items: center; gap: 8px; padding: 7px 0; color: var(--body-font-color); font-size: 13px; border-bottom: 1px solid rgba(var(--light-background),.5); }
.kb-nav ul li a:hover { color: rgba(var(--theme-default),1); }
.kb-nav ul li.active a { color: rgba(var(--theme-default),1); font-weight: 600; }
.accordion-collapse { visibility: visible !important; height: auto !important; overflow: visible !important; }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="container job-details-wrapper">
        <div class="grid grid-cols-12 gap-3">

            {{-- Left sidebar: KB navigation --}}
            <div class="col-span-3 xl:col-span-12 xl-40 box-col-12">
                <div class="md-sidebar">
                    <a class="btn btn-primary email-aside-toggle text-white md-sidebar-toggle hover:text-white">Navigace KB</a>
                    <div class="md-sidebar-aside job-sidebar custom-scrollbar">
                        <div class="default-according style-1 faq-accordion job-accordion">
                            <div id="accordionKb">

                                @foreach($allCategories as $cat => $catArticles)
                                @php $cid = 'pKb' . Str::slug($cat ?: 'other'); @endphp
                                <div class="card accordion">
                                    <div class="card-header accordion-item">
                                        <h2 class="accordion-header relative">
                                            <button class="accordion-button btn btn-link btn-block text-start {{ $cat !== $article->category ? 'collapsed' : '' }}"
                                                    type="button" data-bs-toggle="collapse"
                                                    data-bs-target="#{{ $cid }}"
                                                    aria-expanded="{{ $cat === $article->category ? 'true' : 'false' }}">
                                                <i data-feather="folder" style="width:13px;height:13px;margin-right:6px;"></i>
                                                {{ $cat ?: 'Obecné' }}
                                            </button>
                                        </h2>
                                        <div class="accordion-collapse collapse {{ $cat === $article->category ? 'show' : '' }}"
                                             id="{{ $cid }}">
                                            <div class="card-body kb-nav py-0">
                                                <ul>
                                                    @foreach($catArticles as $a)
                                                    <li class="{{ $a->id === $article->id ? 'active' : '' }}">
                                                        <a href="{{ route('panel.kb.show', $a->slug) }}">
                                                            <i data-feather="{{ $a->id === $article->id ? 'book-open' : 'file-text' }}" style="width:12px;height:12px;flex-shrink:0;"></i>
                                                            {{ Str::limit($a->title, 38) }}
                                                        </a>
                                                    </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endforeach

                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right: Article content --}}
            <div class="col-span-9 xl:col-span-12 xl-80 box-col-12">
                <div class="card">
                    <div class="job-search">
                        <div class="card-body">

                            {{-- Header --}}
                            <div class="job-flex mb-3">
                                <div style="width:48px;height:48px;background:linear-gradient(135deg,rgba(var(--theme-default),.12),rgba(var(--theme-default),.03));border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-right:16px;">
                                    <i data-feather="book-open" style="width:24px;height:24px;color:rgba(var(--theme-default),1);"></i>
                                </div>
                                <div class="grow">
                                    <h6>{{ $article->title }}</h6>
                                    <p class="!mt-0">
                                        @if($article->category)
                                            <span class="badge badge-light-secondary me-1">{{ $article->category }}</span>
                                        @endif
                                        @if($article->updated_at)
                                            <span class="f-light f-12">Aktualizováno: {{ $article->updated_at->format('d.m.Y') }}</span>
                                        @endif
                                    </p>
                                </div>
                            </div>

                            {{-- Excerpt --}}
                            @if($article->excerpt)
                            <div class="job-description">
                                <p class="c-o-light f-15">{{ $article->excerpt }}</p>
                            </div>
                            @endif

                            {{-- Body --}}
                            <div class="job-description">
                                @if($article->body)
                                    <div class="kb-article-body">{!! $article->body !!}</div>
                                @else
                                    <p class="c-o-light f-light">Obsah článku není k dispozici.</p>
                                @endif
                            </div>

                            {{-- Actions --}}
                            <div class="job-description d-flex gap-3 flex-wrap mt-2">
                                <a href="{{ route('panel.kb.index') }}" class="btn btn-hover-effect">
                                    <span><i class="fa-solid fa-caret-left fa-lg"></i></span> Zpět na znalostní bázi
                                </a>
                                <a href="{{ route('panel.support.index') }}" class="btn btn-outline-primary">
                                    <i data-feather="message-square" style="width:14px;height:14px;"></i> Otevřít ticket
                                </a>
                            </div>

                        </div>
                    </div>
                </div>

                {{-- Related articles --}}
                @if($related->isNotEmpty())
                <div class="header-faq">
                    <h5 class="mb-0 font-semibold">Podobné články</h5>
                </div>
                <div class="grid grid-cols-12 card-gap">
                    @foreach($related->take(2) as $rel)
                    <div class="col-span-6 xl:col-span-12 xl-100">
                        <div class="card">
                            <div class="job-search">
                                <div class="card-body">
                                    <div class="job-flex">
                                        <div style="width:40px;height:40px;background:linear-gradient(135deg,rgba(var(--theme-default),.08),rgba(var(--theme-default),.02));border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-right:12px;">
                                            <i data-feather="file-text" style="width:18px;height:18px;opacity:.4;"></i>
                                        </div>
                                        <div class="grow">
                                            <h6>
                                                <a href="{{ route('panel.kb.show', $rel->slug) }}">{{ $rel->title }}</a>
                                                <span class="pull-right">
                                                    <a class="btn btn-outline-primary btn-sm py-1" href="{{ route('panel.kb.show', $rel->slug) }}">Přečíst</a>
                                                </span>
                                            </h6>
                                            <p class="!mt-0 c-o-light f-12">{{ Str::limit($rel->excerpt, 100) }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif

            </div>
        </div>
    </div>
</div>
@endsection
