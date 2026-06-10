@props(['money'])
{{-- Brick\Money\Money formatted for current locale --}}
<span class="font-mono">{{ \App\Domains\Shared\Support\MoneyFormatter::format($money) }}</span>
