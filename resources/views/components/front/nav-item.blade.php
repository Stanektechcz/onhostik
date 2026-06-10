@props(['href', 'label', 'badge' => null])
<div class="menu-item">
    <a href="{{ $href }}" class="mergecolor {{ request()->url() === $href ? 'active' : '' }}" title="{{ $label }}">{{ $label }}</a>
    @if($badge)
        <div class="badge bg-purple align-middle horizontalShake">{{ $badge }}</div>
    @endif
</div>
