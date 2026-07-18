@extends('layouts.panel')

@php($breadcrumbTitle = 'Analytika znalostní báze')
@php($breadcrumbItems = ['Znalostní báze' => route('admin.kb.index'), 'Analytika' => ''])

@section('title', 'KB Analytika')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 card-gap">
        <div class="col-span-4 md:col-span-6 sm:col-span-12">
            <x-panel.stat-widget
                label="Celkový počet zobrazení"
                :value="number_format($totalViews)"
                icon="eye" color="primary" />
        </div>
        <div class="col-span-4 md:col-span-6 sm:col-span-12">
            <x-panel.stat-widget
                label="Sledovaných článků"
                :value="$topArticles->count()"
                icon="book-open" color="info" />
        </div>
        <div class="col-span-4 md:col-span-6 sm:col-span-12">
            <x-panel.stat-widget
                label="Průměr zobrazení"
                :value="$topArticles->count() > 0 ? number_format($totalViews / $topArticles->count(), 1) : 0"
                icon="bar-chart-2" color="secondary" />
        </div>
    </div>

    <x-panel.card title="Nejčtenější články znalostní báze (Top 20)">
        @if($topArticles->isEmpty())
            <div class="text-center py-5">
                <i data-feather="book-open" style="width:36px;height:36px;" class="text-muted mb-2"></i>
                <p class="f-light f-12 mb-0">Zatím žádná zobrazení.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover table-borderless mb-0">
                    <thead>
                        <tr class="border-bottom">
                            <th class="f-12 f-w-600">#</th>
                            <th class="f-12 f-w-600">Článek</th>
                            <th class="f-12 f-w-600">Kategorie</th>
                            <th class="f-12 f-w-600 text-right">Zobrazení</th>
                            <th class="f-12 f-w-600 text-right">Podíl</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($topArticles as $i => $article)
                        <tr>
                            <td class="f-light f-12">{{ $i + 1 }}</td>
                            <td>
                                <span class="f-13 f-w-500">{{ $article->title }}</span>
                            </td>
                            <td>
                                <span class="badge badge-light-secondary f-11">{{ $article->category ?? 'Obecné' }}</span>
                            </td>
                            <td class="text-right">
                                <span class="f-w-600 f-13">{{ number_format($article->views_count) }}</span>
                                {{-- Progress bar --}}
                                @if($topArticles->first()->views_count > 0)
                                <div class="progress mt-1" style="height:4px;min-width:80px;">
                                    <div class="progress-bar bg-primary"
                                         style="width:{{ min(100, round($article->views_count / $topArticles->first()->views_count * 100)) }}%"></div>
                                </div>
                                @endif
                            </td>
                            <td class="text-right f-light f-12">
                                {{ $totalViews > 0 ? round($article->views_count / $totalViews * 100, 1) : 0 }} %
                            </td>
                            <td>
                                <a href="{{ route('admin.kb.show', $article->slug) }}"
                                   class="btn btn-outline-primary btn-sm">Detail</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-panel.card>
</div>
@endsection
