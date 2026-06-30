<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel') | Onhost.cz</title>
    <link rel="icon" href="{{ asset('panel/images/favicon.png') }}" type="image/x-icon">

    {{-- Cuba CSS stack --}}
    <link href="https://fonts.googleapis.com/css?family=Rubik:400,400i,500,500i,700,700i&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('panel/css/vendors/fontawesome.css') }}">
    <link rel="stylesheet" href="{{ asset('panel/css/vendors/icofont.css') }}">
    <link rel="stylesheet" href="{{ asset('panel/css/vendors/themify.css') }}">
    <link rel="stylesheet" href="{{ asset('panel/css/vendors/feather-icon.css') }}">
    <link rel="stylesheet" href="{{ asset('panel/css/vendors/scrollbar.css') }}">
    <link rel="stylesheet" href="{{ asset('panel/css/vendors/animate.css') }}">
    @stack('styles')
    <link rel="stylesheet" href="{{ asset('panel/css/style.css') }}">
</head>
<body>
{{-- Cuba loader --}}
<div class="loader-wrapper">
    <div class="loader-index"><span></span></div>
    <svg><defs></defs><filter id="goo"><fegaussianblur in="SourceGraphic" stddeviation="11" result="blur"></fegaussianblur><fecolormatrix in="blur" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 19 -9" result="goo"></fecolormatrix></filter></svg>
</div>

<div class="tap-top"><i data-feather="chevrons-up"></i></div>

<div class="page-wrapper compact-wrapper" id="pageWrapper">
    <x-panel.header />

    <div class="page-body-wrapper">
        <x-panel.sidebar />

        <div class="page-body">
            {{-- Impersonation top bar --}}
            @if(session()->has('_impersonated_by'))
            <div class="container-fluid py-0">
                <div class="alert alert-warning mb-0 py-2 d-flex align-items-center gap-3" role="alert"
                     style="border-radius:0;border-left:0;border-right:0;border-top:0;">
                    <i data-feather="eye" style="width:16px;height:16px;flex-shrink:0;"></i>
                    <span class="f-13 flex-1">
                        Jste přihlášen <strong>za zákazníka {{ auth()->user()?->name }}</strong>
                        ({{ auth()->user()?->email }}) — vidíte zákaznický panel z perspektivy zákazníka.
                    </span>
                    <a href="{{ route('admin.impersonate.stop') }}"
                       class="btn btn-sm btn-warning text-white flex-shrink-0">
                        <i data-feather="log-out" style="width:13px;height:13px;"></i>
                        Zpět na admin účet
                    </a>
                </div>
            </div>
            @endif
            <x-panel.breadcrumb :title="$breadcrumbTitle ?? null" :items="$breadcrumbItems ?? []" />
            @yield('content')
        </div>

        <footer class="footer">
            <div class="container-fluid">
                <div class="grid grid-cols-12">
                    <div class="col-span-12 footer-copyright text-center">
                        <p class="mb-0">© {{ date('Y') }} Onhost.cz</p>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</div>

{{-- Cuba JS stack --}}
<script src="{{ asset('panel/js/jquery.min.js') }}"></script>
<script src="{{ asset('panel/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('panel/js/icons/feather-icon/feather.min.js') }}"></script>
<script src="{{ asset('panel/js/icons/feather-icon/feather-icon.js') }}"></script>
<script src="{{ asset('panel/js/scrollbar/simplebar.min.js') }}"></script>
<script src="{{ asset('panel/js/scrollbar/custom.js') }}"></script>
<script src="{{ asset('panel/js/config.js') }}"></script>
<script src="{{ asset('panel/js/sidebar-menu.js') }}"></script>
<script src="{{ asset('panel/js/sidebar-pin.js') }}"></script>
<script>
/* ── Fix Cuba sidebar-menu.js TypeError when no active link in sidebar ──
   jQuery's .offset() on an empty selection returns undefined in jQuery 3.x,
   causing "Cannot read properties of undefined (reading 'top')".
   We patch $.fn.offset to return a safe default for empty selections.    ── */
(function($) {
    if (!$) return;
    var _origOffset = $.fn.offset;
    $.fn.offset = function() {
        if (this.length === 0) return { top: 0, left: 0 };
        return _origOffset.apply(this, arguments);
    };
})(window.jQuery);

/* ── Fix Cuba sidebar-pin.js TypeError when .pin-title not found ──
   If the pin-title element doesn't render, guard against null access.   ── */
(function() {
    var orig = window.togglePinnedName;
    if (typeof orig === 'function') return;
})();

/* ── Open the active sidebar submenu on page load ──────────────────────
   Cuba's sidebar-menu.js hides ALL .sidebar-submenu on load.
   We re-open the one that contains the active link.                    ── */
