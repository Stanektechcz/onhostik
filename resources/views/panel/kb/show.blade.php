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
            <div class="col-span-3 xl:col-span-12 xl-40 box-col-span-12">
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
                                            <button class="accordion-button btn btn-link btn-block text-left {{ $cat !== $article->category ? 'collapsed' : '' }}"
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
            <div class="col-span-9 xl:col-span-12 xl-80 box-col-span-12">
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

                            {{-- ── Voting section ──────────────────────── --}}
                            <div class="job-description">
                                <div class="border rounded p-3" style="background:rgba(var(--light-background),.4);">
                                    <h6 class="mb-2">Byl tento článek užitečný?</h6>
                                    <div class="flex items-center gap-3">
                                        <button type="button" id="vote-helpful"
                                                class="btn {{ ($userVote ?? '') === 'helpful' ? 'btn-success' : 'btn-outline-success' }} btn-sm"
                                                data-call="castVote" data-call-args='[true]'>
                                            <i data-feather="thumbs-up" style="width:14px;height:14px;"></i>
                                            Ano <span class="ms-1 badge bg-white text-success" id="helpful-count">{{ $voteStats['helpful'] }}</span>
                                        </button>
                                        <button type="button" id="vote-not-helpful"
                                                class="btn {{ ($userVote ?? '') === 'not_helpful' ? 'btn-danger' : 'btn-outline-danger' }} btn-sm"
                                                data-call="castVote" data-call-args='[false]'>
                                            <i data-feather="thumbs-down" style="width:14px;height:14px;"></i>
                                            Ne <span class="ms-1 badge bg-white text-danger" id="not-helpful-count">{{ $voteStats['not_helpful'] }}</span>
                                        </button>
                                        @php $total = $voteStats['helpful'] + $voteStats['not_helpful']; @endphp
                                        @if($total > 0)
                                        <span class="f-light f-12">
                                            {{ $total }} hodnocení,
                                            {{ $total > 0 ? round($voteStats['helpful'] / $total * 100) : 0 }}% považuje za užitečné
                                        </span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            {{-- ── Review form ──────────────────────────── --}}
                            <div class="job-description">
                                <h6 class="mb-3">Napsat komentář k článku</h6>
                                <form method="POST" action="{{ route('panel.kb.review', $article->slug) }}" class="custom-input">
                                    @csrf
                                    @if(!auth()->check())
                                    <div class="mb-3">
                                        <label class="form-label">Vaše jméno</label>
                                        <input type="text" class="form-control" name="author_name" placeholder="Jak se jmenujete?" maxlength="100">
                                    </div>
                                    @endif
                                    <div class="mb-3">
                                        <label class="form-label">Komentář *</label>
                                        <textarea class="form-control @error('content') is-invalid @enderror"
                                                  name="content" rows="3" required minlength="10" maxlength="1000"
                                                  placeholder="Sdílejte svůj pohled, zkušenost nebo doplňující informaci k článku…"></textarea>
                                        @error('content')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <div class="form-text f-11 f-light">Komentář bude zveřejněn po schválení administrátorem.</div>
                                    </div>
                                    <button type="submit" class="btn btn-primary text-white btn-sm">
                                        <i data-feather="send" style="width:13px;height:13px;"></i>
                                        Odeslat komentář
                                    </button>
                                </form>

                                {{-- Approved reviews --}}
                                @if($reviews->isNotEmpty())
                                <div class="mt-4">
                                    <h6 class="mb-3">Komentáře ({{ $reviews->count() }})</h6>
                                    @foreach($reviews as $review)
                                    <div class="flex gap-3 py-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                                        <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,rgba(var(--theme-default),.15),rgba(var(--theme-default),.03));display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                            <span class="f-w-600 f-12" style="color:rgba(var(--theme-default),1);">
                                                {{ strtoupper(substr($review->author_name ?? 'A', 0, 2)) }}
                                            </span>
                                        </div>
                                        <div class="flex-1">
                                            <div class="flex justify-between">
                                                <span class="f-w-500 f-13">{{ $review->author_name ?? 'Anonymní' }}</span>
                                                <span class="f-light f-11">{{ $review->created_at?->format('d.m.Y') }}</span>
                                            </div>
                                            <p class="f-13 mb-0 mt-1">{{ $review->content }}</p>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                                @endif
                            </div>

                            {{-- Actions --}}
                            <div class="job-description flex gap-3 flex-wrap mt-2">
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

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
var _voteUrl = '{{ route('panel.kb.vote', $article->slug) }}';
var _csrfToken = '{{ csrf_token() }}';

function castVote(helpful) {
    fetch(_voteUrl, {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': _csrfToken, 'Accept': 'application/json'},
        body: JSON.stringify({helpful: helpful})
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            document.getElementById('helpful-count').textContent = data.helpful;
            document.getElementById('not-helpful-count').textContent = data.not_helpful;
            var hBtn = document.getElementById('vote-helpful');
            var nBtn = document.getElementById('vote-not-helpful');
            if (data.user_vote === 'helpful') {
                hBtn.className = hBtn.className.replace('btn-outline-success', 'btn-success');
                nBtn.className = nBtn.className.replace('btn-danger', 'btn-outline-danger');
            } else {
                nBtn.className = nBtn.className.replace('btn-outline-danger', 'btn-danger');
                hBtn.className = hBtn.className.replace('btn-success', 'btn-outline-success');
            }
        }
    })
    .catch(function() {});
}
</script>
@endpush
