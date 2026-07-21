@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Co je nového';
    $breadcrumbItems = ['Co je nového' => ''];
@endphp

@section('title', 'Co je nového')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="grid grid-cols-12 card-gap">
        <div class="col-span-12">
            <x-panel.card title="Co je nového v OnHost">
                @if($updates->isEmpty())
                    <x-panel.empty-state
                        icon="gift"
                        title="Zatím žádné novinky"
                        subtitle="Jakmile vydáme něco nového, najdete to tady." />
                @else
                    <ul class="timeline-list list-unstyled mb-0">
                        @foreach($updates as $update)
                            @php
                                // "New" is relative to when THIS user last looked,
                                // not to a global date — otherwise a customer who
                                // reads it daily and one who returns after a month
                                // would see the same badges.
                                $isNew = $seenBefore === null
                                    || $update->published_at?->greaterThan($seenBefore);
                            @endphp
                            <li class="pb-4 mb-4 border-bottom">
                                <div class="flex items-start justify-between gap-3 flex-wrap">
                                    <div class="grow">
                                        <div class="flex items-center gap-2 flex-wrap mb-1">
                                            <span class="badge badge-light-{{ $update->categoryColor() }} f-11">
                                                {{ $update->categoryLabel() }}
                                            </span>

                                            @if($update->version)
                                                <span class="badge badge-light-secondary f-11">{{ $update->version }}</span>
                                            @endif

                                            @if($isNew)
                                                <span class="badge badge-light-primary f-10">Nové</span>
                                            @endif
                                        </div>

                                        <h6 class="mb-1">{{ $update->title }}</h6>
                                        <p class="f-13 f-light mb-0" style="white-space:pre-line;">{{ $update->body }}</p>
                                    </div>

                                    <span class="f-11 f-light text-nowrap">
                                        {{ $update->published_at?->format('d.m.Y') }}
                                    </span>
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-3">
                        {{ $updates->links() }}
                    </div>
                @endif
            </x-panel.card>
        </div>
    </div>
</div>
@endsection
