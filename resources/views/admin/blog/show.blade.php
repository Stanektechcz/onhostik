@extends('layouts.panel')

@php
    $breadcrumbTitle = Str::limit($post->title, 40);
    $breadcrumbItems = ['Blog' => route('admin.blog.index'), $breadcrumbTitle => ''];
    use Illuminate\Support\Str;
@endphp

@section('title', $post->title . ' | Blog Admin')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="container">
        <div class="grid grid-cols-12">
            <div class="col-span-12">
                <div class="card">
                    <div class="card-body">
                        <div class="blog-single">

                            {{-- ── Article header ─────────────────────────── --}}
                            <div class="blog-box blog-details">
                                @if($post->featured_image)
                                    <img class="max-w-full h-auto w-full" src="{{ $post->featured_image }}" alt="{{ $post->title }}">
                                @else
                                    <div style="height:300px;background:linear-gradient(135deg,rgba(var(--theme-default),.1),rgba(var(--theme-default),.03));display:flex;align-items:center;justify-content:center;border-radius:8px;margin-bottom:20px;">
                                        <i data-feather="image" style="width:64px;height:64px;opacity:.2;"></i>
                                    </div>
                                @endif

                                <div class="blog-details">
                                    <ul class="blog-social">
                                        <li>{{ $post->published_at?->format('d. M Y') ?? 'Nepublikováno' }}</li>
                                        @if($post->author)
                                            <li><i class="icofont icofont-user"></i>{{ $post->author->name }}</li>
                                        @endif
                                        @if($post->category)
                                            <li><i class="icofont icofont-tag"></i>{{ $post->category }}</li>
                                        @endif
                                        <li>
                                            @if($post->is_published)
                                                <span class="badge badge-light-success">Publikován</span>
                                            @else
                                                <span class="badge badge-light-warning">Koncept</span>
                                            @endif
                                        </li>
                                    </ul>

                                    <h5>{{ $post->title }}</h5>

                                    {{-- Action buttons --}}
                                    <div class="d-flex gap-2 flex-wrap mt-3">
                                        <a href="{{ route('admin.blog.edit', $post) }}" class="btn btn-primary btn-sm">
                                            <i data-feather="edit-2" style="width:13px;height:13px;"></i> Upravit
                                        </a>
                                        <a href="{{ route('front.blog.show', $post->slug) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                                            <i data-feather="external-link" style="width:13px;height:13px;"></i> Zobrazit na webu
                                        </a>
                                        <form method="POST" action="{{ route('admin.blog.destroy', $post) }}"
                                              onsubmit="return confirm('Opravdu smazat tento příspěvek?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                                <i data-feather="trash-2" style="width:13px;height:13px;"></i> Smazat
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            {{-- ── Excerpt / Perex ─────────────────────────── --}}
                            @if($post->excerpt)
                            <div class="single-blog-content-top">
                                <p class="f-w-500 f-16">{{ $post->excerpt }}</p>
                                <hr>
                            </div>
                            @endif

                            {{-- ── Main content ────────────────────────────── --}}
                            <div class="single-blog-content-top">
                                @if($post->body)
                                    <div class="blog-content">
                                        {!! $post->body !!}
                                    </div>
                                @else
                                    <p class="f-light text-center py-4">Obsah příspěvku je prázdný.</p>
                                @endif
                            </div>

                            {{-- ── Related posts ───────────────────────────── --}}
                            @if($related->isNotEmpty())
                            <section class="!pb-0 comment-box">
                                <h5>Související příspěvky</h5>
                                <hr>
                                <div class="grid grid-cols-12 card-gap">
                                    @foreach($related as $rel)
                                    <div class="col-span-4 xl:col-span-12">
                                        <div class="card mb-0">
                                            <div class="blog-box blog-grid text-center">
                                                @if($rel->featured_image)
                                                    <img class="max-w-full h-auto top-radius-blog" src="{{ $rel->featured_image }}" alt="{{ $rel->title }}">
                                                @else
                                                    <div class="top-radius-blog" style="height:100px;background:linear-gradient(135deg,rgba(var(--theme-default),.08),rgba(var(--theme-default),.02));display:flex;align-items:center;justify-content:center;">
                                                        <i data-feather="file-text" style="width:28px;height:28px;opacity:.3;"></i>
                                                    </div>
                                                @endif
                                                <div class="blog-details-main">
                                                    <ul class="blog-social">
                                                        <li>{{ $rel->published_at?->format('d.m.Y') }}</li>
                                                        <li>{{ $rel->author?->name ?? 'Admin' }}</li>
                                                    </ul>
                                                    <hr>
                                                    <h6 class="blog-bottom-details">{{ Str::limit($rel->title, 50) }}</h6>
                                                    <a href="{{ route('admin.blog.show', $rel) }}" class="btn btn-primary btn-xs mt-1">Zobrazit</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </section>
                            @endif

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
