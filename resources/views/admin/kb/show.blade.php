@extends('layouts.panel')

@php
    $breadcrumbTitle = Str::limit($article->title, 40);
    $breadcrumbItems = ['Znalostní báze' => route('admin.kb.index'), $breadcrumbTitle => ''];
    use Illuminate\Support\Str;
@endphp

@section('title', $article->title . ' | Znalostní báze')

@push('styles')
<style>
/* Force left-filter sidebar visible */
.job-sidebar .left-filter,
.job-sidebar .filter-cards-view,
.job-sidebar .accordion-collapse { visibility: visible !important; height: auto !important; overflow: visible !important; }
.browse-articles-nav ul { list-style: none; padding: 0; margin: 0; }
.browse-articles-nav ul li a { display: flex; align-items: center; gap: 8px; padding: 6px 0; color: var(--body-font-color); font-size: 13px; }
.browse-articles-nav ul li a:hover { color: rgba(var(--theme-default),1); }
.browse-articles-nav ul li.active a { color: rgba(var(--theme-default),1); font-weight: 600; }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="container job-details-wrapper">
        <div class="grid grid-cols-12 gap-3">

            {{-- ── Left sidebar: KB navigation (col-span-3) ─────────── --}}
            <div class="col-span-3 xl:col-span-12 xl-40 box-col-12">
                <div class="md-sidebar">
                    <a class="btn btn-primary email-aside-toggle text-white md-sidebar-toggle hover:text-white">
                        Navigace KB
                    </a>
                    <div class="md-sidebar-aside job-sidebar custom-scrollbar">
                        <div class="default-according style-1 faq-accordion job-accordion">
                            <div id="accordionKb">

                                {{-- Category accordion groups --}}
                                @foreach($allCategories as $cat => $catArticles)
                                @php $collapseId = 'kbCat' . Str::slug($cat ?: 'uncategorized'); @endphp
                                <div class="card accordion">
                                    <div class="card-header accordion-item" id="head{{ $collapseId }}">
                                        <h2 class="accordion-header relative">
                                            <button class="accordion-button btn btn-link btn-block text-start {{ $cat !== $article->category ? 'collapsed' : '' }}"
                                                    type="button" data-bs-toggle="collapse"
                                                    data-bs-target="#{{ $collapseId }}"
                                                    aria-expanded="{{ $cat === $article->category ? 'true' : 'false' }}">
                                                <i data-feather="folder" style="width:14px;height:14px;margin-right:6px;"></i>
                                                {{ $cat ?: 'Bez kategorie' }}
                                                <span class="badge badge-light-primary ms-1">{{ $catArticles->count() }}</span>
                                            </button>
                                        </h2>
                                        <div class="accordion-collapse collapse {{ $cat === $article->category ? 'show' : '' }}"
                                             id="{{ $collapseId }}" data-bs-parent="#accordionKb">
                                            <div class="card-body browse-articles-nav">
                                                <ul>
                                                    @foreach($catArticles as $a)
                                                    <li class="{{ $a->id === $article->id ? 'active' : '' }}">
                                                        <a href="{{ route('admin.kb.show', $a) }}">
                                                            <i data-feather="{{ $a->id === $article->id ? 'book-open' : 'file-text' }}" style="width:13px;height:13px;flex-shrink:0;"></i>
                                                            {{ Str::limit($a->title, 38) }}
                                                            @if(!$a->is_published)
                                                                <span class="badge badge-light-warning ms-auto" style="font-size:9px;">draft</span>
                                                            @endif
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

            {{-- ── Right: Article content (col-span-9) ──────────────── --}}
            <div class="col-span-9 xl:col-span-12 xl-80 box-col-12">
                <div class="card">
                    <div class="job-search">
                        <div class="card-body">

                            {{-- Article header --}}
                            <div class="job-flex mb-3">
                                <div style="width:48px;height:48px;background:linear-gradient(135deg,rgba(var(--theme-default),.15),rgba(var(--theme-default),.03));border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-right:16px;">
                                    <i data-feather="book-open" style="width:24px;height:24px;color:rgba(var(--theme-default),1);"></i>
                                </div>
                                <div class="grow">
                                    <h6>
                                        {{ $article->title }}
                                        <span class="pull-right d-flex gap-2">
                                            @if($article->is_published)
                                                <span class="badge badge-light-success">Publikován</span>
                                            @else
                                                <span class="badge badge-light-warning">Skrytý</span>
                                            @endif
                                            <a class="hover:text-white text-white btn btn-primary btn-sm py-1"
                                               href="{{ route('admin.kb.edit', $article) }}">
                                                <i data-feather="edit-2" style="width:12px;height:12px;"></i> Upravit
                                            </a>
                                        </span>
                                    </h6>
                                    <p class="!mt-0">
                                        @if($article->category)
                                            <span class="badge badge-light-secondary me-1">{{ $article->category }}</span>
                                        @endif
                                        <span class="f-light f-12">Pořadí: {{ $article->sort_order }}</span>
                                        @if($article->updated_at)
                                            <span class="f-light f-12 ms-2">Aktualizováno: {{ $article->updated_at->format('d.m.Y H:i') }}</span>
                                        @endif
                                    </p>
                                </div>
                            </div>

                            {{-- Excerpt --}}
                            @if($article->excerpt)
                            <div class="job-description">
                                <h6>Perex</h6>
                                <p class="c-o-light">{{ $article->excerpt }}</p>
                            </div>
                            @endif

                            {{-- Body content --}}
                            <div class="job-description">
                                <h6>Obsah článku</h6>
                                @if($article->body)
                                    <div class="kb-article-body">
                                        {!! $article->body !!}
                                    </div>
                                @else
                                    <p class="c-o-light f-light">Obsah článku je prázdný. Klikněte na "Upravit" pro doplnění obsahu.</p>
                                @endif
                            </div>

                            {{-- Actions --}}
                            <div class="job-description d-flex gap-3 flex-wrap">
                                <a href="{{ route('admin.kb.edit', $article) }}" class="btn btn-primary text-white">
                                    <i data-feather="edit-2" style="width:14px;height:14px;"></i> Upravit článek
                                </a>
                                <a href="{{ route('front.kb.show', $article->slug) }}" target="_blank" class="btn btn-outline-secondary">
                                    <i data-feather="external-link" style="width:14px;height:14px;"></i> Zobrazit na webu
                                </a>
                                <form method="POST" action="{{ route('admin.kb.destroy', $article) }}"
                                      onsubmit="return confirm('Opravdu smazat tento článek?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger">
                                        <i data-feather="trash-2" style="width:14px;height:14px;"></i> Smazat
                                    </button>
                                </form>
                            </div>

                        </div>
                    </div>
                </div>

                {{-- Voting stats + reviews --}}
                <div class="card">
                    <div class="card-header card-no-border">
                        <div class="header-top">
                            <h5>Hodnocení a komentáře</h5>
                            <div class="card-header-right-icon">
                                @php $totalVotes = ($voteStats['helpful'] ?? 0) + ($voteStats['not_helpful'] ?? 0); @endphp
                                <span class="badge badge-light-primary">{{ $totalVotes }} hlasů</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        {{-- Stats --}}
                        <div class="d-flex gap-4 mb-4">
                            <div class="text-center">
                                <h4 class="txt-success mb-0">{{ $voteStats['helpful'] ?? 0 }}</h4>
                                <small class="f-light">Užitečný</small>
                            </div>
                            <div class="text-center">
                                <h4 class="txt-danger mb-0">{{ $voteStats['not_helpful'] ?? 0 }}</h4>
                                <small class="f-light">Neužitečný</small>
                            </div>
                            @if($totalVotes > 0)
                            <div class="flex-1">
                                <div class="progress mt-2" style="height:8px;">
                                    <div class="progress-bar bg-success"
                                         style="width:{{ round(($voteStats['helpful'] / $totalVotes) * 100) }}%">
                                    </div>
                                </div>
                                <small class="f-light">{{ round(($voteStats['helpful'] / $totalVotes) * 100) }}% pozitivních</small>
                            </div>
                            @endif
                        </div>

                        {{-- Reviews --}}
                        @if(($reviews ?? collect())->isEmpty())
                            <p class="f-light f-13 text-center py-3">Žádné komentáře zatím.</p>
                        @else
                            <div class="recent-table overflow-x-auto custom-scrollbar">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th><span class="f-light font-semibold">Autor</span></th>
                                            <th><span class="f-light font-semibold">Komentář</span></th>
                                            <th><span class="f-light font-semibold">Datum</span></th>
                                            <th><span class="f-light font-semibold">Stav</span></th>
                                            <th><span class="f-light font-semibold">Akce</span></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($reviews as $review)
                                        <tr class="inbox-data">
                                            <td class="f-w-500">{{ $review->author_name ?? 'Anonymní' }}</td>
                                            <td class="f-13">{{ Str::limit($review->content, 80) }}</td>
                                            <td class="f-12 f-light">{{ $review->created_at?->format('d.m.Y H:i') }}</td>
                                            <td>
                                                @if($review->is_visible)
                                                    <span class="badge badge-light-success">Schváleno</span>
                                                @else
                                                    <span class="badge badge-light-warning">Čeká</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="common-align gap-2 justify-start">
                                                    @if(!$review->is_visible)
                                                    <form method="POST" action="{{ route('admin.kb.review.approve', $review) }}" style="display:inline;">
                                                        @csrf
                                                        <button type="submit" class="square-white" title="Schválit">
                                                            <i data-feather="check" style="width:14px;height:14px;color:rgba(var(--success-color),1);"></i>
                                                        </button>
                                                    </form>
                                                    @endif
                                                    <form method="POST" action="{{ route('admin.kb.review.delete', $review) }}"
                                                          style="display:inline;" onsubmit="return confirm('Smazat komentář?')">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="square-white trash-3" title="Smazat">
                                                            <svg><use href="{{ asset('panel/svg/icon-sprite.svg#trash1') }}"></use></svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Related articles --}}
                @if($related->isNotEmpty())
                <div class="header-faq">
                    <h5 class="mb-0 font-semibold">Podobné články ve stejné kategorii</h5>
                </div>
                <div class="grid grid-cols-12 card-gap">
                    @foreach($related->take(2) as $rel)
                    <div class="col-span-6 xl:col-span-12 xl-100">
                        <div class="card">
                            <div class="job-search">
                                <div class="card-body">
                                    <div class="job-flex">
                                        <div style="width:40px;height:40px;background:linear-gradient(135deg,rgba(var(--theme-default),.1),rgba(var(--theme-default),.03));border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-right:12px;">
                                            <i data-feather="file-text" style="width:18px;height:18px;opacity:.5;"></i>
                                        </div>
                                        <div class="grow">
                                            <h6>
                                                <a href="{{ route('admin.kb.show', $rel) }}">{{ $rel->title }}</a>
                                                <span class="pull-right">
                                                    <a class="btn btn-outline-primary btn-sm py-1" href="{{ route('admin.kb.show', $rel) }}">Zobrazit</a>
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
