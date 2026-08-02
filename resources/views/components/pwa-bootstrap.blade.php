{{-- Service-worker registration + install prompt (A2HS).

     Shared by the panel and auth layouts: installability is judged on the page
     the user is actually looking at, and logged-out users land on the login
     page, so registering only inside the panel would leave the app
     un-installable until after sign-in.

     The prompt is a plain Cuba dismissible alert in normal flow — no custom CSS
     and no floating element covering content. It stays hidden until the browser
     confirms the install criteria are met.

     CSP-safe: nonced block, addEventListener only, no inline handlers. --}}

<div class="container-fluid hidden" id="pwa-install">
    <div class="alert alert-light-primary alert-dismissible flex items-center gap-2" role="alert">
        <i data-feather="download" style="width:16px;height:16px;"></i>
        <span class="flex-1">
            Panel si můžete nainstalovat jako aplikaci — otevře se ve vlastním okně a funguje i offline.
        </span>
        <button type="button" class="btn btn-primary btn-sm text-white" id="pwa-install-btn">
            Nainstalovat
        </button>
        <button type="button" class="btn-close" id="pwa-install-dismiss" aria-label="Zavřít"></button>
    </div>
</div>

<script nonce="{{ $cspNonce ?? '' }}">
(function () {
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js').then(function (reg) {
                // A worker installed after a deploy should take over at once,
                // otherwise users keep being served the previous build.
                if (reg.waiting) { reg.waiting.postMessage('skip-waiting'); }
                reg.addEventListener('updatefound', function () {
                    var sw = reg.installing;
                    if (!sw) { return; }
                    sw.addEventListener('statechange', function () {
                        if (sw.state === 'installed' && navigator.serviceWorker.controller) {
                            sw.postMessage('skip-waiting');
                        }
                    });
                });
            }).catch(function () { /* The SW is an enhancement; never block the page. */ });
        });
    }

    var deferred = null;
    var wrap    = document.getElementById('pwa-install');
    var btn     = document.getElementById('pwa-install-btn');
    var dismiss = document.getElementById('pwa-install-dismiss');

    // Respect an earlier dismissal instead of nagging on every page view.
    var DISMISS_KEY = 'onhost-pwa-dismissed';

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferred = e;

        try { if (localStorage.getItem(DISMISS_KEY)) { return; } } catch (err) { /* private mode */ }

        if (wrap) { wrap.classList.remove('hidden'); }
    });

    if (btn) {
        btn.addEventListener('click', function () {
            if (!deferred) { return; }
            deferred.prompt();
            deferred.userChoice.finally(function () {
                deferred = null;
                if (wrap) { wrap.classList.add('hidden'); }
            });
        });
    }

    if (dismiss) {
        dismiss.addEventListener('click', function () {
            if (wrap) { wrap.classList.add('hidden'); }
            try { localStorage.setItem(DISMISS_KEY, '1'); } catch (err) { /* ignore */ }
        });
    }

    window.addEventListener('appinstalled', function () {
        if (wrap) { wrap.classList.add('hidden'); }
    });
})();
</script>
