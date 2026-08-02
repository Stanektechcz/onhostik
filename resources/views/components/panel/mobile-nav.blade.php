{{-- Mobile bottom navigation — the app-like surface for phones (PWA).
     Hidden on tablets and up, where the Cuba sidebar already does this job.
     Only rendered for accounts that actually have a customer profile. --}}
@auth
@php
    $navUser     = auth()->user();
    $hasCustomer = $navUser?->customer !== null;
    $is          = fn (string $name) => request()->routeIs($name . '*');
    $items = [
        ['route' => 'panel.dashboard',        'icon' => 'home',           'label' => 'Přehled'],
        ['route' => 'panel.services.index',   'icon' => 'server',         'label' => 'Služby'],
        ['route' => 'panel.support.index',    'icon' => 'message-square', 'label' => 'Podpora'],
        ['route' => 'panel.account.profile',  'icon' => 'user',           'label' => 'Účet'],
    ];
@endphp

@if($hasCustomer)
<nav class="onhost-mobile-nav" aria-label="Hlavní navigace">
    @foreach ($items as $item)
        <a href="{{ route($item['route']) }}"
           class="onhost-mobile-nav__item {{ $is($item['route']) ? 'is-active' : '' }}"
           @if($is($item['route'])) aria-current="page" @endif>
            <i data-feather="{{ $item['icon'] }}" aria-hidden="true"></i>
            <span>{{ $item['label'] }}</span>
        </a>
    @endforeach
</nav>
@endif
@endauth
