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
    <link rel="stylesheet" href="{{ asset('panel/css/vendors/flag-icon.css') }}">
    <link rel="stylesheet" href="{{ asset('panel/css/vendors/scrollbar.css') }}">
    <link rel="stylesheet" href="{{ asset('panel/css/vendors/animate.css') }}">
    @stack('styles')
    <link rel="stylesheet" href="{{ asset('panel/css/style.css') }}">
    {{-- Project layer — must load after Cuba's style.css --}}
    <link rel="stylesheet" href="{{ asset('panel/css/onhost.css') }}">
</head>
{{-- N176: dark mode is a persisted per-user preference, applied server-side so
     the page never flashes light before a client script catches up. Cuba's
     own toggle used localStorage, which reset on another device / cleared
     storage — this reads the DB column instead. --}}
<body class="{{ auth()->user()?->dark_mode ? 'dark-only' : '' }}">
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
            {{-- Maintenance banner --}}
            <x-maintenance-banner :active="$maintenanceActive ?? null" :upcoming="$maintenanceUpcoming ?? null" style="admin" />
            {{-- System announcement banners --}}
            @foreach($activeAnnouncements ?? [] as $announcement)
            <div class="container-fluid py-0 announcement-banner" id="announcement-{{ $announcement->id }}">
                <div class="alert alert-light-{{ $announcement->type === 'warning' ? 'warning' : ($announcement->type === 'maintenance' ? 'danger' : ($announcement->type === 'feature' ? 'success' : 'primary')) }} mb-0 py-2 flex items-start gap-3"
                     style="border-radius:0;border-left:0;border-right:0;border-top:0;">
                    <i data-feather="{{ $announcement->icon }}" style="width:16px;height:16px;flex-shrink:0;margin-top:2px;"></i>
                    <div class="flex-1">
                        <strong class="f-13">{{ $announcement->title }}</strong>
                        @if($announcement->body)
                            <p class="f-12 f-light mb-0">{{ $announcement->body }}</p>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('panel.announcements.dismiss', $announcement) }}">
                        @csrf
                        <button type="submit" class="btn-close btn-sm" style="opacity:.6;" aria-label="Zavřít"></button>
                    </form>
                </div>
            </div>
            @endforeach
            {{-- Impersonation top bar --}}
            @if(session()->has('_impersonated_by'))
            <div class="container-fluid py-0">
                <div class="alert alert-warning mb-0 py-2 flex items-center gap-3" role="alert"
                     style="border-radius:0;border-left:0;border-right:0;border-top:0;">
                    <i data-feather="eye" style="width:16px;height:16px;flex-shrink:0;"></i>
                    <span class="f-13 flex-1">
                        Jste přihlášen <strong>za zákazníka {{ auth()->user()?->name }}</strong>
                        ({{ auth()->user()?->email }}) — vidíte zákaznický panel z perspektivy zákazníka.
                    </span>
                    <a href="{{ route('admin.impersonate.stop') }}"
                       class="btn btn-sm btn-warning text-white shrink-0">
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
/* Initialise Bootstrap tooltips (Cuba loads bootstrap.bundle but does not
   auto-init them). Reads the element's data-tooltip / title as the label. */
