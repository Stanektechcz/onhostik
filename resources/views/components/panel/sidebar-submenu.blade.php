@props(['icon', 'label', 'active' => false])

@php
    $isActive = $active || (isset($__env) && str_starts_with(request()->path(), ltrim(preg_replace('#\?.*#', '', $slot ?? ''), '/')));
@endphp

<li class="sidebar-list">
    <i class="fa-solid fa-thumbtack"></i>
    <a class="sidebar-link sidebar-title {{ $isActive ? 'active' : '' }}" href="#">
        <i data-feather="{{ $icon }}" style="margin-right:0;"></i>
        <span>{{ $label }}</span>
    </a>
    <ul class="sidebar-submenu">
        {{ $slot }}
    </ul>
</li>