(function() {
    function openActiveSubmenu() {
        document.querySelectorAll('.sidebar-submenu').forEach(function(sub) {
            var hasActive = sub.querySelector('a.active') || sub.querySelector('li.active');
            if (!hasActive) return;

            // Show the submenu
            sub.style.display = 'block';
            sub.style.removeProperty('display'); // let slideDown handle it via jQuery
            if (window.jQuery) jQuery(sub).show();

            // Mark parent .sidebar-title as active and update arrow icon
            var title = sub.previousElementSibling;
            if (title && title.classList.contains('sidebar-title')) {
                title.classList.add('active');
                var arrow = title.querySelector('.according-menu');
                if (arrow) arrow.innerHTML = '<i class="fa-solid fa-angle-down"></i>';
            }
        });
    }

    // Run after sidebar-menu.js has had time to hide submenus
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', openActiveSubmenu);
    } else {
        setTimeout(openActiveSubmenu, 50);
    }
})();
</script>
@livewireScripts
@stack('scripts')
<script src="{{ asset('panel/js/script.js') }}"></script>
<script>
/* ── In-panel notification bell ─────────────────────────────────── */
(function() {
    var FETCH_URL    = '{{ route('panel.notifications.index') }}';
    var READ_ALL_URL = '{{ route('panel.notifications.read-all') }}';
    var READ_URL_TPL = '{{ route('panel.notifications.read', ['id' => 'NOTIF_ID_PLACEHOLDER']) }}';
    var CSRF         = document.querySelector('meta[name="csrf-token"]').content;

    var iconMap = {
        'check-circle': '✓', 'file-text': '📄', 'alert-triangle': '⚠',
        'clock': '⏰', 'server': '🖥', 'message-circle': '💬',
    };
    var colorMap = {
        success: '#54ba4a', primary: '#7366FF', warning: '#f39c12',
        danger: '#dc3545', info: '#0dcaf0',
    };

    function loadNotifications() {
        fetch(FETCH_URL, { headers: { Accept: 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var count = data.unread_count;
                var badge = document.getElementById('notif-count');
                if (badge) {
                    badge.textContent = count > 9 ? '9+' : count;
                    badge.style.display = count > 0 ? '' : 'none';
                }
                var label = document.getElementById('notif-unread-label');
                if (label) label.textContent = count > 0 ? count + ' nepřečtených' : '';

                var container = document.getElementById('notif-items-container');
                if (!container) return;

                if (!data.notifications || data.notifications.length === 0) {
                    container.innerHTML = '<div class="text-center py-3 f-light f-12">Žádné notifikace</div>';
                    return;
                }

                var html = '';
                data.notifications.forEach(function(n) {
                    var d = n.data || {};
                    var icon = iconMap[d.icon] || '•';
                    var color = colorMap[d.color] || '#7366FF';
                    var readClass = n.read ? '' : 'f-w-600';
                    var bg = n.read ? '' : 'background:rgba(115,102,255,.04);';
                    html += '<div class="media notification-item" style="padding:10px 14px;border-bottom:1px solid rgba(82,82,108,.1);cursor:pointer;' + bg + '"' +
                        ' data-id="' + n.id + '" data-url="' + (d.url || '#') + '" onclick="handleNotifClick(this)">' +
                        '<div class="flex-shrink-0 me-3 d-flex align-items-center justify-content-center rounded-circle"' +
                        ' style="width:36px;height:36px;background:' + color + '22;font-size:15px;">' + icon + '</div>' +
                        '<div class="media-body">' +
                        '<p class="mb-0 ' + readClass + ' f-13">' + (d.title || '') + '</p>' +
                        '<p class="mb-0 f-light f-12" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:260px;">' + (d.body || '') + '</p>' +
                        '<p class="mb-0 f-light" style="font-size:10px;">' + n.created_at + '</p>' +
                        '</div></div>';
                });
                container.innerHTML = html;
            })
            .catch(function() {});
    }

    window.handleNotifClick = function(el) {
        var id  = el.getAttribute('data-id');
        var url = el.getAttribute('data-url');
        fetch(READ_URL_TPL.replace('NOTIF_ID_PLACEHOLDER', id), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, Accept: 'application/json' },
        }).then(function() { if (url && url !== '#') window.location.href = url; });
    };

    window.markAllNotifRead = function() {
        fetch(READ_ALL_URL, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, Accept: 'application/json' },
        }).then(function() { loadNotifications(); });
    };

    /* Load on open */
    var bellLi = document.getElementById('notif-bell-li');
    if (bellLi) {
        bellLi.addEventListener('mouseenter', function() { loadNotifications(); });
    }

    /* Poll every 60 s for count update */
    loadNotifications();
    setInterval(loadNotifications, 60000);
})();
</script>
</body>
</html>