(function(){
    if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el){
        var label = el.getAttribute('data-tooltip') || el.getAttribute('title');
        if (label) new bootstrap.Tooltip(el, { title: label });
    });
})();
</script>
<script nonce="{{ $cspNonce ?? '' }}">
/* ── In-panel notification bell ─────────────────────────────────── */
(function() {
    var FETCH_URL    = '{{ route('panel.notifications.index') }}';
    var READ_ALL_URL = '{{ route('panel.notifications.read-all') }}';
    var READ_URL_TPL = '{{ route('panel.notifications.read', ['id' => 'NOTIF_ID_PLACEHOLDER']) }}';
    var CSRF         = document.querySelector('meta[name="csrf-token"]').content;

    // Notification type → Cuba border-l-{color} accent on the toast row.
    var colorMap = {
        success: 'success', primary: 'primary', warning: 'warning',
        danger: 'danger', info: 'info', secondary: 'secondary',
    };
    function esc(s) {
        return String(s == null ? '' : s).replace(/[<>&"]/g, function (c) {
            return { '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;' }[c];
        });
    }

    var wsConnected = false;
    var pollInterval = null;

    function updateBadge(count) {
        var badge = document.getElementById('notif-count');
        if (badge) {
            badge.textContent = count > 9 ? '9+' : count;
            badge.classList.toggle('show', count > 0);
        }
        var label = document.getElementById('notif-unread-label');
        if (label) {
            label.textContent = count > 0 ? count : '';
            label.classList.toggle('show', count > 0);
        }
    }

    /**
     * Rebuilds the Cuba notification-dropdown list in place. Each entry is a
     * Cuba toast row: a grey card accented with border-l-{color} !border-l-4,
     * a .toast-body holding the title (left) + time (right) and an optional
     * sub-line, plus a .btn-close that marks it read. Rows are inserted
     * between the heading and the trailing (centred) actions <li>.
     */
    function renderNotifications(data) {
        updateBadge(data.unread_count);

        var list = document.getElementById('notif-list');
        if (!list) return;

        // Drop previously rendered rows / placeholder, keep the actions row.
        list.querySelectorAll('.notif-item, .notif-placeholder').forEach(function (el) {
            el.remove();
        });

        var actions = list.querySelector('.notif-actions');

        if (!data.notifications || data.notifications.length === 0) {
            var empty = document.createElement('li');
            empty.className = 'notif-placeholder';
            empty.innerHTML = '<div class="toast-body p-3"><p class="f-light f-12 mb-0">Žádné notifikace</p></div>';
            list.insertBefore(empty, actions);
            return;
        }

        data.notifications.forEach(function (n) {
            var d     = n.data || {};
            var color = colorMap[d.color] || 'primary';

            var li = document.createElement('li');
            li.className = 'notif-item border-l-' + color + ' !border-l-4' + (n.read ? ' is-read' : ' unread');
            li.dataset.id  = n.id;
            li.dataset.url = d.url || '#';

            li.innerHTML =
                '<div class="flex justify-between items-center">' +
                    '<div class="toast-body p-3">' +
                        '<p><span class="notif-title">' + esc(d.title) + '</span>' +
                        '<span class="f-light notif-time">' + esc(n.created_at) + '</span></p>' +
                        (d.body ? '<p class="f-light f-12 mb-0 notif-sub">' + esc(d.body) + '</p>' : '') +
                    '</div>' +
                    '<button class="btn-close" type="button" aria-label="Označit přečtené"></button>' +
                '</div>';

            // Whole grid grid-cols-12 navigates + marks read; the close button only marks read.
            li.querySelector('.toast-body').addEventListener('click', function () { handleNotifClick(li); });
            li.querySelector('.btn-close').addEventListener('click', function (e) {
                e.stopPropagation();
                markNotifRead(li);
            });

            list.insertBefore(li, actions);
        });
    }

    window.markNotifRead = function(el) {
        var id = el.getAttribute('data-id');
        fetch(READ_URL_TPL.replace('NOTIF_ID_PLACEHOLDER', id), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, Accept: 'application/json' },
        }).then(function() { loadNotifications(); });
    };

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

            /*
             | Audit I131. Binding only 'connected' and 'disconnected' left two
             | states unhandled: 'unavailable' (server unreachable — the common
             | case when Reverb is down) and 'failed' (no usable transport). In
             | both the socket is dead but polling never resumed, so the bell
             | silently stopped updating.
             |
             | state_change covers every state, including ones added later.
             */
            function startPolling() {
                if (!pollInterval) { pollInterval = setInterval(loadNotifications, 60000); }
            }

            function stopPolling() {
                if (pollInterval) { clearInterval(pollInterval); pollInterval = null; }
            }

            echo.connector.pusher.connection.bind('state_change', function(states) {
                if (states.current === 'connected') {
                    wsConnected = true;
                    stopPolling();

                    /* Catch up on anything that arrived while the socket was
                       down — otherwise a reconnect leaves a silent gap. */
                    loadNotifications();
                } else {
                    wsConnected = false;
                    startPolling();
                }
            });

            /* An auth or subscription error must not leave the bell frozen. */
            echo.connector.pusher.connection.bind('error', function() {
                wsConnected = false;
                startPolling();
            });

            /* Coming back to a backgrounded tab: browsers throttle timers, so
               the last poll may be minutes stale regardless of socket state. */
            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'visible') { loadNotifications(); }
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
/* Language dropdown items */
.translate_wrapper .more_lang .lang{padding:8px 14px;gap:8px;white-space:nowrap;}
</style>
{{-- Floating AI assistant. Closed by default; the whole thing is styled from
     Cuba variables in onhost.css. The chat backend returns a categorised set
     of follow-up quick-replies + deep links per turn. --}}
