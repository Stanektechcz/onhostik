@php($authUser = auth()->user())
{{--
    Cuba page-header. Built ONLY from Cuba component classes
    (.nav-menus, .translate_wrapper, .profile-media, .onhover-dropdown …)
    plus the Tailwind utilities that ship with the Cuba build.

    NOTE: the header-wrapper is `flex`, not `grid grid-cols-12`. In this Cuba
    build `.col-auto` = `grid-column: auto` (one 1/12 slot) and `.col-span-12`
    spans all twelve, so logo + nav-right summed to 13 columns and the navbar
    wrapped onto a second row. Flex is what the zones actually need.
--}}
<div class="page-header">
    <div class="header-wrapper flex items-center gap-3 m-0">

        {{-- Logo + sidebar toggle --}}
        <div class="header-logo-wrapper shrink-0 p-0">
            <div class="logo-wrapper">
                <a href="{{ route('panel.dashboard') }}">
                    <img class="img-fluid for-light" src="{{ asset('panel/images/logo/logo-onhost.svg') }}" alt="Onhost.cz">
                    <img class="img-fluid for-dark" src="{{ asset('panel/images/logo/logo-onhost-white.svg') }}" alt="Onhost.cz">
                </a>
            </div>
            <div class="toggle-sidebar">
                <i class="status_toggle middle sidebar-toggle" data-feather="align-center"></i>
            </div>
        </div>

        {{-- Global search (admin only) — grows into the free space, capped --}}
        @can('access-admin')
            <div class="grow min-w-0 relative" id="admin-global-search-wrap">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-transparent border-end-0">
                        <i data-feather="search"></i>
                    </span>
                    <input type="text" id="admin-global-search" class="form-control border-start-0 ps-0"
                           placeholder="Hledat zákazníky, služby, faktury, tikety…" autocomplete="off"
                           aria-label="Globální vyhledávání">
                </div>
                <div id="admin-search-results" class="card shadow"></div>
            </div>
        @endcan

        {{-- Right-hand navbar --}}
        <div class="nav-right shrink-0 ms-auto right-header p-0">
            <ul class="nav-menus flex items-center gap-2 mb-0">

                {{-- Credit balance (customers only) --}}
                @if($authUser?->customer)
                    <li>
                        <a href="{{ route('panel.billing.credits') }}" class="badge badge-light-primary f-w-500"
                           title="{{ __('panel.billing.balance') }}">
                            <i data-feather="dollar-sign"></i>
                            {{ \App\Domains\Shared\Support\MoneyFormatter::format(app(\App\Domains\Billing\Services\CreditLedger::class)->getBalance($authUser->customer)) }}
                        </a>
                    </li>
                @endif

                {{-- Language switcher — Cuba translate_wrapper (script.js toggles .active).
                     Cuba hides the .selected entry in .more_lang by design. --}}
                <li class="language-nav">
                    <div class="translate_wrapper">
                        <div class="current_lang">
                            <div class="lang">
                                <i class="flag-icon flag-icon-{{ app()->getLocale() === 'cs' ? 'cz' : 'us' }}"></i>
                                <span class="lang-txt">{{ strtoupper(app()->getLocale()) }}</span>
                            </div>
                        </div>
                        <div class="more_lang">
                            @foreach(['cs' => ['cz', 'Čeština'], 'en' => ['us', 'English']] as $loc => [$flag, $label])
                                <a href="{{ route('locale.switch', $loc) }}"
                                   class="lang {{ app()->getLocale() === $loc ? 'selected' : '' }}"
                                   data-value="{{ $loc }}">
                                    <i class="flag-icon flag-icon-{{ $flag }}"></i>
                                    <span class="lang-txt">{{ $label }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </li>

                {{-- Notifications --}}
                <li class="onhover-dropdown" id="notif-bell-li">
                    <div class="notification-box" id="notif-bell">
                        <i data-feather="bell"></i>
                        <span class="badge rounded-pill badge-danger" id="notif-count"></span>
                    </div>
                    {{-- Cuba notification-dropdown: every notification is its own
                         <li> (Cuba gives it the grey card + radius) whose <p> is
                         flex/space-between — title left, timestamp right. The
                         last <li> is the centred action row. JS rebuilds the
                         list between the heading and the actions. --}}
                    <ul class="notification-dropdown onhover-show-div" id="notif-dropdown">
                        <li>
                            <p class="f-w-600 mb-0 p-3">
                                Notifikace
                                <span class="pull-right badge badge-light-primary" id="notif-unread-label"></span>
                            </p>
                        </li>
                        <li class="notif-placeholder">
                            <p class="f-light f-12 mb-0 p-3">Načítání…</p>
                        </li>
                        <li>
                            <button class="btn btn-primary btn-xs text-white" id="notif-mark-all-btn" onclick="markAllNotifRead()">
                                Označit vše přečtené
                            </button>
                            <a href="{{ route('panel.notifications.index') }}" class="btn btn-outline-primary btn-xs">
                                Všechny
                            </a>
                        </li>
                    </ul>
                </li>

                {{-- Dark mode --}}
                <li>
                    <div class="mode">
                        <svg><use href="{{ asset('panel/svg/icon-sprite.svg') }}#moon"></use></svg>
                    </div>
                </li>

                {{-- Profile — Cuba .profile-media > .profile-content --}}
                <li class="profile-nav onhover-dropdown p-0">
                    <div class="flex profile-media items-center">
                        <div class="profile-avatar bg-primary rounded-circle flex items-center justify-center">
                            {{ mb_strtoupper(mb_substr($authUser?->name ?? 'U', 0, 1)) }}
                        </div>
                        <div class="grow profile-content">
                            <span class="f-w-500">{{ $authUser?->name }}</span>
                            <p class="mb-0 font-outfit">
                                {{ $authUser?->isAdmin() ? 'Administrátor' : __('panel.nav.customer') }}
                                <i class="middle fa-solid fa-angle-down"></i>
                            </p>
                        </div>
                    </div>
                    <ul class="profile-dropdown onhover-show-div">
                        <li>
                            <a href="{{ route('panel.account.profile') }}">
                                <i data-feather="user"></i><span>{{ __('panel.nav.profile') }}</span>
                            </a>
                        </li>
                        @can('access-admin')
                            <li>
                                <a href="{{ route('admin.account.security') }}">
                                    <i data-feather="lock"></i><span>Zabezpečení</span>
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('admin.settings.index') }}">
                                    <i data-feather="settings"></i><span>Nastavení systému</span>
                                </a>
                            </li>
                        @endcan
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="logout-btn">
                                    <i data-feather="log-out"></i><span>{{ __('panel.nav.logout') }}</span>
                                </button>
                            </form>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</div>

