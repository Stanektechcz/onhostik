{{-- Service-worker registration + install prompt (A2HS).

     Shared by the panel and auth layouts: installability is judged on the page
     the user is actually looking at, and logged-out users land on the login
     page, so registering only inside the panel would make the app
     un-installable until after sign-in.

     CSP-safe: nonced block, addEventListener only, no inline handlers. --}}

<div id="pwa-install" class="hidden" style="position:fixed;bottom:18px;left:18px;z-index:1040;">
    <button type="button" id="pwa-install-btn" class="btn btn-primary btn-sm text-white shadow">
        Nainstalovat aplikaci
    </button>
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
    var wrap = document.getElementById('pwa-install');
    var btn  = document.getElementById('pwa-install-btn');

    // Browsers fire this only once the install criteria are met.
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferred = e;
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

    window.addEventListener('appinstalled', function () {
        if (wrap) { wrap.classList.add('hidden'); }
    });
})();
</script>