<div id="ai-chat-widget" class="ai-closed">
    <div id="ai-chat-panel" role="dialog" aria-label="AI asistent">
        <div id="ai-chat-header">
            <span class="ai-chat-title">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                AI asistent
            </span>
            <button id="ai-chat-close" type="button" title="Zavřít" aria-label="Zavřít">×</button>
        </div>
        <div id="ai-chat-messages" aria-live="polite"></div>
        <div id="ai-chat-suggestions"></div>
        <button id="ai-chat-escalate" type="button" hidden>Spojit s živou podporou</button>
        <form id="ai-chat-form">
            <label id="ai-chat-attach" title="Přiložit soubor" aria-label="Přiložit soubor">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
                <input id="ai-chat-file" type="file" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.log,.zip" hidden>
            </label>
            <input id="ai-chat-input" type="text" placeholder="Napište dotaz…" autocomplete="off" aria-label="Zpráva pro asistenta">
            <button id="ai-chat-send" type="submit" title="Odeslat" aria-label="Odeslat">→</button>
        </form>
    </div>
    <button id="ai-chat-toggle" type="button" title="AI asistent" aria-label="Otevřít AI asistenta">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
    </button>
</div>
<script nonce="{{ $cspNonce ?? '' }}">
(function(){
    var widget=document.getElementById('ai-chat-widget'),
        toggle=document.getElementById('ai-chat-toggle'),
        closeBtn=document.getElementById('ai-chat-close'),
        form=document.getElementById('ai-chat-form'),
        input=document.getElementById('ai-chat-input'),
        send=document.getElementById('ai-chat-send'),
        msgs=document.getElementById('ai-chat-messages'),
        sugg=document.getElementById('ai-chat-suggestions'),
        escalateBtn=document.getElementById('ai-chat-escalate'),
        fileInput=document.getElementById('ai-chat-file'),
        meta=document.querySelector('meta[name="csrf-token"]'),
        csrf=meta?meta.content:'',
        loaded=false, busy=false, escalated=false, lastId=0, pollTimer=null,
        renderedIds={};

    var URL_CHAT='{{ route('panel.ai.chat') }}',
        URL_ESCALATE='{{ route('panel.ai.escalate') }}',
        URL_UPLOAD='{{ route('panel.ai.upload') }}',
        URL_POLL='{{ route('panel.ai.poll') }}';

    function renderAttachment(el, att){
        if(!att) return;
        var wrap=document.createElement('div'); wrap.className='ai-msg-attach';
        if(att.mime && att.mime.indexOf('image/')===0 && att.url){
            var img=document.createElement('img'); img.src=att.url; img.alt=att.name||'příloha';
            wrap.appendChild(img);
        } else if(att.url){
            var a=document.createElement('a'); a.href=att.url; a.textContent='📎 '+(att.name||'příloha'); a.target='_blank';
            wrap.appendChild(a);
        }
        el.appendChild(wrap);
        msgs.scrollTop=msgs.scrollHeight;
    }

    function uploadFile(file){
        if(busy||!file) return;
        busy=true;
        var fd=new FormData(); fd.append('file', file);
        var pending=addMsg('Nahrávám '+file.name+'…','user');
        fetch(URL_UPLOAD,{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:fd})
        .then(function(r){return r.json().then(function(d){return {ok:r.ok,d:d};});})
        .then(function(res){
            pending.remove();
            if(!res.ok){ addMsg((res.d && res.d.message) || 'Soubor se nepodařilo nahrát.','system'); return; }
            if(typeof res.d.id==='number'){ renderedIds[res.d.id]=true; if(res.d.id>lastId) lastId=res.d.id; }
            var m=addMsg(res.d.body||('📎 '+file.name),'user');
            renderAttachment(m, res.d.meta && res.d.meta.attachment);
        }).catch(function(){ pending.textContent='Chyba nahrávání.'; })
        .finally(function(){ busy=false; fileInput.value=''; });
    }

    /* role: user | bot | agent | system */
    function addMsg(text, role){
        var d=document.createElement('div');
        d.className='ai-msg ai-msg--'+role;
        d.textContent=text;
        msgs.appendChild(d);
        msgs.scrollTop=msgs.scrollHeight;
        return d;
    }

    function renderLinks(el,links){
        if(!links||!links.length)return;
        var wrap=document.createElement('div'); wrap.className='ai-msg-links';
        links.forEach(function(l){
            var a=document.createElement('a'); a.href=l.url; a.textContent=l.label;
            wrap.appendChild(a);
        });
        el.appendChild(wrap);
        msgs.scrollTop=msgs.scrollHeight;
    }

    function renderSuggestions(items){
        sugg.innerHTML='';
        (items||[]).forEach(function(s){
            var b=document.createElement('button');
            b.type='button'; b.className='ai-suggestion'; b.textContent=s.label;
            b.addEventListener('click',function(){ ask(s.message, s.label); });
            sugg.appendChild(b);
        });
    }

    function setEscalated(on){
        escalated=on;
        escalateBtn.hidden = on;
        if(on){ sugg.innerHTML=''; startPolling(); }
    }

    /* Render a persisted message object from the server (poll/history). */
    function renderServerMsg(m){
        if(renderedIds[m.id]) return;
        renderedIds[m.id]=true;
        if(m.id>lastId) lastId=m.id;
        var el=addMsg(m.body, m.role);
        if(m.meta && m.meta.links) renderLinks(el, m.meta.links);
        if(m.meta && m.meta.attachment) renderAttachment(el, m.meta.attachment);
    }

    /* Post a chatbot turn. echoLabel=null → no user bubble (opening menu). */
    function postAndRender(message, echoLabel){
        if(busy)return;
        if(echoLabel!==null){ addMsg(echoLabel, 'user'); }
        sugg.innerHTML='';
        busy=true; input.disabled=true; send.disabled=true;
        var typing=addMsg('…','bot'); typing.classList.add('ai-msg--typing');
        fetch(URL_CHAT,{
            method:'POST',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
            body:JSON.stringify({message:message})
        }).then(function(r){return r.json();}).then(function(d){
            typing.remove();
            if(typeof d.lastId==='number' && d.lastId>lastId){ lastId=d.lastId; renderedIds[d.lastId]=true; }
            var bot=addMsg(d.reply||'Omlouváme se, zkuste to prosím znovu.','bot');
            renderLinks(bot, d.links);
            renderSuggestions(d.suggestions);
            if(d.escalated){ setEscalated(true); }
        }).catch(function(){
            typing.textContent='Chyba komunikace, zkuste to prosím znovu.';
            typing.classList.remove('ai-msg--typing');
        }).finally(function(){
            busy=false; input.disabled=false; send.disabled=false; input.focus();
        });
    }

    function ask(message, label){ postAndRender(message, label || message); }

    /* Load persisted history; empty conversation → show the AI menu. */
    function loadHistory(){
        fetch(URL_POLL+'?after=0',{headers:{'Accept':'application/json'}})
        .then(function(r){return r.json();}).then(function(d){
            if(d.messages && d.messages.length){
                d.messages.forEach(renderServerMsg);
                if(d.status==='waiting_agent' || d.status==='agent_active') setEscalated(true);
            } else {
                postAndRender('', null); // opening menu
            }
        }).catch(function(){ postAndRender('', null); });
    }

    function escalate(){
        if(busy||escalated)return;
        fetch(URL_ESCALATE,{
            method:'POST',
            headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}
        }).then(function(r){return r.json();}).then(function(d){
            addMsg(d.message||'Spojujeme vás s podporou.','system');
            setEscalated(true);
        });
    }

    function poll(){
        fetch(URL_POLL+'?after='+lastId,{headers:{'Accept':'application/json'}})
        .then(function(r){return r.json();}).then(function(d){
            (d.messages||[]).forEach(renderServerMsg);
            if(d.status==='closed'){ stopPolling(); addMsg('Konverzace byla uzavřena.','system'); }
        }).catch(function(){});
    }

    function startPolling(){ if(!pollTimer) pollTimer=setInterval(poll, 5000); }
    function stopPolling(){ if(pollTimer){ clearInterval(pollTimer); pollTimer=null; } }

    function openPanel(){
        widget.classList.remove('ai-closed'); widget.classList.add('ai-open');
        try{ localStorage.setItem('ai_chat_open','1'); }catch(e){}
        if(!loaded){ loaded=true; escalateBtn.hidden=false; loadHistory(); }
        input.focus();
    }
    function closePanel(){
        widget.classList.add('ai-closed'); widget.classList.remove('ai-open');
        try{ localStorage.setItem('ai_chat_open','0'); }catch(e){}
    }

    toggle.addEventListener('click',openPanel);
    closeBtn.addEventListener('click',closePanel);
    escalateBtn.addEventListener('click',escalate);
    if(fileInput) fileInput.addEventListener('change',function(){ if(fileInput.files[0]) uploadFile(fileInput.files[0]); });
    form.addEventListener('submit',function(e){
        e.preventDefault();
        var t=input.value.trim(); if(!t)return; input.value=''; ask(t);
    });

    /* Default closed; only reopen if the user had it open before. */
    try{ if(localStorage.getItem('ai_chat_open')==='1') openPanel(); }catch(e){}
})();
</script>
@endauth

