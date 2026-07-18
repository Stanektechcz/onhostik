@props([
    'active'   => null,
    'upcoming' => null,
    'style'    => 'front', // 'front' | 'admin'
])

@if($active || $upcoming)
@php $maint = $active ?? $upcoming; $isActive = (bool) $active; @endphp

@if($style === 'admin')
<div class="container-fluid py-0 maintenance-banner">
    <div class="alert alert-{{ $maint->color }} mb-0 py-2 flex items-center gap-3" role="alert"
         style="border-radius:0;border-left:0;border-right:0;border-top:0;">
        <i data-feather="{{ $isActive ? 'tool' : 'clock' }}" style="width:16px;height:16px;flex-shrink:0;"></i>
        <span class="f-13 grow">
            <strong>{{ $maint->title }}</strong>
            @if(!$isActive)
                &nbsp;<span class="badge badge-light-{{ $maint->color }}">Plánovaná od {{ $maint->starts_at->format('d.m.Y H:i') }}</span>
            @endif
            &mdash; {{ $maint->message }}
        </span>
        @if($isActive)
        <span class="f-11 shrink-0 f-light">do {{ $maint->ends_at->format('d.m.Y H:i') }}</span>
        @endif
    </div>
</div>
@else
<div class="maintenance-banner"
     style="background:{{ $maint->color === 'danger' ? '#dc3545' : ($maint->color === 'info' ? '#0dcaf0' : ($maint->color === 'primary' ? '#0d6efd' : '#ffc107')) }};
            color: {{ $maint->color === 'warning' ? '#000' : '#fff' }};
            padding: 10px 20px; text-align:center; font-size:14px; font-weight:500;">
    <i class="fa fa-{{ $isActive ? 'wrench' : 'clock' }}" style="margin-right:6px;"></i>
    <strong>{{ $maint->title }}</strong>
    @if(!$isActive)
        &nbsp;&mdash; Plánovaná od {{ $maint->starts_at->format('d.m.Y H:i') }}
    @endif
    &mdash; {{ $maint->message }}
    @if($isActive)
        &nbsp;<small>(do {{ $maint->ends_at->format('d.m.Y H:i') }})</small>
    @endif
</div>
@endif
@endif
