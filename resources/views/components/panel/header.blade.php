@php($authUser = auth()->user())
<div class="page-header">
    <div class="header-wrapper m-0" style="display:flex;align-items:center;flex-wrap:nowrap;gap:14px;">

        {{-- Logo + sidebar toggle --}}
        <div class="header-logo-wrapper p-0" style="flex:0 0 auto;display:flex;align-items:center;">
            <div class="logo-wrapper">
                <a href="{{ route('panel.dashboard') }}" style="display:inline-flex;align-items:center;">
                    <img class="for-light" src="{{ asset('panel/images/logo/logo-onhost.svg') }}" alt="Onhost.cz" style="height:32px;width:auto;">
                    <img class="for-dark" src="{{ asset('panel/images/logo/logo-onhost-white.svg') }}" alt="Onhost.cz" style="height:32px;width:auto;">
                </a>
            </div>
            <div class="toggle-sidebar" style="margin-left:16px;cursor:pointer;"><i class="status_toggle middle sidebar-toggle" data-feather="align-center"></i></div>
        </div>

        {{-- Global admin search (grows to fill, capped) --}}
        @can('access-admin')
        <div id="admin-global-search-wrap" style="position:relative;flex:1 1 auto;max-width:340px;min-width:0;">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-transparent border-end-0"><i data-feather="search" style="width:14px;height:14px;"></i></span>
                <input type="text" id="admin-global-search" class="form-control border-start-0 ps-0" placeholder="Hledat zákazníky, faktury…" autocomplete="off" style="font-size:13px;">
            </div>
            <div id="admin-search-results" class="card shadow" style="display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;z-index:9999;max-height:400px;overflow-y:auto;border-radius:8px;"></div>
        </div>
        @endcan

        {{-- Right-hand navbar --}}
        <div class="nav-right right-header p-0" style="flex:0 0 auto;margin-left:auto;width:auto;">
            <ul class="nav-menus mb-0" style="display:flex;align-items:center;gap:8px;list-style:none;padding:0;margin:0;">

                {{-- Credit balance (customers only) --}}
                @if($authUser?->customer)
                    <li style="list-style:none;">
                        <a href="{{ route('panel.billing.credits') }}"
                           style="display:inline-flex;align-items:center;gap:5px;text-decoration:none;background:rgba(115,102,255,.1);color:#7366ff;border-radius:20px;padding:7px 14px;font-size:12px;font-weight:600;white-space:nowrap;"
                           title="{{ __('panel.billing.balance') }}">
                            <i data-feather="dollar-sign" style="width:15px;height:15px;"></i>
                            <span>{{ \App\Domains\Shared\Support\MoneyFormatter::format(app(\App\Domains\Billing\Services\CreditLedger::class)->getBalance($authUser->customer)) }}</span>
                        </a>
                    </li>
                @endif

                {{-- Language switcher (native Cuba translate_wrapper; script.js toggles .active) --}}
                <li class="language-nav" style="list-style:none;">
                    <div class="translate_wrapper">
                        <div class="current_lang" style="cursor:pointer;padding:6px 8px;">
                            <div class="lang" style="display:flex;align-items:center;gap:5px;">
                                <i class="flag-icon flag-icon-{{ app()->getLocale() === 'cs' ? 'cz' : 'us' }}" style="width:20px;height:14px;border-radius:2px;"></i>
                                <span class="lang-txt" style="font-size:12px;font-weight:600;text-transform:uppercase;">{{ app()->getLocale() }}</span>
                            </div>
                        </div>
                        <div class="more_lang">
                            @foreach(['cs' => ['cz','Čeština'], 'en' => ['us','English']] as $loc => [$flag, $label])
                            <a href="{{ route('locale.switch', $loc) }}"
                               class="lang {{ app()->getLocale() === $loc ? 'selected' : '' }}"
                               style="display:flex;align-items:center;gap:8px;text-decoration:none;">
                                <i class="flag-icon flag-icon-{{ $flag }}" style="width:20px;height:14px;border-radius:2px;"></i>
                                <span class="lang-txt">{{ $label }}</span>
                            </a>
                            @endforeach
                        </div>
                    </div>
                </li>

                {{-- Notification bell --}}
                <li class="onhover-dropdown" id="notif-bell-li" style="list-style:none;">
                    <div class="notification-box" id="notif-bell" style="cursor:pointer;position:relative;display:flex;align-items:center;">
                        <i data-feather="bell" style="width:20px;height:20px;"></i>
                        <span class="badge badge-danger rounded-circle" id="notif-count" style="display:none;font-size:9px;min-width:16px;height:16px;line-height:16px;padding:0 3px;position:absolute;top:-6px;right:-6px;"></span>
                    </div>
                    <ul class="notification-dropdown onhover-show-div" style="width:340px;max-width:calc(100vw - 32px);max-height:480px;overflow-y:auto;">
                        <li>
                            <h6 class="f-18 mb-0 dropdown-title">Notifikace</h6>
                            <span class="f-light f-12 float-end" id="notif-unread-label"></span>
                        </li>
                        <li id="notif-items-container">
                            <div class="text-center py-3 f-light f-12">Načítání…</div>
                        </li>
                        <li class="text-center py-1" style="display:flex;gap:8px;justify-content:center;">
                            <button class="btn btn-primary btn-xs text-white" id="notif-mark-all-btn" onclick="markAllNotifRead()">
                                Označit vše přečtené
                            </button>
                            <a href="{{ route('panel.notifications.index') }}" class="btn btn-outline-primary btn-xs">
                                Všechny notifikace
                            </a>
                        </li>
                    </ul>
                </li>

                {{-- Dark mode toggle --}}
                <li style="list-style:none;">
                    <div class="mode" style="cursor:pointer;display:flex;align-items:center;">
                        <svg style="width:20px;height:20px;"><use href="{{ asset('panel/svg/icon-sprite.svg') }}#moon"></use></svg>
                    </div>
                </li>

                {{-- Profile dropdown --}}
                <li class="profile-nav onhover-dropdown p-0" style="list-style:none;">
                    <div style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                        <span style="display:flex;align-items:center;justify-content:center;flex:0 0 auto;width:36px;height:36px;border-radius:50%;background:#7366ff;color:#fff;font-weight:600;font-size:14px;">
                            {{ mb_strtoupper(mb_substr($authUser?->name ?? 'U', 0, 1)) }}
                        </span>
                        <span style="display:flex;flex-direction:column;line-height:1.25;">
                            <span style="font-size:13px;font-weight:600;white-space:nowrap;color:inherit;">{{ $authUser?->name }}</span>
                            <span style="font-size:11px;color:#999;white-space:nowrap;">{{ $authUser?->isAdmin() ? 'Administrátor' : __('panel.nav.customer') }}</span>
                        </span>
                        <i class="fa-solid fa-angle-down" style="font-size:11px;color:#999;"></i>
                    </div>
                    <ul class="profile-dropdown onhover-show-div" style="right:0;left:unset;min-width:190px;">
                        <li><a href="{{ route('panel.account.profile') }}"><i data-feather="user"></i><span>{{ __('panel.nav.profile') }}</span></a></li>
                        @can('access-admin')
                            <li><a href="{{ route('admin.account.security') }}"><i data-feather="lock"></i><span>Zabezpečení</span></a></li>
                            <li><a href="{{ route('admin.settings.index') }}"><i data-feather="settings"></i><span>Nastavení systému</span></a></li>
                        @endcan
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" style="width:100%;text-align:left;border:0;background:transparent;padding:0;"><i data-feather="log-out"></i><span>{{ __('panel.nav.logout') }}</span></button>
                            </form>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</div>

@can('access-admin')
<script>
document.addEventListener('DOMContentLoaded', function(){
    const inp = document.getElementById('admin-global-search');
    const box = document.getElementById('admin-search-results');
    const wrap = document.getElementById('admin-global-search-wrap');
    if (!inp) return;
    let timer;
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    const typeColors = { customer:'primary', service:'info', invoice:'success', order:'warning', ticket:'secondary' };
    const typeLabels = { customer:'Zákazník', service:'Služba', invoice:'Faktura', order:'Objednávka', ticket:'Tiket' };

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
                        <a href="${r.url}" class="border-bottom" style="display:flex;align-items:center;gap:8px;padding:8px 12px;text-decoration:none;color:#333;font-size:13px">
                            <i data-feather="${r.icon}" style="width:14px;height:14px;flex-shrink:0;color:#6c757d;"></i>
                            <div style="flex:1;overflow:hidden;">
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
        if (wrap && !wrap.contains(e.target)) { box.style.display = 'none'; }
    });

    inp.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') { box.style.display='none'; this.value=''; }
    });
});
</script>
@endcan
