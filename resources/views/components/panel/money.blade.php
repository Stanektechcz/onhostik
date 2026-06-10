@props(['money'])
{{-- Brick\Money\Money formatted for current locale --}}
<span class="font-mono">{{ $money?->formatTo(app()->getLocale()) ?? '—' }}</span>
