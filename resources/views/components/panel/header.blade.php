<div class="page-header">
    <div class="header-wrapper grid grid-cols-12 m-0">
        <div class="header-logo-wrapper hidden col-auto p-0 lg:block">
            <div class="logo-wrapper">
                <a href="{{ route('panel.dashboard') }}">
                    <img class="max-w-full h-auto for-light" src="{{ asset('panel/images/logo/logo-onhost.svg') }}" alt="Onhost.cz" height="36">
                    <img class="max-w-full h-auto for-dark" src="{{ asset('panel/images/logo/logo-onhost-white.svg') }}" alt="Onhost.cz" height="36">
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

                {{-- Locale switcher — Cuba translate_wrapper style --}}
                <li class="language-nav">
                    <div class="translate_wrapper relative">
                        <input class="peer hidden" id="lang-toggle-panel" type="checkbox">
                        <label class="current_lang flex items-center cursor-pointer !mb-0" for="lang-toggle-panel">
                            <span class="lang flex items-center gap-1">
                                <i class="flag-icon flag-icon-{{ app()->getLocale() === 'cs' ? 'cz' : 'us' }}" style="font-size:14px;"></i>
                                <span class="lang-txt">{{ strtoupper(app()->getLocale()) }}</span>
                            </span>
                        </label>
                        <div class="more_lang custom-scrollbar absolute top-full mt-2 bg-white rounded-md shadow-md w-36 z-50 hidden peer-checked:block" style="right:0;">
                            @foreach(['cs' => ['cz','Čeština'], 'en' => ['us','English']] as $loc => [$flag, $label])
                            <div class="lang {{ app()->getLocale() === $loc ? 'selected' : '' }}">
                                <a href="{{ route('locale.switch', $loc) }}" class="flex items-center cursor-pointer px-3 py-2 gap-2 text-sm hover:bg-gray-50">
                                    <i class="flag-icon flag-icon-{{ $flag }}"></i>
                                    <span class="lang-txt">{{ $label }}</span>
                                    @if(app()->getLocale() === $loc)
                                        <i data-feather="check" style="width:12px;height:12px;margin-left:auto;color:#54ba4a;"></i>
                                    @endif
                                </a>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </li>

                {{-- Notification bell --}}
                <li class="onhover-dropdown" id="notif-bell-li">
                    <div class="notification-box" id="notif-bell" style="cursor:pointer;position:relative;">
                        <i data-feather="bell" style="width:20px;height:20px;"></i>
                        <span class="badge badge-danger rounded-circle" id="notif-count" style="display:none;font-size:9px;min-width:16px;height:16px;line-height:16px;padding:0 3px;position:absolute;top:-6px;right:-6px;"></span>
                    </div>
                    <ul class="notification-dropdown onhover-show-div" style="width:360px;max-height:480px;overflow-y:auto;">
                        <li>
                            <h6 class="f-18 mb-0 dropdown-title">Notifikace</h6>
                            <span class="f-light f-12 float-end" id="notif-unread-label"></span>
                        </li>
                        <li id="notif-items-container">
                            <div class="text-center py-3 f-light f-12">Načítání…</div>
                        </li>
                        <li class="text-center d-flex gap-2 justify-content-center py-1">
                            <button class="btn btn-primary btn-xs text-white" id="notif-mark-all-btn" onclick="markAllNotifRead()">
                                Označit vše přečtené
                            </button>
                            <a href="{{ route('panel.notifications.index') }}" class="btn btn-outline-primary btn-xs">
                                Všechny notifikace
                            </a>
                        </li>
                    </ul>
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
