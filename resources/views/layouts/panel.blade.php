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
<script nonce="{{ $cspNonce ?? '' }}">
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
@livewireScripts(['nonce' => $cspNonce ?? ''])
@stack('scripts')
<script src="{{ asset('panel/js/script.js') }}"></script>
<script src="{{ asset('panel/js/pusher.min.js') }}"></script>
<script src="{{ asset('panel/js/echo.iife.js') }}"></script>
<script nonce="{{ $cspNonce ?? '' }}">
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

    var wsConnected = false;
    var pollInterval = null;

    function updateBadge(count) {
        var badge = document.getElementById('notif-count');
        if (badge) {
            badge.textContent = count > 9 ? '9+' : count;
            badge.style.display = count > 0 ? '' : 'none';
        }
        var label = document.getElementById('notif-unread-label');
        if (label) label.textContent = count > 0 ? count + ' nepřečtených' : '';
    }

    function renderNotifications(data) {
        updateBadge(data.unread_count);

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
    }

    function loadNotifications() {
        fetch(FETCH_URL, { headers: { Accept: 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(renderNotifications)
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

    /* Load on bell open */
    var bellLi = document.getElementById('notif-bell-li');
    if (bellLi) {
        bellLi.addEventListener('mouseenter', function() { loadNotifications(); });
    }

    /* Initial load */
    loadNotifications();

    /* ── WebSocket push via Laravel Reverb ─────────────────────── */
    @auth
    (function() {
        var REVERB_KEY    = '{{ config("reverb.apps.apps.0.key", "") }}';
        var REVERB_HOST   = '{{ config("reverb.apps.apps.0.options.host", "localhost") }}';
        var REVERB_PORT   = {{ (int) config("reverb.apps.apps.0.options.port", 8080) }};
        var REVERB_SCHEME = '{{ config("reverb.apps.apps.0.options.scheme", "http") }}';

        if (!REVERB_KEY || typeof window.Echo === 'undefined' || typeof Pusher === 'undefined') {
            /* Reverb not configured — fall back to 60s polling */
            pollInterval = setInterval(loadNotifications, 60000);
            return;
        }

        try {
            var echo = new Echo({
                broadcaster: 'reverb',
                key: REVERB_KEY,
                wsHost: REVERB_HOST,
                wsPort: REVERB_PORT,
                wssPort: REVERB_PORT,
                forceTLS: REVERB_SCHEME === 'https',
                enabledTransports: ['ws', 'wss'],
                authEndpoint: '/broadcasting/auth',
                auth: { headers: { 'X-CSRF-TOKEN': CSRF } },
            });

            echo.connector.pusher.connection.bind('connected', function() {
                wsConnected = true;
                /* WebSocket active — cancel polling */
                if (pollInterval) { clearInterval(pollInterval); pollInterval = null; }
            });

            echo.connector.pusher.connection.bind('disconnected', function() {
                wsConnected = false;
                /* Reconnect fallback — resume 60s polling */
                if (!pollInterval) { pollInterval = setInterval(loadNotifications, 60000); }
            });

            echo.private('user.{{ auth()->id() }}')
                .listen('.notification.received', function(e) {
                    updateBadge(e.unread_count);
                    /* Refresh full list so dropdown is fresh on next open */
                    loadNotifications();
                });
        } catch (err) {
            /* Any Echo init failure → safe fallback to polling */
            pollInterval = setInterval(loadNotifications, 60000);
        }
    })();
    @else
    /* Unauthenticated — no WebSocket needed */
    @endauth
})();
</script>

@auth
{{-- ── AI Chatbot Widget ─────────────────────────────────────────────────── --}}
<style nonce="{{ $cspNonce ?? '' }}">
#ai-chat-widget{position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;align-items:flex-end;}
#ai-chat-toggle{width:52px;height:52px;border-radius:50%;background:#4680ff;border:none;color:#fff;box-shadow:0 4px 14px rgba(70,128,255,.45);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:transform .15s;}
#ai-chat-toggle:hover{transform:scale(1.08);}
#ai-chat-panel{width:320px;background:#fff;border-radius:14px;box-shadow:0 8px 32px rgba(0,0,0,.18);margin-bottom:10px;overflow:hidden;display:flex;flex-direction:column;}
#ai-chat-header{background:#4680ff;color:#fff;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;font-weight:600;font-size:13px;}
#ai-chat-close{background:none;border:none;color:#fff;font-size:20px;cursor:pointer;line-height:1;padding:0;}
#ai-chat-messages{height:220px;overflow-y:auto;padding:12px;display:flex;flex-direction:column;gap:8px;}
.ai-msg{padding:8px 12px;border-radius:8px;font-size:12px;line-height:1.5;max-width:92%;word-break:break-word;}
.ai-msg--user{background:#4680ff;color:#fff;align-self:flex-end;border-radius:8px 8px 2px 8px;}
.ai-msg--bot{background:#f0f2f8;color:#333;align-self:flex-start;border-radius:8px 8px 8px 2px;}
#ai-chat-form{padding:8px 12px 12px;display:flex;gap:6px;}
#ai-chat-input{flex:1;border:1px solid #dde1ef;border-radius:8px;padding:7px 10px;font-size:12px;outline:none;transition:border-color .15s;}
#ai-chat-input:focus{border-color:#4680ff;}
#ai-chat-send{background:#4680ff;color:#fff;border:none;border-radius:8px;padding:7px 12px;font-size:12px;cursor:pointer;}
#ai-chat-send:disabled{opacity:.55;cursor:default;}
</style>
<div id="ai-chat-widget">
    <div id="ai-chat-panel" class="d-none">
        <div id="ai-chat-header">
            <span>⚡ AI Asistent</span>
            <button id="ai-chat-close" title="Zavřít">×</button>
        </div>
        <div id="ai-chat-messages">
            <div class="ai-msg ai-msg--bot">Dobrý den! Jak vám mohu pomoci?</div>
        </div>
        <div id="ai-chat-form">
            <input id="ai-chat-input" type="text" placeholder="Napište dotaz…" autocomplete="off">
            <button id="ai-chat-send">→</button>
        </div>
    </div>
    <button id="ai-chat-toggle" title="AI Asistent">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
    </button>
</div>
<script nonce="{{ $cspNonce ?? '' }}">
(function(){
    var toggle=document.getElementById('ai-chat-toggle'),
        panel=document.getElementById('ai-chat-panel'),
        close=document.getElementById('ai-chat-close'),
        input=document.getElementById('ai-chat-input'),
        send=document.getElementById('ai-chat-send'),
        msgs=document.getElementById('ai-chat-messages'),
        csrf=document.querySelector('meta[name="csrf-token"]')?document.querySelector('meta[name="csrf-token"]').content:'';

    toggle.addEventListener('click',function(){panel.classList.toggle('d-none');if(!panel.classList.contains('d-none'))input.focus();});
    close.addEventListener('click',function(){panel.classList.add('d-none');});

    function addMsg(text,isUser){
        var d=document.createElement('div');
        d.className='ai-msg '+(isUser?'ai-msg--user':'ai-msg--bot');
        d.textContent=text;
        msgs.appendChild(d);
        msgs.scrollTop=msgs.scrollHeight;
    }

    function sendMsg(){
        var text=input.value.trim();
        if(!text||send.disabled)return;
        addMsg(text,true);
        input.value='';
        input.disabled=true;
        send.disabled=true;
        addMsg('…',false);
        fetch('/panel/ai/chat',{
            method:'POST',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
            body:JSON.stringify({message:text})
        }).then(function(r){return r.json();}).then(function(d){
            msgs.lastChild.textContent=d.reply||'Omlouváme se, zkuste to znovu.';
            msgs.scrollTop=msgs.scrollHeight;
        }).catch(function(){
            msgs.lastChild.textContent='Chyba komunikace.';
        }).finally(function(){
            input.disabled=false;send.disabled=false;input.focus();
        });
    }

    send.addEventListener('click',sendMsg);
    input.addEventListener('keydown',function(e){if(e.key==='Enter')sendMsg();});
})();
</script>
@endauth
</body>
</html>
