@props(['href', 'icon', 'label', 'badge' => null, 'badgeColor' => 'primary'])

@php
    $current = url()->current();
    $path    = parse_url($href, PHP_URL_PATH);
    $isActive = rtrim($current, '/') === rtrim($href, '/') ||
                ($path !== '/' && str_starts_with(rtrim($current, '/'), rtrim($path, '/')));
@endphp

<li class="{{ $isActive ? 'active' : '' }}">
    <a href="{{ $href }}" class="{{ $isActive ? 'active' : '' }}">
        @if($icon)
        <i data-feather="{{ $icon }}"></i>
        @endif
        <span>{{ $label }}</span>
        @if($badge !== null)
            <span class="badge badge-{{ $badgeColor }} rounded-full pull-right text-white">{{ $badge }}</span>
        @endif
    </a>
</li>
