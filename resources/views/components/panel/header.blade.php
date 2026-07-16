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

        @can('access-admin')
        <script>
        document.addEventListener('DOMContentLoaded', function(){
            const inp = document.getElementById('admin-global-search');
            const box = document.getElementById('admin-search-results');
            if (!inp) return;
            let timer;
            const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

            const typeColors = { customer:'primary', service:'info', invoice:'success', order:'warning', ticket:'secondary' };
            const typeLabels = { customer:'Zákazník', service:'Služba', invoice:'Faktura', order:'Objednávka', ticket:'Tiketa' };

            inp.addEventListener('input', function() {
                clearTimeout(timer);
                const q = this.value.trim();
                if (q.length < 2) { box.style.display='none'; return; }
                timer = setTimeout(() => {
                    fetch(`{{ route('admin.search.quick') }}?q=` + encodeURIComponent(q), {
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (!data.results || data.results.length === 0) {
                            box.innerHTML = '<div class="p-3 text-muted" style="font-size:13px">Žádné výsledky</div>';
                        } else {
                            box.innerHTML = data.results.map(r => `
                                <a href="${r.url}" class="d-flex align-items-center gap-2 px-3 py-2 text-decoration-none text-dark border-bottom" style="font-size:13px">
                                    <i data-feather="${r.icon}" style="width:14px;height:14px;flex-shrink:0;color:#6c757d;"></i>
                                    <div class="flex-1 overflow-hidden">
                                        <div class="fw-semibold text-truncate">${r.title}</div>
                                        <div class="text-muted" style="font-size:11px">${r.subtitle}</div>
                                    </div>
                                    <span class="badge bg-${typeColors[r.type] ?? 'secondary'}" style="font-size:10px;flex-shrink:0">${typeLabels[r.type] ?? r.type}</span>
                                </a>`).join('');
                        }
                        box.style.display = 'block';
                        if (window.feather) feather.replace();
                    });
                }, 250);
            });

            document.addEventListener('click', function(e) {
                if (!document.getElementById('admin-global-search-wrap').contains(e.target)) {
                    box.style.display = 'none';
                }
            });

            inp.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') { box.style.display='none'; this.value=''; }
            });
        });
        </script>
        @endcan

        <div class="nav-right col-span-12 float-right right-header p-0 ms-auto">
            <ul class="nav-menus">
                {{-- Global admin search — first flex item, pushed left with me-auto
                     so it never adds a grid column (that used to wrap the navbar) --}}
                @can('access-admin')
                <li class="me-auto p-0" id="admin-global-search-wrap" style="position:relative;flex:0 1 300px;min-width:200px;">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-transparent border-end-0"><i data-feather="search" style="width:14px;height:14px;"></i></span>
                        <input type="text" id="admin-global-search" class="form-control border-start-0 ps-0" placeholder="Hledat zákazníky, faktury…" autocomplete="off" style="font-size:13px;">
                    </div>
                    <div id="admin-search-results" class="card shadow" style="display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;z-index:9999;max-height:400px;overflow-y:auto;border-radius:8px;"></div>
                </li>
                @endcan

                {{-- Credit balance quick view --}}
                @if(auth()->user()?->customer)
                    <li>
                        <a href="{{ route('panel.billing.credits') }}"
                           class="badge badge-light-primary d-flex align-items-center gap-1 f-12 f-w-600"
                           style="padding:8px 14px;border-radius:20px;"
                           title="{{ __('panel.billing.balance') }}">
                            <i data-feather="dollar-sign" style="width:15px;height:15px;"></i>
                            <span>{{ \App\Domains\Shared\Support\MoneyFormatter::format(app(\App\Domains\Billing\Services\CreditLedger::class)->getBalance(auth()->user()->customer)) }}</span>
                        </a>
                    </li>
                @endif

                {{-- Locale switcher — native Cuba translate_wrapper (script.js toggles .active) --}}
                <li class="language-nav">
                    <div class="translate_wrapper">
                        <div class="current_lang">
                            <div class="lang">
                                <i class="flag-icon flag-icon-{{ app()->getLocale() === 'cs' ? 'cz' : 'us' }}"></i>
                                <span class="lang-txt">{{ app()->getLocale() }}</span>
                            </div>
                        </div>
                        <div class="more_lang">
                            @foreach(['cs' => ['cz','Čeština'], 'en' => ['us','English']] as $loc => [$flag, $label])
                            <a href="{{ route('locale.switch', $loc) }}"
                               class="lang {{ app()->getLocale() === $loc ? 'selected' : '' }} d-flex align-items-center text-decoration-none">
                                <i class="flag-icon flag-icon-{{ $flag }}"></i>
                                <span class="lang-txt">{{ $label }}</span>
                            </a>
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
                        @can('access-admin')
                            <li><a href="{{ route('admin.account.security') }}"><i data-feather="lock"></i><span>Zabezpečení</span></a></li>
                            <li><a href="{{ route('admin.settings.index') }}"><i data-feather="settings"></i><span>Nastavení systému</span></a></li>
                        @endcan
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
