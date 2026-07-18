@extends('layouts.panel')

@php
    $breadcrumbTitle = 'Historie aktivit';
    $breadcrumbItems = ['Účet' => '#', 'Historie aktivit' => ''];

    $filters = [
        ''         => 'Vše',
        'invoices' => 'Faktury',
        'payments' => 'Platby',
        'orders'   => 'Objednávky',
        'tickets'  => 'Tikety',
        'services' => 'Služby',
        'credit'   => 'Kredit',
    ];

    $colorMap = [
        'primary'   => ['bg' => '#5c61f2', 'text' => '#fff'],
        'success'   => ['bg' => '#54ba4a', 'text' => '#fff'],
        'danger'    => ['bg' => '#e44141', 'text' => '#fff'],
        'warning'   => ['bg' => '#ffa941', 'text' => '#fff'],
        'info'      => ['bg' => '#15a8fa', 'text' => '#fff'],
        'secondary' => ['bg' => '#757575', 'text' => '#fff'],
        'gray'      => ['bg' => '#aaa', 'text' => '#fff'],
    ];
@endphp

@section('title', 'Historie aktivit')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    {{-- ── Filter bar ─────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap gap-2 mb-4">
        @foreach($filters as $val => $label)
            <a href="{{ route('panel.activity-feed') . ($val ? '?filter=' . $val : '') }}"
               class="btn btn-sm {{ $filter === $val ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @if($events->isEmpty())
        <div class="card">
            <div class="card-body text-center py-5">
                <i data-feather="clock" style="width:40px;height:40px;color:rgba(var(--theme-body-sub-title-color),.5);" class="mb-3"></i>
                <p class="text-muted mb-0">Žádné aktivity k zobrazení.</p>
            </div>
        </div>
    @else
    {{-- ── Timeline ────────────────────────────────────────────────────────── --}}
    <div class="card">
        <div class="card-body">
            <div class="activity-log-list" style="position:relative; padding-left: 0;">

                @php $lastDate = null; @endphp
                @foreach($events as $event)
                    @php
                        $eventDate  = $event['date']?->format('Y-m-d');
                        $color      = $event['color'] ?? 'primary';
                        $bg         = $colorMap[$color]['bg'] ?? '#5c61f2';
                    @endphp

                    @if($eventDate !== $lastDate)
                        @php $lastDate = $eventDate; @endphp
                        <div class="activity-date-divider flex items-center gap-3 my-3">
                            <span class="badge bg-light text-dark border f-12 px-3 py-2">
                                {{ $event['date']?->translatedFormat('j. F Y') ?? '—' }}
                            </span>
                            <div style="flex:1;height:1px;background:rgba(var(--light-semi-gray),1);"></div>
                        </div>
                    @endif

                    <div class="flex items-start gap-3 py-2 activity-item">
                        {{-- Icon dot --}}
                        <div class="shrink-0 flex items-center justify-center rounded-full"
                             style="width:36px;height:36px;background:{{ $bg }};">
                            <i data-feather="{{ $event['icon'] }}" style="width:16px;height:16px;color:rgba(var(--white),1);"></i>
                        </div>

                        {{-- Content --}}
                        <div class="grow" style="min-width:0;">
                            <div class="flex items-baseline gap-2 flex-wrap">
                                @if($event['url'])
                                    <a href="{{ $event['url'] }}" class="font-semibold text-dark f-15 text-decoration-none hover-underline">
                                        {{ $event['title'] }}
                                    </a>
                                @else
                                    <span class="font-semibold f-15">{{ $event['title'] }}</span>
                                @endif

                                @if($event['badge'])
                                    <span class="badge bg-{{ $event['badge_color'] ?? 'secondary' }} f-10">
                                        {{ $event['badge'] }}
                                    </span>
                                @endif
                            </div>

                            @if($event['description'])
                                <div class="f-13 text-muted mt-1">{{ $event['description'] }}</div>
                            @endif
                        </div>

                        {{-- Timestamp --}}
                        <div class="shrink-0 text-right">
                            <span class="f-12 text-muted">{{ $event['date']?->format('H:i') }}</span>
                        </div>
                    </div>
                @endforeach

            </div>
        </div>

        @if($events->hasPages())
        <div class="card-footer">
            {{ $events->links() }}
        </div>
        @endif
    </div>
    @endif

</div>

<style>
.activity-item:not(:last-child) {
    border-bottom: 1px solid #f5f5f5;
}
.hover-underline:hover {
    text-decoration: underline !important;
}
</style>
@endsection
