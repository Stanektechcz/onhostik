@extends('layouts.panel')

@php
    use App\Domains\Communication\Support\NotificationCatalog;

    $breadcrumbTitle = 'Notifikace';
    $breadcrumbItems = [__('panel.nav.account') => '#', 'Notifikace' => ''];

    $typeIcons = [
        'security'         => 'shield',
        'new_ip_login'     => 'map-pin',
        'account'          => 'user',
        'invoice'          => 'file-text',
        'payment'          => 'credit-card',
        'credit'           => 'dollar-sign',
        'service'          => 'server',
        'service_critical' => 'alert-octagon',
        'renewal'          => 'refresh-cw',
        'monitor'          => 'activity',
        'backup'           => 'archive',
        'maintenance'      => 'tool',
        'support'          => 'message-circle',
        'digest'           => 'inbox',
        'marketing'        => 'star',
    ];
    $channelLabels = ['mail' => 'E-mail', 'database' => 'V aplikaci'];
    $channelIcons  = ['mail' => 'mail', 'database' => 'bell'];
@endphp

@section('title', 'Předvolby notifikací')

@section('content')
<div class="container-fluid">
    <x-panel.flash />

    <div class="container">
        <form method="POST" action="{{ route('panel.account.notification-preferences.update') }}">
            @csrf @method('PUT')

            <div class="card">
                <div class="card-header card-no-border">
                    <div class="header-top">
                        <h5>Předvolby notifikací</h5>
                        <p class="f-m-light mt-1">
                            Zvolte, co chcete dostávat a jakým kanálem. Několik typů je
                            povinných — ty vypnout nelze a jsou označené zámkem.
                        </p>
                    </div>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:50%">Typ notifikace</th>
                                    @foreach ($channels as $channel)
                                        <th class="text-center">
                                            <i data-feather="{{ $channelIcons[$channel] ?? 'bell' }}" class="me-1" style="width:14px;height:14px;"></i>
                                            {{ $channelLabels[$channel] ?? $channel }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($grouped as $groupName => $groupTypes)
                                    <tr class="table-light">
                                        <td colspan="{{ count($channels) + 1 }}" class="f-w-600 f-12 uppercase f-light">
                                            {{ $groupName }}
                                        </td>
                                    </tr>

                                    @foreach ($groupTypes as $type => $meta)
                                        <tr>
                                            <td>
                                                <div class="flex items-start gap-2">
                                                    <i data-feather="{{ $typeIcons[$type] ?? 'bell' }}" style="width:16px;height:16px;flex-shrink:0;" class="text-primary mt-1"></i>
                                                    <div>
                                                        <div class="f-w-500 flex items-center gap-2">
                                                            {{ $meta['label'] }}
                                                            @if ($meta['mandatory'])
                                                                <span class="badge badge-light-secondary f-10">
                                                                    <i data-feather="lock" style="width:10px;height:10px;"></i>
                                                                    Povinné
                                                                </span>
                                                            @endif
                                                        </div>
                                                        <div class="f-12 f-light">{{ $meta['description'] }}</div>
                                                    </div>
                                                </div>
                                            </td>

                                            @foreach ($channels as $channel)
                                                @php
                                                    $isOptIn = NotificationCatalog::isOptIn($type, $channel);

                                                    if ($isOptIn) {
                                                        // Off unless explicitly asked for.
                                                        $checked = in_array($type, (array) ($prefs['opt_in'][$channel] ?? []), true);
                                                        $locked  = false;
                                                    } elseif ($meta['mandatory']) {
                                                        $checked = true;
                                                        $locked  = true;
                                                    } else {
                                                        $checked = ! in_array($type, (array) ($prefs[$channel] ?? []), true);
                                                        $locked  = false;
                                                    }
                                                @endphp
                                                <td class="text-center">
                                                    <div class="form-check flex justify-center m-0">
                                                        <input class="form-check-input"
                                                               type="checkbox"
                                                               name="{{ $channel }}[]"
                                                               value="{{ $type }}"
                                                               aria-label="{{ $meta['label'] }} — {{ $channelLabels[$channel] ?? $channel }}"
                                                               @checked($checked)
                                                               @disabled($locked)>
                                                    </div>
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 flex gap-2 items-center">
                        <button type="submit" class="btn btn-primary text-white">
                            <i data-feather="save" class="me-1" style="width:14px;height:14px;"></i>
                            Uložit předvolby
                        </button>
                        <a href="{{ route('panel.account.security') }}" class="btn btn-light">
                            Zrušit
                        </a>
                    </div>
                </div>
            </div>

        </form>

        {{-- Browser push (audit 92) --}}
        <div class="card mt-4" id="push-card" data-key-url="{{ route('panel.push.key') }}"
             data-subscribe-url="{{ route('panel.push.subscribe') }}"
             data-unsubscribe-url="{{ route('panel.push.unsubscribe') }}">
            <div class="card-header card-no-border">
                <div class="header-top">
                    <h5>Oznámení v prohlížeči</h5>
                    <p class="f-m-light mt-1">
                        Dostávejte upozornění přímo na plochu, i když zrovna nemáte panel otevřený.
                        Fungují na tomto zařízení a prohlížeči; kdykoli je můžete zase vypnout.
                    </p>
                </div>
            </div>
            <div class="card-body">
                {{-- Shown when the browser cannot do web push. --}}
                <div id="push-unsupported" class="alert alert-light-secondary hidden" role="alert">
                    <i data-feather="alert-circle" class="me-1" style="width:16px;height:16px;"></i>
                    Tento prohlížeč oznámení na plochu nepodporuje.
                </div>
                {{-- Shown when an operator has not configured VAPID keys yet. --}}
                <div id="push-disabled" class="alert alert-light-secondary hidden" role="alert">
                    <i data-feather="alert-circle" class="me-1" style="width:16px;height:16px;"></i>
                    Oznámení v prohlížeči zatím nejsou k dispozici.
                </div>

                <div id="push-controls" class="hidden flex gap-2 items-center">
                    <button type="button" id="push-enable" class="btn btn-primary text-white">
                        <i data-feather="bell" class="me-1" style="width:14px;height:14px;"></i>
                        Zapnout oznámení
                    </button>
                    <button type="button" id="push-disable" class="btn btn-light hidden">
                        <i data-feather="bell-off" class="me-1" style="width:14px;height:14px;"></i>
                        Vypnout oznámení
                    </button>
                    <span id="push-status" class="f-12 f-light" role="status" aria-live="polite"></span>
                </div>
            </div>
        </div>

        {{-- Info card --}}
        <div class="card mt-4">
            <div class="card-body">
                <div class="flex gap-3 items-start">
                    <i data-feather="info" class="text-info mt-1" style="width:18px;height:18px;flex-shrink:0;"></i>
                    <div>
                        <strong>Jak notifikace fungují</strong>
                        <ul class="mt-2 mb-0 ps-3 f-m-light">
                            <li><strong>E-mail</strong> — zprávy přicházejí na vaši registrovanou e-mailovou adresu.</li>
                            <li><strong>V aplikaci</strong> — notifikace se zobrazují v ikoně zvonku v záhlaví panelu.</li>
                            <li><strong>Povinné typy</strong> (zámek) nelze vypnout — jde o zabezpečení účtu
                                a kritické stavy služeb, kde by mlčení samo o sobě bylo škodou.</li>
                            <li><strong>Zabezpečení účtu e-mailem</strong> je naopak ve výchozím stavu vypnuté;
                                zapněte si ho, pokud chcete o přihlášení z neznámé adresy vědět i mimo aplikaci.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
{{-- Browser web-push opt-in (audit 92). CSP-safe: nonced block, addEventListener,
     route URLs from data-* on #push-card. No secrets here — only the public VAPID key. --}}
<script nonce="{{ $cspNonce ?? '' }}">
(function () {
    var card = document.getElementById('push-card');
    if (!card) { return; }

    var elUnsupported = document.getElementById('push-unsupported');
    var elDisabled    = document.getElementById('push-disabled');
    var elControls    = document.getElementById('push-controls');
    var btnEnable     = document.getElementById('push-enable');
    var btnDisable    = document.getElementById('push-disable');
    var elStatus      = document.getElementById('push-status');

    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    function show(el) { if (el) { el.classList.remove('hidden'); } }
    function hide(el) { if (el) { el.classList.add('hidden'); } }
    function status(text) { if (elStatus) { elStatus.textContent = text || ''; } }

    // Base64url → Uint8Array, as the PushManager wants the applicationServerKey.
    function urlB64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var raw = window.atob(base64);
        var out = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) { out[i] = raw.charCodeAt(i); }
        return out;
    }

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(body || {}),
        });
    }

    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        show(elUnsupported);
        return;
    }

    var publicKey = null;

    fetch(card.getAttribute('data-key-url'), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (cfg) {
            if (!cfg || !cfg.enabled || !cfg.publicKey) {
                show(elDisabled);
                return;
            }
            publicKey = cfg.publicKey;
            show(elControls);
            return navigator.serviceWorker.register('/sw.js').then(function (reg) {
                return reg.pushManager.getSubscription().then(function (sub) {
                    reflect(!!sub);
                });
            });
        })
        .catch(function () { show(elDisabled); });

    function reflect(subscribed) {
        if (subscribed) { hide(btnEnable); show(btnDisable); status('Oznámení jsou zapnutá na tomto zařízení.'); }
        else { show(btnEnable); hide(btnDisable); status(''); }
    }

    function enable() {
        if (!publicKey) { return; }
        btnEnable.setAttribute('disabled', 'disabled');
        status('Žádám o povolení…');
        Notification.requestPermission().then(function (perm) {
            if (perm !== 'granted') { btnEnable.removeAttribute('disabled'); status('Povolení bylo zamítnuto v prohlížeči.'); return; }
            return navigator.serviceWorker.ready.then(function (reg) {
                return reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlB64ToUint8Array(publicKey),
                });
            }).then(function (sub) {
                var json = sub.toJSON();
                return post(card.getAttribute('data-subscribe-url'), {
                    endpoint: sub.endpoint,
                    publicKey: json.keys ? json.keys.p256dh : '',
                    authToken: json.keys ? json.keys.auth : '',
                });
            }).then(function () { reflect(true); }).finally(function () { btnEnable.removeAttribute('disabled'); });
        }).catch(function () { btnEnable.removeAttribute('disabled'); status('Oznámení se nepodařilo zapnout.'); });
    }

    function disable() {
        btnDisable.setAttribute('disabled', 'disabled');
        navigator.serviceWorker.ready.then(function (reg) {
            return reg.pushManager.getSubscription().then(function (sub) {
                if (!sub) { reflect(false); return; }
                var endpoint = sub.endpoint;
                return sub.unsubscribe().then(function () {
                    return post(card.getAttribute('data-unsubscribe-url'), { endpoint: endpoint });
                }).then(function () { reflect(false); });
            });
        }).catch(function () { status('Oznámení se nepodařilo vypnout.'); })
          .finally(function () { btnDisable.removeAttribute('disabled'); });
    }

    if (btnEnable) { btnEnable.addEventListener('click', enable); }
    if (btnDisable) { btnDisable.addEventListener('click', disable); }
})();
</script>
@endpush
