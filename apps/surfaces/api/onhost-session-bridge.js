/* onhost-session-bridge.js — production seam between the server session and the prototype shell.
 *
 * The shell (onhost-shell.js) keeps the signed-in user and the active role in localStorage.
 * In production the server injects `window.ONHOST = { apiBase, csrf, user, surface, demo, hash }`
 * before the shell loads; this script mirrors that into the shell's storage keys so every
 * surface renders the real session, locks the demo role switcher unless ONHOST.demo is on, and
 * turns the public surface's sign-in / registration forms into real API calls.
 * Nothing visual changes. Loaded right after the ONHOST boot object, before onhost-shell.js.
 */
(function () {
  'use strict';
  // The prototype runtime re-executes <helmet> scripts once more after boot (that is why onhost-shell.js and
  // onhost-store.js guard on their globals); without this guard every listener below would be registered twice.
  if (window.__onhostBridge) return;
  window.__onhostBridge = true;
  var B = window.ONHOST || {};
  var K = { session: 'onhost.session', role: 'onhost.role', cart: 'onhost.cart', ops: 'onhost.ops' };

  /* 0. Cloudflare Turnstile (audit §5q-6): rendered once when the boot object carries a site key; its token rides on
   * registration and checkout as `turnstile`. Without a key nothing loads and the payloads carry no field. */
  var turnstileId = null;
  function turnstileToken() { try { return turnstileId !== null && window.turnstile ? (window.turnstile.getResponse(turnstileId) || undefined) : undefined; } catch (e) { return undefined; } }
  function turnstileReset() { try { if (turnstileId !== null && window.turnstile) window.turnstile.reset(turnstileId); } catch (e) {} }
  if (B.turnstile && !B.demo) {
    var mountTurnstile = function () {
      if (turnstileId !== null || !window.turnstile || !document.body) return;
      var host = document.createElement('div'); host.id = 'onhost-turnstile'; host.style.cssText = 'position:fixed;right:12px;bottom:12px;z-index:9999';
      document.body.appendChild(host);
      turnstileId = window.turnstile.render(host, { sitekey: B.turnstile, appearance: 'interaction-only', theme: 'light' });
    };
    var ts = document.createElement('script'); ts.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit'; ts.async = true; ts.defer = true;
    ts.onload = function () { if (document.body) mountTurnstile(); else document.addEventListener('DOMContentLoaded', mountTurnstile); };
    document.head.appendChild(ts);
  }

  function write(key, val) {
    try { if (val == null) localStorage.removeItem(key); else localStorage.setItem(key, JSON.stringify(val)); } catch (e) {}
  }

  /* 1. session from the server (never from a stale localStorage copy) */
  function dropForeignHandoff(email) {
    try {
      var ho = JSON.parse(localStorage.getItem('onhost.handoff') || 'null');
      if (ho && (!email || String(ho.email || '').toLowerCase() !== String(email).toLowerCase())) localStorage.removeItem('onhost.handoff');
    } catch (e) {}
  }
  if (B.user) {
    dropForeignHandoff(B.user.email); // the last web order belongs to the account that placed it, not to the browser
    write(K.session, { email: B.user.email, name: B.user.name, since: Date.now(), id: B.user.id, org: B.user.organization ? B.user.organization.id : null, staff: !!B.user.staff });
    write(K.role, B.user.role || 'klient');
  } else if (!B.demo) {
    dropForeignHandoff(null);
    write(K.session, null);
    write(K.role, 'klient');
  }

  /* 1b. the shell's client-side role gate keys on the prototype file name in the URL; in production the
   *     server already decides who may open /panel, /sprava, /partner, so the client gate is switched off. */
  if (!B.demo) window.__ohGateOff = true;

  /* 2. deep link: /panel/sluzby → #/sluzby (hash routing stays inside the surface) */
  if (B.hash && !location.hash) {
    try { history.replaceState(null, '', location.pathname + location.search + B.hash); } catch (e) { location.hash = B.hash; }
  }

  /* 3. API helper shared by the API-backed data modules */
  window.OnhostApi = window.OnhostApi || (function () {
    var base = (B.apiBase || '/v1').replace(/\/$/, '');
    function headers(extra) {
      var h = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept-Language': (document.documentElement.lang || 'cs').indexOf('en') === 0 ? 'en' : 'cs' };
      if (B.csrf) h['X-XSRF-TOKEN'] = B.csrf;
      if (B.user && B.user.organization) h['X-Organization'] = B.user.organization.id;
      var m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
      if (m) h['X-XSRF-TOKEN'] = decodeURIComponent(m[1]);
      for (var k in (extra || {})) h[k] = extra[k];
      return h;
    }
    function call(method, path, body, extra, retried) {
      var opts = { method: method, credentials: 'same-origin', headers: headers(extra) };
      var guarded = method === 'POST' && /^\/(leads|tender\/request|reseller\/apply)(\?|$)/.test(path); // §5r-6: the public forms carry the Turnstile token too
      if (guarded && body && !(typeof FormData !== 'undefined' && body instanceof FormData) && body.turnstile === undefined) { body.turnstile = turnstileToken(); setTimeout(turnstileReset, 0); }
      if (body !== undefined) {
        if (typeof FormData !== 'undefined' && body instanceof FormData) opts.body = body; // multipart uploads: the browser sets the boundary
        else { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
      }
      return fetch(base + path, opts).then(function (r) {
        return r.text().then(function (t) {
          var j = null; try { j = t ? JSON.parse(t) : null; } catch (e) { j = { raw: t }; }
          if (!r.ok && r.status === 403 && j && j.error === 'step_up_required' && !retried && B.user) {
            return stepUpDialog().then(function () { return call(method, path, body, extra, true); });
          }
          if (!r.ok) { var err = new Error((j && (j.message || j.error)) || ('HTTP ' + r.status)); err.status = r.status; err.body = j; throw err; }
          j = j || {}; j.__total = parseInt(r.headers.get('X-Total-Count') || '0', 10) || (j.data ? j.data.length : 0);
          return j;
        });
      });
    }
    /* Step-up ("sudo mode"): API keys, team changes, terminations or domain transfers need a fresh proof of identity.
     * The customer confirms with the password — or the authenticator / recovery code once 2FA is on — the grant is
     * stored on the session by POST /auth/step-up and the original request is sent again. One dialog at a time. */
    var stepUpPending = null;
    function stepUpDialog() {
      if (stepUpPending) return stepUpPending;
      var cs = (document.documentElement.lang || 'cs').indexOf('en') !== 0, mfa = !!(B.user && B.user.mfa);
      stepUpPending = new Promise(function (resolve, reject) {
        var wrap = document.createElement('div');
        wrap.setAttribute('role', 'dialog'); wrap.setAttribute('aria-modal', 'true'); wrap.setAttribute('aria-labelledby', 'onhost-stepup-title');
        wrap.style.cssText = 'position:fixed;inset:0;z-index:130;display:flex;align-items:center;justify-content:center;background:rgba(20,18,17,.55);font-family:inherit';
        var field = 'font:inherit;font-size:15px;padding:10px 12px;border:1px solid color-mix(in srgb,var(--fg,#201e1d) 30%,transparent);border-radius:8px;background:transparent;color:inherit';
        var btn = 'font:inherit;font-size:13.5px;padding:9px 14px;border-radius:8px;cursor:pointer;';
        wrap.innerHTML = '<form style="background:var(--bg,#fff);color:var(--fg,#201e1d);width:min(420px,calc(100vw - 32px));padding:24px;border:1px solid color-mix(in srgb,var(--fg,#201e1d) 15%,transparent);border-radius:12px;box-shadow:0 24px 60px rgba(0,0,0,.28);display:flex;flex-direction:column;gap:12px">'
          + '<div id="onhost-stepup-title" style="font-weight:700;font-size:17px">' + (cs ? 'Potvrďte, že jste to vy' : 'Confirm it is you') + '</div>'
          + '<div style="font-size:13.5px;line-height:1.5;opacity:.85">' + (mfa ? (cs ? 'Tato akce vyžaduje ověření. Zadejte kód z autentikátoru nebo jeden ze záložních kódů.' : 'This action needs verification. Enter the code from your authenticator or one of the recovery codes.') : (cs ? 'Tato akce vyžaduje ověření. Zadejte prosím své heslo.' : 'This action needs verification. Please enter your password.')) + '</div>'
          + '<label style="display:flex;flex-direction:column;gap:6px;font-size:12px;font-weight:600;letter-spacing:.04em;text-transform:uppercase">' + (mfa ? (cs ? 'Kód' : 'Code') : (cs ? 'Heslo' : 'Password'))
          + '<input name="code" type="' + (mfa ? 'text' : 'password') + '" autocomplete="' + (mfa ? 'one-time-code' : 'current-password') + '" inputmode="' + (mfa ? 'numeric' : 'text') + '" required style="' + field + '"></label>'
          + '<div data-err role="alert" style="display:none;color:#8f1c0a;font-size:13px"></div>'
          + '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:4px">'
          + '<button type="button" data-cancel style="' + btn + 'border:1px solid color-mix(in srgb,var(--fg,#201e1d) 30%,transparent);background:transparent;color:inherit">' + (cs ? 'Zrušit' : 'Cancel') + '</button>'
          + '<button type="submit" style="' + btn + 'font-weight:600;border:1px solid var(--acc,#ec3013);background:var(--acc,#ec3013);color:#fff">' + (cs ? 'Potvrdit' : 'Confirm') + '</button>'
          + '</div></form>';
        document.body.appendChild(wrap);
        var form = wrap.querySelector('form'), input = wrap.querySelector('input'), err = wrap.querySelector('[data-err]'), submit = wrap.querySelector('[type=submit]');
        function close() { wrap.remove(); document.removeEventListener('keydown', onKey, true); stepUpPending = null; }
        function cancel() { close(); var e = new Error(cs ? 'Ověření zrušeno.' : 'Verification cancelled.'); e.cancelled = true; reject(e); }
        function onKey(ev) { if (ev.key === 'Escape') { ev.stopPropagation(); cancel(); } }
        document.addEventListener('keydown', onKey, true);
        wrap.querySelector('[data-cancel]').addEventListener('click', cancel);
        form.addEventListener('submit', function (ev) {
          ev.preventDefault(); ev.stopPropagation();
          var code = input.value.trim();
          if (!code) { input.focus(); return; }
          var method = mfa ? (/^[0-9]{6,8}$/.test(code) ? 'totp' : 'recovery') : 'password';
          submit.disabled = true;
          call('POST', '/auth/step-up', { method: method, code: code }, undefined, true).then(function () { close(); resolve(true); }).catch(function (e) {
            submit.disabled = false;
            err.style.display = 'block';
            err.textContent = (e.body && e.body.error === 'step_up_failed') ? (cs ? 'Ověření se nezdařilo — zkontrolujte zadání a zkuste to znovu.' : 'Verification failed — check the input and try again.') : (e.message || 'Error');
            input.focus(); input.select();
          });
        });
        setTimeout(function () { input.focus(); }, 30);
      });
      return stepUpPending;
    }
    return {
      base: base,
      stepUp: stepUpDialog,
      get: function (p) { return call('GET', p); },
      post: function (p, b, key) { return call('POST', p, b || {}, key ? (typeof key === 'object' ? key : { 'Idempotency-Key': key }) : undefined); }, // key: an Idempotency-Key or a header map
      upload: function (p, form) { return call('POST', p, form); },
      put: function (p, b, extra) { return call('PUT', p, b || {}, extra); },
      patch: function (p, b) { return call('PATCH', p, b || {}); },
      del: function (p) { return call('DELETE', p); },
      staff: function () { return !!(B.user && B.user.staff); },
      user: function () { return B.user || null; },
      key: function () { return 'ui-' + Date.now().toString(36) + Math.random().toString(36).slice(2, 8); }
    };
  })();

  /* 4. real sign-in / registration. The prototype's auth card only wrote localStorage; its submit button is
   *    type="button" with a React onClick, so clicks and Enter are intercepted in the capture phase (before React),
   *    the API is called, and on success the session is written the way the prototype does and the panel opens.
   *    Errors reuse the prototype's error style. */
  function authView() {
    var h = String(location.hash || '');
    if (/^#\/(prihlaseni|login)/.test(h)) return 'login';
    if (/^#\/(registrace|register)/.test(h)) return 'register';
    if (/^#\/(obnova-hesla|reset)/.test(h)) return 'reset';
    return null;
  }
  /* The auth card is the smallest container around the e-mail input that also holds a button. Only clicks inside it
   * are intercepted — the header's menu buttons (Produkty …) sit outside and keep working on the sign-in pages. */
  function authCard(el) {
    var email = document.querySelector('main input[type=email]') || document.querySelector('input[type=email]');
    if (!email) return null;
    var node = email.parentElement, depth = 0;
    while (node && node !== document.body && depth < 8) {
      if (node.querySelector && node.querySelector('button')) return node.contains(el) ? node : null;
      node = node.parentElement; depth++;
    }
    return null;
  }
  function isAuthSubmit(btn) {
    return /btn-primary/.test(btn.className || '') || /přihlás|založ|registr|odeslat|obnov|sign in|create|send|reset/i.test(btn.textContent || '');
  }
  function showError(card, text) {
    var old = card.querySelector('[data-onhost-error]');
    if (old) old.remove();
    var span = document.createElement('span');
    span.setAttribute('data-onhost-error', '1');
    span.setAttribute('role', 'alert');
    span.style.cssText = 'display:block;font-size:12px;line-height:1.4;color:var(--accDeep,#ae1800);margin-top:8px';
    span.textContent = text;
    var pass = card.querySelector('input[type=password]') || card.querySelector('input[type=email]');
    ((pass && pass.parentNode) || card).appendChild(span);
  }
  function setBusy(card, busy) {
    card.querySelectorAll('button').forEach(function (btn) { btn.disabled = !!busy; btn.style.opacity = busy ? '.6' : ''; });
  }
  var inFlight = false;
  function handleAuth(card, view) {
    if (inFlight) return;
    var email = (card.querySelector('input[type=email]') || {}).value || '';
    var passInputs = card.querySelectorAll('input[type=password]');
    var pass = passInputs[0] ? passInputs[0].value : '';
    var pass2 = passInputs[1] ? passInputs[1].value : null;
    var nameInput = card.querySelector('input[type=text], input:not([type])'); // the prototype's name field carries no type attribute
    var name = nameInput ? nameInput.value : '';
    var A = window.OnhostApi;
    if (!email) { showError(card, 'Zadejte e-mail.'); return; }
    if (view === 'login' && !pass) { showError(card, 'Zadejte heslo.'); return; }
    var termsBox = card.querySelector('input[type=checkbox]');
    if (view === 'register') {
      if (pass.length < 12 || !/[0-9]/.test(pass) || !/[a-zA-Z]/.test(pass)) { showError(card, 'Heslo musí mít alespoň 12 znaků, písmena i číslice.'); return; }
      if (pass2 !== null && pass2 !== pass) { showError(card, 'Hesla se neshodují.'); return; }
      if (termsBox && !termsBox.checked) { showError(card, 'Bez souhlasu s podmínkami účet nezaložíme.'); return; }
    }
    inFlight = true;
    setBusy(card, true);
    var next = (function () { try { return new URLSearchParams(location.search).get('next'); } catch (x) { return null; } })();
    var ref = (function () { try { return new URLSearchParams(location.search).get('ref') || localStorage.getItem('onhost.ref'); } catch (x) { return null; } })();
    var req = function () {
      return view === 'login'
        ? A.post('/auth/login', { email: email.trim(), password: pass, remember: true })
        : view === 'register'
          ? A.post('/auth/register', { name: name.trim() || email.split('@')[0], email: email.trim(), password: pass, terms: true, partner_code: ref || undefined, turnstile: turnstileToken() })
          : A.post('/auth/password/reset', { email: email.trim() });
    };
    fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' }).catch(function () {}).then(req).then(function (r) {
      turnstileReset(); // a token is single-use; the next form submit needs a fresh one
      var u = (r && r.data && (r.data.user || r.data)) || {};
      inFlight = false;
      if (view === 'reset') { showError(card, 'Pokud účet existuje, poslali jsme odkaz na obnovu hesla.'); setBusy(card, false); return; }
      write(K.session, { email: u.email || email.trim(), name: u.name || name || email.split('@')[0], since: Date.now(), id: u.id });
      var staff = !!(u.staff || u.is_staff);
      write(K.role, staff ? 'admin' : 'klient');
      location.href = next && /^\/(panel|sprava|partner|m|kosik)(\/|$)/.test(next) ? next : (staff ? '/sprava' : '/panel');
    }).catch(function (err) {
      inFlight = false;
      setBusy(card, false);
      var body = err && err.body;
      var msg = (body && body.message) || 'Přihlášení se nezdařilo.';
      if (body && body.error === 'mfa_required') msg = 'Účet má zapnuté dvoufázové ověření — zadejte kód z autentikátoru do pole hesla za heslo oddělené mezerou.';
      if (body && body.errors) { var first = Object.keys(body.errors)[0]; if (first && body.errors[first][0]) msg = body.errors[first][0]; }
      showError(card, msg);
    });
  }
  function intercept(e) {
    if (B.demo || B.surface !== 'public') return;
    var view = authView();
    if (!view) return;
    var target = e.target;
    if (e.type === 'click') {
      var btn = target && target.closest ? target.closest('button') : null;
      if (!btn) return;
      var card = authCard(btn);
      if (!card || !isAuthSubmit(btn)) return;
      e.preventDefault(); e.stopImmediatePropagation();
      handleAuth(card, view);
    } else if (e.type === 'keydown') {
      if (e.key !== 'Enter' || !target || !/^(INPUT)$/.test(target.tagName)) return;
      var card2 = authCard(target);
      if (!card2) return;
      e.preventDefault(); e.stopImmediatePropagation();
      handleAuth(card2, view);
    } else if (e.type === 'submit') {
      var card3 = authCard(target);
      if (!card3) return;
      e.preventDefault(); e.stopImmediatePropagation();
      handleAuth(card3, view);
    }
  }
  document.addEventListener('click', intercept, true);
  document.addEventListener('keydown', intercept, true);
  document.addEventListener('submit', intercept, true);

  /* 4b. real checkout. The prototype's cart lives in localStorage (`onhost.cart`: items {name, price, qty, commit}) and
   *     its "pay" button only fakes an order id. The capture-phase click builds the order through the API first
   *     (cart → quote → order; wallet, otherwise the payment gateway), stores the real number in window.__onhostOrder
   *     (the surface reads it through a data seam) and then lets the prototype continue. Guests are sent to sign-up. */
  function checkoutView() { return /^#\/(kosik|checkout)/.test(String(location.hash || '')); }
  function checkoutCard(el) {
    var node = el, depth = 0;
    while (node && node !== document.body && depth < 8) {
      if (node.querySelector && node.querySelector('input[type=checkbox]') && node.querySelector('button.btn-primary')) return node;
      node = node.parentElement; depth++;
    }
    return null;
  }
  function cartItems() {
    try { var c = JSON.parse(localStorage.getItem('onhost.cart') || 'null'); return c && c.items ? c : null; } catch (e) { return null; }
  }
  function skuFor(item) {
    if (item && item.meta && typeof item.meta === 'object' && item.meta.product_key) return item.meta; // product pages attach the SKU when adding to the cart
    var fromPages = window.OnhostSvcPages && window.OnhostSvcPages.sku ? window.OnhostSvcPages.sku(item.name, item.price) : null;
    if (fromPages) return fromPages;
    var D = window.ONHOST_DATA && window.ONHOST_DATA.skus ? window.ONHOST_DATA.skus : null;
    var cs = (document.documentElement.lang || 'cs').indexOf('en') !== 0;
    var idx = D ? D(cs) : null;
    if (!idx) return null;
    var name = String(item.name || '').toLowerCase();
    return idx[name + '|' + Math.round(item.price || 0)] || idx[name] || null;
  }
  /* Add-ons live on the cart lines themselves (`item.addons`, kept by the OnhostCart seam in `onhost.cart`), so the
   * order is built from the same state the checkout renders. */
  function prepaidCreditSelected() {
    var rows = Array.prototype.filter.call(document.querySelectorAll('div, span, p'), function (el) { return el.children.length <= 2 && /Kredit p[řr]ed[eo]m|Prepaid credit/i.test(el.textContent || ''); });
    var row = rows[rows.length - 1];
    if (!row) return false;
    var value = (row.textContent || '').replace(/Kredit p[řr]ed[eo]m|Prepaid credit/i, '').trim();
    return value !== '' && value !== '—' && value !== '-';
  }
  var orderInFlight = false;
  function cartHash(cart, method, guest) {
    var s = JSON.stringify([(B.user && B.user.id) || (guest && guest.email) || '', cart.items, cart.commit, cart.promoOk ? cart.promo : '', method && method.mode]);
    var h = 5381;
    for (var i = 0; i < s.length; i++) h = ((h << 5) + h + s.charCodeAt(i)) | 0;
    return 'cart-' + (h >>> 0).toString(36);
  }
  function placeOrder(card, cart) {
    var A = window.OnhostApi, C = window.OnhostCart;
    var built = C ? C.orderItems(cart) : { items: [], unknown: [] };
    var items = built.items, unknown = built.unknown, blocked = [];
    if (!C) {
      (cart.items || []).forEach(function (i) {
        var sku = skuFor(i);
        if (!sku) { unknown.push(i.name); return; }
        items.push({ product_key: sku.product_key, plan_key: sku.plan_key, qty: i.qty || 1, config: {} });
      });
    }
    if (prepaidCreditSelected()) blocked.push('Předplacený kredit');
    if (blocked.length) { showError(card, 'Tyto položky se objednávají v klientském panelu, ne v košíku: ' + blocked.join(', ') + '. Odškrtněte je prosím v kroku Konfigurace (kredit dobijete v panelu → Fakturace).'); return Promise.reject(new Error('unmapped')); }
    if (unknown.length) { showError(card, 'Položky se nepodařilo spárovat s katalogem: ' + unknown.join(', ') + '. Vyberte službu znovu z nabídky.'); return Promise.reject(new Error('unmapped')); }
    if (!items.length) { showError(card, 'Košík je prázdný.'); return Promise.reject(new Error('unmapped')); }
    var commit = cart.commit === 'yearly' || cart.commit === 12 ? 12 : (cart.commit === 24 ? 24 : 1);
    var guest = !B.user && C ? C.guestDetails() : null;
    var person = (B.user && B.user.name) || (guest && guest.name) || '';
    var consents = {};
    ['terms', 'privacy', 'withdrawal_waiver', 'dpa', 'sla', 'registrar_terms'].forEach(function (k) { consents[k] = { version: (B.version || '4.0'), person: person }; });
    (C ? C.domainTlds(cart) : []).forEach(function (tld) { consents['registry_terms_' + tld] = { version: (B.version || '4.0'), person: person }; });
    var method = selectedPaymentMethod(card);
    if (!method) { showError(card, 'Vyberte platební metodu.'); return Promise.reject(new Error('unmapped')); }
    if (method.mode === null) { showError(card, 'Metoda „' + method.label + '“ zatím není v provozu — zvolte kartu, bankovní převod nebo fakturu pro firmy.'); return Promise.reject(new Error('unmapped')); }
    if (guest && method.mode === 'postpaid') { showError(card, 'Fakturu se splatností nabízíme zákazníkům se schváleným kreditním rámcem — dokončete objednávku kartou nebo převodem, po přihlášení o rámec požádáte v panelu.'); return Promise.reject(new Error('unmapped')); }
    var payment = { mode: method.mode, provider: method.provider, method: method.method, return_urls: { success: location.origin + '/panel/objednavky', cancel: location.origin + '/kosik' } };
    // Retry safety: the same cart snapshot placed again (double click, reload, network hiccup) is recognised by the server
    // (unpaid order with the same fingerprint within a few minutes is returned instead of a new one); the browser also
    // remembers the order it placed for this cart hash so a reload shows the same confirmation.
    var hash = cartHash(cart, method, guest);
    var remembered = null;
    try { remembered = JSON.parse(sessionStorage.getItem('onhost.order.' + hash) || 'null'); } catch (x) {}
    if (remembered && remembered.number) { remembered.__cartHash = hash; return Promise.resolve(remembered); }
    var remember = function (r) {
      r.__cartHash = hash;
      try { sessionStorage.setItem('onhost.order.' + hash, JSON.stringify({ order_id: r.order_id, number: r.number, state: r.state, total: r.total, subtotal: r.subtotal, discount: r.discount, tax: r.tax, currency: r.currency, payment_mode: r.payment_mode, bank_instructions: r.bank_instructions || null })); } catch (x) {}
      return r;
    };
    if (guest) {
      // no account yet: the platform creates one from the details of step 2, places the order as that customer and signs the browser in
      var trimmed = function (v) { return (v || '').trim() || undefined; };
      var customer = { email: (guest.email || '').trim(), name: (guest.name || '').trim(), company: trimmed(guest.company), ico: trimmed(guest.ico), dic: trimmed(guest.dic), street: trimmed(guest.street), city: trimmed(guest.city), postal_code: trimmed(guest.postal_code), country: 'CZ' };
      return fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' }).catch(function () {})
        .then(function () { return A.post('/checkout/guest', { customer: customer, items: items, commit_months: commit, currency: 'CZK', promo_code: cart.promoOk ? (cart.promo || null) : null, consents: consents, payment: payment, terms: true, source: 'web', turnstile: turnstileToken() }, hash); })
        .then(function (r) { turnstileReset(); return r; }, function (e) { turnstileReset(); throw e; })
        .then(function (r) {
          var acc = r.account || {};
          if (acc.user) { write(K.session, { email: acc.user.email, name: acc.user.name, since: Date.now(), id: acc.user.id, org: acc.organization ? acc.organization.id : null, staff: false }); write(K.role, 'klient'); }
          try { sessionStorage.removeItem('onhost.guest'); } catch (x) {}
          return remember(r);
        });
    }
    return A.put('/cart', { items: items, commit_months: commit, currency: 'CZK', promo_code: cart.promoOk ? (cart.promo || null) : null })
      .then(function () { return A.post('/cart/quote', {}); })
      .then(function (q) {
        window.__onhostQuote = (q.data && q.data.quote_id) || q.quote_id;
        return A.post('/orders', { quote_id: window.__onhostQuote, consents: consents, payment: payment, source: 'web', turnstile: turnstileToken() }, hash + ':' + window.__onhostQuote);
      })
      .then(function (r) { turnstileReset(); return r; }, function (e) { turnstileReset(); throw e; })
      .then(remember);
  }
  /* The payment tiles are <label> blocks; the selected one carries the accent border (prototype styling). */
  function selectedPaymentMethod(card) {
    var tiles = Array.prototype.filter.call(document.querySelectorAll('label, div, button'), function (el) {
      return el.children.length && el.children.length <= 5 && /^(Karta|Card|Bankovn[ií] p[řr]evod|Bank transfer|Apple Pay|PayPal|Krypto|Crypto|Faktura|Invoice|SEPA)/i.test((el.textContent || '').replace(/\s+/g, ' ').trim());
    });
    var picked = null;
    tiles.forEach(function (el) {
      var cs = getComputedStyle(el);
      if (/236,\s*48,\s*19|174,\s*24,\s*0/.test(cs.borderColor) && parseInt(cs.borderWidth, 10) >= 2) picked = el;
    });
    if (!picked) return null;
    var label = (picked.textContent || '').replace(/\s+/g, ' ').trim();
    var map = [
      [/^(Karta|Card|Apple Pay|Google Pay)/i, { mode: 'gateway', provider: 'comgate', method: /Apple/i.test(label) ? 'applepay' : (/Google/i.test(label) ? 'googlepay' : 'card') }],
      [/^(Bankovn[ií] p[řr]evod|Bank transfer)/i, { mode: 'bank', provider: undefined, method: 'bank_transfer' }],
      [/^(Faktura|Invoice)/i, { mode: 'postpaid', provider: undefined, method: 'invoice' }],
    ];
    for (var i = 0; i < map.length; i++) { if (map[i][0].test(label)) return Object.assign({ label: label }, map[i][1]); }
    return { label: label, mode: null };
  }
  document.addEventListener('click', function (e) {
    if (B.demo || B.surface !== 'public' || !checkoutView()) return;
    var btn = e.target && e.target.closest ? e.target.closest('button.btn-primary') : null;
    if (!btn || btn.getAttribute('type') !== 'button') return;   // only the final "pay" button is type=button in the checkout
    var card = checkoutCard(btn);
    if (!card) return;
    // An order already placed for this page load is never placed twice: the pass-through click lets the prototype render
    // it, and any later click on the same (still unpaid) cart just re-renders the same confirmation.
    if (window.__onhostOrder) {
      if (window.__onhostOrder.passThrough === true) { window.__onhostOrder.passThrough = false; return; }
      e.preventDefault(); e.stopImmediatePropagation();
      showError(card, 'Objednávka ' + window.__onhostOrder.number + ' už byla přijata — najdete ji v klientském panelu.');
      return;
    }
    e.preventDefault(); e.stopImmediatePropagation();
    if (orderInFlight) return;
    if (!B.user) {
      var g = window.OnhostCart ? window.OnhostCart.guestDetails() : null;
      if (!g || !(g.email || '').trim() || !(g.name || '').trim()) { showError(card, 'Objednávku dokončíte i bez účtu: vyplňte v kroku Údaje e-mail a jméno (účet vám založíme), nebo se přihlaste.'); return; }
    }
    var cart = cartItems();
    if (!cart || !cart.items || !cart.items.length) { showError(card, 'Košík je prázdný.'); return; }
    var terms = card.querySelector('input[type=checkbox]');
    if (terms && !terms.checked) return; // the prototype shows its own validation for this
    orderInFlight = true; setBusy(card, true);
    placeOrder(card, cart).then(function (r) {
      var d = (r && r.data) || r || {};
      window.__onhostOrder = { number: d.number || d.order_id, id: d.order_id, state: d.state, cartHash: r.__cartHash, passThrough: true, total: d.total, subtotal: d.subtotal, discount: d.discount, tax: d.tax, currency: d.currency, payment_mode: d.payment_mode, bank_instructions: d.bank_instructions || null };
      orderInFlight = false; setBusy(card, false);
      if (d.redirect_url) { location.href = d.redirect_url; return; }
      btn.click(); // exactly one pass-through click: the prototype renders the real order number and clears the cart
      window.__onhostOrder.passThrough = false;
    }).catch(function (err) {
      orderInFlight = false; setBusy(card, false);
      var body = err && err.body;
      if (err && err.message === 'unmapped') return;
      if (body && body.error === 'account_exists') { showError(card, 'Účet s tímto e-mailem už existuje — přihlaste se (odkaz Přihlášení v hlavičce) a objednávku dokončete jako přihlášený zákazník; košík vám zůstane.'); return; }
      if (body && body.errors) { var firstKey = Object.keys(body.errors)[0]; if (firstKey && body.errors[firstKey][0]) { showError(card, body.errors[firstKey][0]); return; } }
      showError(card, (body && (body.message || body.error)) || 'Objednávku se nepodařilo odeslat.');
    });
  }, true);

  /* 5. after the shell loads: lock the role switcher and route sign-out through the API */
  function patchShell() {
    var S = window.OnhostSession;
    if (!S || S.__onhostBridged) return;
    S.__onhostBridged = true;
    if (!B.demo) {
      S.setRole = function () { return S.role(); };
      S.rolesFor = function () { return []; };
      S.signIn = function (email, name) { if (B.surface !== 'public') location.href = '/prihlaseni'; return { email: email, name: name }; };
      var out = S.signOut;
      S.signOut = function () {
        try { out.call(S); } catch (e) {}
        window.OnhostApi.post('/auth/logout').catch(function () {}).then(function () { location.href = '/'; });
      };
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', patchShell); else setTimeout(patchShell, 0);
  window.addEventListener('load', patchShell);
})();
