@props(['href', 'icon', 'label'])
<li class="sidebar-list">
    <i class="fa-solid fa-thumbtack"></i>
    <a class="sidebar-link sidebar-title link-nav {{ url()->current() === $href ? 'active' : '' }}" href="{{ $href }}">
        <i data-feather="{{ $icon }}"></i>
        <span>{{ $label }}</span>
    </a>
</li>
