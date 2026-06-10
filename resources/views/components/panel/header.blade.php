<div class="page-header">
    <div class="header-wrapper grid grid-cols-12 m-0">
        <div class="header-logo-wrapper hidden col-auto p-0 lg:block">
            <div class="logo-wrapper">
                <a href="{{ route('panel.dashboard') }}">
                    <img class="max-w-full h-auto for-light" src="{{ asset('panel/images/logo/logo.png') }}" alt="Onhost.cz">
                    <img class="max-w-full h-auto for-dark" src="{{ asset('panel/images/logo/logo_dark.png') }}" alt="Onhost.cz">
                </a>
            </div>
            <div class="toggle-sidebar"><i class="status_toggle middle sidebar-toggle" data-feather="align-center"></i></div>
        </div>

        <div class="nav-right col-span-12 float-right right-header p-0 ms-auto">
            <ul class="nav-menus">
                {{-- Credit balance quick view --}}
                @if(auth()->user()?->customer)
                    <li>
                        <a href="{{ route('panel.billing.credits') }}" class="credit-badge">
                            <i data-feather="dollar-sign"></i>
                            <span>{{ \App\Domains\Shared\Support\MoneyFormatter::format(app(\App\Domains\Billing\Services\CreditLedger::class)->getBalance(auth()->user()->customer)) }}</span>
                        </a>
                    </li>
                @endif

                {{-- Locale switcher --}}
                <li class="language-nav">
                    <a href="{{ route('locale.switch', app()->getLocale() === 'cs' ? 'en' : 'cs') }}">
                        <span class="lang-txt">{{ strtoupper(app()->getLocale() === 'cs' ? 'EN' : 'CZ') }}</span>
                    </a>
                </li>

                {{-- Dark mode toggle (Cuba built-in) --}}
                <li>
                    <div class="mode">
                        <svg><use href="{{ asset('panel/svg/icon-sprite.svg') }}#moon"></use></svg>
                    </div>
                </li>

                {{-- Profile dropdown --}}
                <li class="profile-nav onhover-dropdown pe-0 py-0">
                    <div class="flex items-center profile-media">
                        <div class="flex-grow-1">
                            <span>{{ auth()->user()?->name }}</span>
                            <p class="mb-0 font-outfit">{{ auth()->user()?->isAdmin() ? 'Admin' : __('panel.nav.customer') }} <i class="middle fa-solid fa-angle-down"></i></p>
                        </div>
                    </div>
                    <ul class="profile-dropdown onhover-show-div">
                        <li><a href="{{ route('panel.account.profile') }}"><i data-feather="user"></i><span>{{ __('panel.nav.profile') }}</span></a></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full text-start"><i data-feather="log-in"></i><span>{{ __('panel.nav.logout') }}</span></button>
                            </form>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</div>