@can('access-admin')
<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('DOMContentLoaded', function () {
    const inp  = document.getElementById('admin-global-search');
    const box  = document.getElementById('admin-search-results');
    const wrap = document.getElementById('admin-global-search-wrap');
    if (!inp) return;

    let timer;
    let activeIdx = -1;

    const typeColors = { customer: 'primary', service: 'info', invoice: 'success', order: 'warning', ticket: 'secondary' };
    const typeLabels = { customer: 'Zákazník', service: 'Služba', invoice: 'Faktura', order: 'Objednávka', ticket: 'Tiket' };
    const esc = s => String(s ?? '').replace(/[<>&"]/g, c => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;' }[c]));

    function close() { box.classList.remove('show'); activeIdx = -1; }

    function render(results) {
        if (!results.length) {
            box.innerHTML = '<div class="search-empty f-light f-12">Žádné výsledky</div>';
        } else {
            box.innerHTML = results.map(r => `
                <a href="${esc(r.url)}" class="search-result-item">
                    <i data-feather="${esc(r.icon)}"></i>
                    <div class="grow min-w-0">
                        <div class="f-w-500 truncate">${esc(r.title)}</div>
                        <div class="f-light f-11 truncate">${esc(r.subtitle)}</div>
                    </div>
                    <span class="badge badge-light-${typeColors[r.type] ?? 'secondary'}">${esc(typeLabels[r.type] ?? r.type)}</span>
                </a>`).join('');
        }
        box.classList.add('show');
        activeIdx = -1;
        if (window.feather) feather.replace();
    }

    inp.addEventListener('input', function () {
        clearTimeout(timer);
        const q = this.value.trim();
        if (q.length < 2) { close(); return; }

        timer = setTimeout(() => {
            fetch(`{{ route('admin.search.quick') }}?q=` + encodeURIComponent(q), { headers: { Accept: 'application/json' } })
                .then(r => r.json())
                .then(d => render(d.results ?? []))
                .catch(() => { box.innerHTML = '<div class="search-empty f-light f-12">Vyhledávání selhalo</div>'; box.classList.add('show'); });
        }, 250);
    });

    // Keyboard navigation through results.
    inp.addEventListener('keydown', function (e) {
        const items = [...box.querySelectorAll('.search-result-item')];

        if (e.key === 'Escape') { close(); this.value = ''; return; }
        if (!items.length) return;

        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            activeIdx = e.key === 'ArrowDown'
                ? (activeIdx + 1) % items.length
                : (activeIdx - 1 + items.length) % items.length;
            items.forEach((el, i) => el.classList.toggle('active', i === activeIdx));
            items[activeIdx].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter' && activeIdx >= 0) {
            e.preventDefault();
            items[activeIdx].click();
        }
    });

    document.addEventListener('click', e => { if (wrap && !wrap.contains(e.target)) close(); });
});
</script>
@endcan
