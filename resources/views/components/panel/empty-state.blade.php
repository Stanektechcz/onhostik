@props([
    'icon'     => 'inbox',
    'title'    => 'Žádná data',
    'subtitle' => null,
])

{{-- Cuba-styled empty state. Optional action(s) go in the default slot. --}}
<div {{ $attributes->merge(['class' => 'text-center py-5']) }}>
    <i data-feather="{{ $icon }}" class="empty-state-icon"></i>
    <h6 class="f-light mt-3 mb-1">{{ $title }}</h6>
    @if($subtitle)
        <p class="f-light f-12 mb-0">{{ $subtitle }}</p>
    @endif
    @if(trim($slot) !== '')
        <div class="mt-3 flex items-center justify-center gap-2">
            {{ $slot }}
        </div>
    @endif
</div>
