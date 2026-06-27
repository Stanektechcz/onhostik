@extends('layouts.panel')

@php
    use Illuminate\Support\Str;
    $breadcrumbTitle = Str::limit($post->title, 40);
    $breadcrumbItems = ['Blog' => route('panel.blog.index'), $breadcrumbTitle => ''];
@endphp

@section('title', $post->title)

@section('content')
<div class="container-fluid">
    <div class="container">
        <div class="grid grid-cols-12">
            <div class="col-span-12">
                <div class="card">
                    <div class="card-body">
                        <div class="blog-single">
                            <div class="blog-box blog-details">
                                @if($post->featured_image)
                                    <img class="max-w-full h-auto w-full" src="{{ $post->featured_image }}" alt="{{ $post->title }}">
                                @else
                                    <div style="height:280px;background:linear-gradient(135deg,rgba(var(--theme-default),.1),rgba(var(--theme-default),.03));display:flex;align-items:center;justify-content:center;border-radius:8px;margin-bottom:16px;">
                                        <i data-feather="book-open" style="width:64px;height:64px;opacity:.15;"></i>
                                    </div>
                                @endif
                                <div class="blog-details">
                                    <ul class="blog-social">
                                        <li>{{ $post->published_at?->format('d. M Y') }}</li>
                                        @if($post->author)
                                            <li><i class="icofont icofont-user"></i>{{ $post->author->name }}</li>
                                        @endif
                                        @if($post->category)
                                            <li><i class="icofont icofont-tag"></i>{{ $post->category }}</li>
                                        @endif
                                    </ul>
                                    <h5>{{ $post->title }}</h5>
                                </div>
                            </div>

                            @if($post->excerpt)
                            <div class="single-blog-content-top">
                                <p class="f-w-500 f-16">{{ $post->excerpt }}</p>
                                <hr>
                            </div>
                            @endif

                            <div class="single-blog-content-top">
                                @if($post->body)
                                    <div class="blog-content">{!! $post->body !!}</div>
                                @else
                                    <p class="f-light text-center py-4">Obsah příspěvku není k dispozici.</p>
                                @endif
                            </div>

                            <div class="mt-3">
                                <a href="{{ route('panel.blog.index') }}" class="btn btn-hover-effect">
                                    <span><i class="fa-solid fa-caret-left fa-lg"></i></span> Zpět na blog
                                </a>
                            </div>

                            @if($related->isNotEmpty())
                            <section class="!pb-0 comment-box mt-4">
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
                                                        <i data-feather="file-text" style="width:28px;height:28px;opacity:.25;"></i>
                                                    </div>
                                                @endif
                                                <div class="blog-details-main">
                                                    <ul class="blog-social">
                                                        <li>{{ $rel->published_at?->format('d.m.Y') }}</li>
                                                    </ul>
                                                    <hr>
                                                    <h6 class="blog-bottom-details">
                                                        <a href="{{ route('panel.blog.show', $rel->slug) }}">{{ Str::limit($rel->title, 50) }}</a>
                                                    </h6>
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