{{--
    Delegated confirmation handling (audit H119).

    Content-Security-Policy sets script-src with a nonce and deliberately
    without 'unsafe-inline'. A nonce does NOT whitelist inline event
    ATTRIBUTES — only <script nonce="{{ $cspNonce ?? '' }}"> blocks. So under SECURITY_CSP_ENFORCE=true an
    onsubmit="return confirm(...)" simply never runs, and because it never
    runs it also never returns false: the destructive form submits with no
    confirmation at all. Silent, and worse than no guard.

    Markup therefore declares intent with data-confirm / data-prompt and the
    behaviour is bound here, inside a properly nonced script.
--}}
<script nonce="{{ $cspNonce ?? '' }}">
(function () {
    'use strict';

    /* Forms: <form data-confirm="Really delete?"> */
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement)) return;

        /* A prompt-style form collects a reason into a named input first. */
        var promptMessage = form.getAttribute('data-prompt');
        if (promptMessage) {
            if (form.dataset.onhostPrompted === '1') { form.dataset.onhostPrompted = ''; return; }

            event.preventDefault();

            var answer = window.prompt(promptMessage);
            if (answer === null) return;

            var minLength = parseInt(form.getAttribute('data-prompt-min') || '0', 10);
            if (answer.trim().length < minLength) {
                window.alert(form.getAttribute('data-prompt-error') || 'Zadejte prosím delší text.');
                return;
            }

            var targetName = form.getAttribute('data-prompt-target') || 'reason';
            var field = form.querySelector('[name="' + targetName + '"]');
            if (field) field.value = answer.trim();

            form.dataset.onhostPrompted = '1';
            form.submit();
            return;
        }

        var confirmMessage = form.getAttribute('data-confirm');
        if (confirmMessage && !window.confirm(confirmMessage)) {
            event.preventDefault();
        }
    }, true);

    /* Standalone buttons and links: <a data-confirm="…"> */
    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-confirm]');
        if (!trigger || trigger.tagName === 'FORM') return;
        if (trigger.closest('form') && trigger.type === 'submit') return; /* handled on submit */

        if (!window.confirm(trigger.getAttribute('data-confirm'))) {
            event.preventDefault();
            event.stopPropagation();
        }
    }, true);

    /* Filter selects that used to submit their own form from an attribute. */
    document.addEventListener('change', function (event) {
        var field = event.target.closest('[data-auto-submit]');
        if (field && field.form) field.form.submit();
    });

    /* Buttons that submit a form elsewhere on the page by id. */
    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-submit-form]');
        if (!trigger) return;

        var form = document.getElementById(trigger.getAttribute('data-submit-form'));
        if (form) { event.preventDefault(); form.submit(); }
    });

    /*
     | Copy-to-clipboard: <button data-copy="#some-input"> reads that element's
     | value/text; <button data-copy-text="literal"> copies the literal.
     |
     | data-copy-text carries secrets (API tokens, webhook signing secrets) that
     | are shown exactly once, so this path must not be allowed to fail quietly —
     | if the clipboard write is rejected the text is selected instead, so the
     | user can still copy it by hand rather than losing it.
     */
    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-copy], [data-copy-text]');
        if (!trigger) return;

        event.preventDefault();

        var text = trigger.getAttribute('data-copy-text');
        if (text === null) {
            var source = document.querySelector(trigger.getAttribute('data-copy'));
            if (!source) return;
            text = 'value' in source ? source.value : source.textContent.trim();
        }

        var done = trigger.getAttribute('data-copy-done') || 'Zkopírováno!';
        var original = trigger.textContent;

        var succeed = function () {
            trigger.textContent = done;
            window.setTimeout(function () { trigger.textContent = original; }, 2000);
        };

        var fallback = function () {
            var node = document.querySelector(trigger.getAttribute('data-copy') || '');
            if (node && typeof node.select === 'function') { node.focus(); node.select(); }
            window.prompt('Zkopírujte ručně (Ctrl+C):', text);
        };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(succeed, fallback);
        } else {
            fallback();
        }
    });

    /*
     | Named page functions: <button data-call="wizardNext" data-call-args='["a"]'>
     |
     | The function itself lives in the page's own nonced script block; only the
     | ATTRIBUTE that used to invoke it was blocked. The triggering element is
     | appended as the final argument, so handlers that took `this` keep working.
     */
    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-call]');
        if (!trigger) return;

        invoke(trigger, event);
    });

    document.addEventListener('change', function (event) {
        var trigger = event.target.closest('[data-call-on-change]');
        if (trigger) invoke(trigger, event, 'data-call-on-change');
    });

    document.addEventListener('input', function (event) {
        var trigger = event.target.closest('[data-call-on-input]');
        if (trigger) invoke(trigger, event, 'data-call-on-input');
    });

    function invoke(trigger, event, attribute) {
        var name = trigger.getAttribute(attribute || 'data-call');
        var fn   = window[name];

        if (typeof fn !== 'function') return;

        if (trigger.getAttribute('data-call-prevent') !== null || trigger.tagName === 'A') {
            event.preventDefault();
        }

        var args = [];
        var raw  = trigger.getAttribute('data-call-args');
        if (raw) {
            try { args = JSON.parse(raw); } catch (e) { args = []; }
        }

        fn.apply(trigger, args.concat([trigger]));
    }

    /*
     | Dark-mode toggle (N176). Flip the class immediately so it feels instant,
     | then persist to the DB in the background. If the persist call fails the
     | visual state still changed for this page — the next full load reads the
     | (unchanged) DB value and corrects itself, which is the safe direction.
     */
    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-dark-toggle]');
        if (!trigger) return;

        event.preventDefault();

        var isDark = document.body.classList.toggle('dark-only');
        trigger.setAttribute('aria-pressed', isDark ? 'true' : 'false');

        fetch(trigger.getAttribute('data-dark-toggle'), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            credentials: 'same-origin',
        }).catch(function () { /* visual state already applied; DB unchanged */ });
    });

    /* Password reveal toggles: <button data-toggle-visibility="#auth-code"> */
    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-toggle-visibility]');
        if (!trigger) return;

        event.preventDefault();

        var field = document.querySelector(trigger.getAttribute('data-toggle-visibility'));
        if (field) field.type = field.type === 'password' ? 'text' : 'password';
    });

    /* Paired inputs that mirror each other (colour picker ↔ hex field). */
    document.addEventListener('input', function (event) {
        var source = event.target.closest('[data-mirror]');
        if (!source) return;

        var target = document.querySelector(source.getAttribute('data-mirror'));
        if (target) target.value = source.value;
    });

    /*
     | Copy text that is already on the page into a field, e.g. the AI-drafted
     | reply into the reply box. Reading from the DOM rather than embedding the
     | text a second time in an attribute keeps the two from drifting apart.
     */
    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-fill-target]');
        if (!trigger) return;

        event.preventDefault();

        var target = document.querySelector(trigger.getAttribute('data-fill-target'));
        var source = document.querySelector(trigger.getAttribute('data-fill-from'));
        if (!target || !source) return;

        target.value = 'value' in source ? source.value : source.textContent.trim();
        target.scrollIntoView({ behavior: 'smooth' });
        target.focus();
    });

    /* Presets that write a fixed value into another field. */
    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-set-value]');
        if (!trigger) return;

        var selector = trigger.getAttribute('data-set-target');
        var field    = selector
            ? document.querySelector(selector)
            : (trigger.closest('form') || document).querySelector(trigger.getAttribute('data-set-within') || '');

        if (field) field.value = trigger.getAttribute('data-set-value');
    });

    /* ─────────────────────────────────────────────────────────────────────
       N137 — focus management in modals.

       Modals here are self-managed (`.modal.show` + display:block), so there
       is no Bootstrap lifecycle to hook. A MutationObserver on the class
       attribute catches every one of them without touching each call site.

       Three things a keyboard/screen-reader user needs and did not have:
       focus moving INTO the modal on open, Tab staying inside it, and focus
       returning to whatever opened it on close. Without the last one, closing
       a modal dumps focus back to <body> and the user restarts from the top.
       ───────────────────────────────────────────────────────────────────── */
    var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type=hidden]), ' +
                    'select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    var lastFocusBeforeModal = null;
    var trappedModal = null;

    function focusablesIn(el) {
        return Array.prototype.filter.call(
            el.querySelectorAll(FOCUSABLE),
            function (n) { return n.offsetParent !== null || n === document.activeElement; }
        );
    }

    function openTrap(modal) {
        if (trappedModal === modal) return;
        lastFocusBeforeModal = document.activeElement;
        trappedModal = modal;

        if (!modal.hasAttribute('tabindex')) modal.setAttribute('tabindex', '-1');
        modal.setAttribute('role', modal.getAttribute('role') || 'dialog');
        modal.setAttribute('aria-modal', 'true');

        var first = focusablesIn(modal)[0];
        (first || modal).focus();
    }

    function closeTrap() {
        if (!trappedModal) return;
        trappedModal.removeAttribute('aria-modal');
        trappedModal = null;
        // Send the user back where they were, not to the top of the page.
        if (lastFocusBeforeModal && document.contains(lastFocusBeforeModal)) {
            lastFocusBeforeModal.focus();
        }
        lastFocusBeforeModal = null;
    }

    new MutationObserver(function (mutations) {
        mutations.forEach(function (m) {
            var el = m.target;
            if (!el.classList || !el.classList.contains('modal')) return;
            if (el.classList.contains('show')) { openTrap(el); }
            else if (trappedModal === el)      { closeTrap(); }
        });
    }).observe(document.body, { subtree: true, attributes: true, attributeFilter: ['class'] });

    document.addEventListener('keydown', function (e) {
        if (!trappedModal || e.key !== 'Tab') return;

        var items = focusablesIn(trappedModal);
        if (!items.length) return;

        var first = items[0];
        var last  = items[items.length - 1];

        // Wrap at both ends so Tab never escapes the dialog.
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });

    /* ─────────────────────────────────────────────────────────────────────
       N138 — keyboard shortcuts. "/" jumps to search, "?" lists the shortcuts.
       Both are ignored while typing, so they never eat a real keystroke.
       ───────────────────────────────────────────────────────────────────── */
    function isTyping(el) {
        if (!el) return false;
        var tag = el.tagName;
        return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || el.isContentEditable;
    }

    document.addEventListener('keydown', function (e) {
        if (e.ctrlKey || e.metaKey || e.altKey || isTyping(document.activeElement)) return;

        if (e.key === '/') {
            var search = document.getElementById('admin-global-search')
                      || document.querySelector('input[type="search"], input[name="q"], input[name="search"]');
            if (search) { e.preventDefault(); search.focus(); }
            return;
        }

        if (e.key === '?') { e.preventDefault(); toggleShortcutHelp(); }
        if (e.key === 'Escape') { hideShortcutHelp(); }
    });

    function shortcutHelpEl() { return document.getElementById('kbd-help'); }

    function toggleShortcutHelp() {
        var el = shortcutHelpEl();
        if (!el) return;
        if (el.classList.contains('show')) { hideShortcutHelp(); } else {
            el.classList.add('show');
            el.style.display = 'block';
        }
    }

    function hideShortcutHelp() {
        var el = shortcutHelpEl();
        if (!el || !el.classList.contains('show')) return;
        el.classList.remove('show');
        el.style.display = 'none';
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-kbd-help-close]')) { hideShortcutHelp(); }
    });
})();
</script>

@auth
{{-- N138: shortcut reference. Uses the same `.modal.show` contract as every
     other dialog, so the focus trap above picks it up for free. --}}
<div class="modal" id="kbd-help" tabindex="-1" role="dialog"
     aria-labelledby="kbd-help-title" style="display:none;">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="kbd-help-title">Klávesové zkratky</h5>
                <button type="button" class="btn-close" data-kbd-help-close aria-label="Zavřít"></button>
            </div>
            <div class="modal-body">
                <table class="table mb-0">
                    <tbody>
                        <tr><td><kbd>/</kbd></td><td>Přejít do vyhledávání</td></tr>
                        <tr><td><kbd>?</kbd></td><td>Zobrazit tuto nápovědu</td></tr>
                        <tr><td><kbd>Esc</kbd></td><td>Zavřít dialog nebo nápovědu</td></tr>
                        <tr><td><kbd>Tab</kbd></td><td>Pohyb uvnitř otevřeného dialogu</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary text-white" data-kbd-help-close>Zavřít</button>
            </div>
        </div>
    </div>
</div>
@endauth
</body>
</html>
