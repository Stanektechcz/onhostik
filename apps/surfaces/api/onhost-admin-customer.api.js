/* ONhost staff console — the customer account drawer (audit §5y, docs/ui/data-seams.md #33).
 *
 * "Otevřít účet" on a customer row opens the account instead of the prototype's narrated toast: the wallet (available,
 * reserved for orders, promo credit, what the customer can spend), the orders with their payment state, services and
 * documents — and the three things support and finance do for a customer:
 *   · Připsat kredit     POST /v1/staff/customers/{id}/wallet/credit   (a reason is required; HIGH, step-up dialog)
 *   · Potvrdit platbu    POST /v1/staff/payments/bank/lines            (an incoming transfer by the proforma's variable symbol)
 *   · Nová objednávka    POST /v1/staff/customers/{id}/orders[/quote]  (the panel's quote and checkout, on the customer's behalf)
 * Everything goes through the audited API; the drawer re-reads the account after every change. */
(function () {
  if (window.OnhostAdminCustomer) return; // the prototype runtime executes helmet scripts twice

  var root = null, state = null, catalog = null;
  function A() { return window.OnhostApi; }
  function esc(v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function amt(m) { return m && typeof m === 'object' ? (m.decimal != null ? Number(m.decimal) : (m.minor != null ? m.minor / 100 : 0)) : (Number(m) || 0); }
  function kc(n, cur) { var v = Math.round((Number(n) || 0) * 100) / 100; return v.toLocaleString('cs-CZ', { minimumFractionDigits: Number.isInteger(v) ? 0 : 2, maximumFractionDigits: 2 }) + ' ' + (cur === 'EUR' ? '€' : 'Kč'); }
  function when(iso) { if (!iso) return '—'; var d = new Date(iso); return isNaN(d) ? iso : d.toLocaleDateString('cs-CZ') + ' ' + d.toLocaleTimeString('cs-CZ', { hour: '2-digit', minute: '2-digit' }); }
  var STATE = { NEW: ['nová', 'warn'], PENDING_PAYMENT: ['čeká na platbu', 'warn'], PAID: ['zaplacená', 'ok'], PROVISIONING: ['zřizuje se', 'ok'], ACTIVE: ['aktivní', 'ok'], PARTIALLY_ACTIVE: ['částečně aktivní', 'warn'], CANCELLED: ['zrušená', 'off'], FAILED: ['selhala', 'hot'], REFUNDED: ['vrácená', 'off'] };
  var MODE = { wallet: 'z kreditu', bank: 'převodem', gateway: 'kartou', postpaid: 'na fakturu' };

  var CSS = '.ohac{position:fixed;inset:0;z-index:9500;display:flex;justify-content:flex-end;background:rgba(10,10,10,.45);font-family:inherit}' +
    '.ohac *{box-sizing:border-box}.ohac-d{width:min(980px,100%);height:100%;overflow:auto;background:var(--a-bg,#111);color:var(--a-fg,#eee);border-left:1px solid var(--a-line2,#333)}' +
    '.ohac-h{position:sticky;top:0;z-index:2;display:flex;align-items:center;gap:12px;padding:14px 20px;background:var(--a-panel,#181818);border-bottom:1px solid var(--a-line2,#333)}' +
    '.ohac-h h2{margin:0;font-size:17px}.ohac-x{margin-left:auto}.ohac-b{padding:18px 20px 40px}' +
    '.ohac-btn{border:1px solid var(--a-line2,#333);background:transparent;color:inherit;font:inherit;font-size:12px;font-weight:700;padding:7px 11px;cursor:pointer}.ohac-btn:hover{border-color:var(--a-acc,#ec3013)}' +
    '.ohac-btn.p{background:var(--a-acc,#ec3013);border-color:var(--a-acc,#ec3013);color:#fff}.ohac-btn[disabled]{opacity:.5;cursor:not-allowed}' +
    '.ohac-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:18px}.ohac-k{border:1px solid var(--a-line2,#333);background:var(--a-panel,#181818);padding:12px}' +
    '.ohac-k span{display:block;font-size:10.5px;letter-spacing:.08em;text-transform:uppercase;color:var(--a-muted,#999)}.ohac-k b{display:block;font-size:20px;margin-top:4px}' +
    '.ohac-sec{border:1px solid var(--a-line2,#333);background:var(--a-panel,#181818);margin-bottom:16px}.ohac-st{display:flex;align-items:center;gap:10px;padding:10px 14px;border-bottom:1px solid var(--a-line2,#333)}' +
    '.ohac-st h3{margin:0;font-size:13px}.ohac-st .ohac-btn{margin-left:auto}.ohac-row{display:grid;grid-template-columns:minmax(120px,1.1fr) minmax(90px,.7fr) minmax(110px,.8fr) minmax(90px,.6fr) auto;gap:10px;align-items:center;padding:9px 14px;border-bottom:1px solid var(--a-line,#262626);font-size:12.5px}' +
    '.ohac-muted{color:var(--a-muted,#999);font-size:11.5px}.ohac-pill{display:inline-block;font-size:10.5px;font-weight:700;padding:2px 7px;border:1px solid}.ohac-pill.ok{color:#5fbf7f;border-color:#5fbf7f}.ohac-pill.warn{color:#e0a93b;border-color:#e0a93b}.ohac-pill.hot{color:#ff5a4a;border-color:#ff5a4a}.ohac-pill.off{color:var(--a-muted,#999);border-color:var(--a-line2,#333)}' +
    '.ohac-form{padding:14px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.ohac-form label{display:block;font-size:11.5px;color:var(--a-muted,#999)}.ohac-form label.w{grid-column:1 / -1}' +
    '.ohac input,.ohac select,.ohac textarea{display:block;width:100%;margin-top:5px;padding:8px 9px;background:var(--a-field,#0d0d0d);color:var(--a-fg,#eee);border:1px solid var(--a-line2,#333);font:inherit;font-size:13px}' +
    '.ohac-q{grid-column:1 / -1;border-top:1px dashed var(--a-line2,#333);padding-top:10px;font-size:12.5px}.ohac-q div{display:flex;justify-content:space-between;padding:3px 0}.ohac-q b{font-size:16px}' +
    '.ohac-msg{margin:0 0 14px;padding:10px 12px;border:1px solid var(--a-acc,#ec3013);font-size:12.5px}.ohac-msg.ok{border-color:#5fbf7f}' +
    '@media (max-width:760px){.ohac-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.ohac-row{grid-template-columns:minmax(0,1fr) auto}.ohac-row>*:nth-child(3),.ohac-row>*:nth-child(4){display:none}.ohac-form{grid-template-columns:minmax(0,1fr)}}';

  function load() {
    state.loading = true; render();
    A().get('/staff/customers/' + encodeURIComponent(state.id)).then(function (r) { state.org = r.data || r; state.loading = false; render(); })
      .catch(function (e) { state.loading = false; state.msg = ['err', (e && e.message) || 'Účet se nepodařilo načíst.']; render(); });
  }
  function loadCatalog() {
    if (catalog) return Promise.resolve(catalog);
    return A().get('/catalog?locale=cs&currency=CZK').then(function (r) { catalog = (r.data || []).filter(function (p) { return p.family !== 'addon' && (p.plans || []).length; }); return catalog; });
  }

  function orderRows(org) {
    var orders = org.orders || [];
    if (!orders.length) return '<div class="ohac-row"><span class="ohac-muted">Zatím žádná objednávka.</span></div>';
    return orders.map(function (o) {
      var st = STATE[o.state] || [o.state, 'off'], held = o.review && o.review.state === 'pending', acts = '';
      if (o.state === 'PENDING_PAYMENT' && o.payment_mode === 'bank') acts += '<button class="ohac-btn p" data-a="paid" data-v="' + esc(o.id) + '">Potvrdit platbu</button>';
      if (held) acts += '<button class="ohac-btn" data-a="release" data-v="' + esc(o.id) + '">Uvolnit</button><button class="ohac-btn" data-a="reject" data-v="' + esc(o.id) + '">Zamítnout</button>';
      return '<div class="ohac-row"><span><b>' + esc(o.number) + '</b><div class="ohac-muted">' + when(o.placed_at) + (o.source === 'staff' ? ' · asistovaná' : '') + '</div></span><span>' + kc(amt(o.total), o.currency) + '</span><span><span class="ohac-pill ' + st[1] + '">' + esc(held ? 'ke kontrole' : st[0]) + '</span><div class="ohac-muted">' + esc(MODE[o.payment_mode] || o.payment_mode || '') + '</div></span><span class="ohac-muted">' + (o.bank_instructions && o.bank_instructions.variable_symbol ? 'VS ' + esc(o.bank_instructions.variable_symbol) : '') + '</span><span style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap">' + acts + '</span></div>';
    }).join('');
  }

  function creditForm() {
    return '<div class="ohac-form"><label>Částka (' + esc((state.org && state.org.currency) || 'CZK') + ')<input type="number" min="1" step="1" data-f="c_amount" value="' + esc(state.form.c_amount || '') + '" placeholder="1000"/></label>' +
      '<label>Druh<select data-f="c_kind"><option value="manual"' + (state.form.c_kind !== 'promo' ? ' selected' : '') + '>Přijatá platba mimo bránu (hotově, jinak)</option><option value="promo"' + (state.form.c_kind === 'promo' ? ' selected' : '') + '>Bonusový kredit (nevratný)</option></select></label>' +
      '<label class="w">Důvod (zapíše se do auditu a k pohybu)<input type="text" data-f="c_note" value="' + esc(state.form.c_note || '') + '" placeholder="např. tiket #4411, kompenzace výpadku"/></label>' +
      '<div style="grid-column:1 / -1;display:flex;gap:8px"><button class="ohac-btn p" data-a="credit"' + (state.busy ? ' disabled' : '') + '>Připsat kredit</button><button class="ohac-btn" data-a="panel" data-v="">Zrušit</button></div></div>';
  }

  function orderForm() {
    if (!catalog) return '<div class="ohac-form"><span class="ohac-muted">Načítám katalog…</span></div>';
    var f = state.form, p = catalog.filter(function (x) { return x.key === f.o_product; })[0] || catalog[0];
    if (!f.o_product) f.o_product = p.key;
    var plans = p.plans || [], plan = plans.filter(function (x) { return x.key === f.o_plan; })[0] || plans.filter(function (x) { return x.highlighted; })[0] || plans[0];
    f.o_plan = plan ? plan.key : '';
    var html = '<div class="ohac-form"><label>Služba<select data-f="o_product">' + catalog.map(function (x) { return '<option value="' + esc(x.key) + '"' + (x.key === p.key ? ' selected' : '') + '>' + esc(x.name) + '</option>'; }).join('') + '</select></label>' +
      '<label>Tarif<select data-f="o_plan">' + plans.map(function (x) { var pr = x.price && x.price.month ? amt(x.price.month) : null; return '<option value="' + esc(x.key) + '"' + (x.key === f.o_plan ? ' selected' : '') + '>' + esc(x.name) + (pr != null ? ' · ' + kc(pr) + ' / měs. bez DPH' : '') + '</option>'; }).join('') + '</select></label>';
    if (p.family === 'web' || p.family === 'managed' || p.family === 'mail') html += '<label class="w">Doména<input type="text" data-f="o_domain" value="' + esc(f.o_domain || '') + '" placeholder="firma.cz"/></label>';
    if (p.family === 'cloud' || p.family === 'data') html += '<label>Hostname / název<input type="text" data-f="o_name" value="' + esc(f.o_name || '') + '" placeholder="app-prod-1"/></label>' + ((p.meta && p.meta.images) ? '<label>Systém<select data-f="o_image">' + p.meta.images.map(function (im) { return '<option' + (f.o_image === im ? ' selected' : '') + '>' + esc(im) + '</option>'; }).join('') + '</select></label>' : '');
    if (p.family === 'game') {
      var eggs = (p.meta && p.meta.eggs) || [];
      html += '<label>Hra (šablona)<select data-f="o_egg">' + eggs.map(function (e) { return '<option' + (f.o_egg === e ? ' selected' : '') + '>' + esc(e) + '</option>'; }).join('') + '</select></label>' +
        '<label>RAM (GB)<input type="number" min="1" data-f="o_ram" value="' + esc(f.o_ram || '') + '" placeholder="minimum hry"/></label><label>vCPU<input type="number" min="1" data-f="o_vcpu" value="' + esc(f.o_vcpu || '') + '" placeholder="minimum hry"/></label><label>NVMe (GB)<input type="number" min="10" step="10" data-f="o_nvme" value="' + esc(f.o_nvme || '') + '" placeholder="minimum hry"/></label>';
    }
    html += '<label>Platba<select data-f="o_pay"><option value="bank"' + (f.o_pay !== 'wallet' && f.o_pay !== 'postpaid' ? ' selected' : '') + '>Převodem (zálohová faktura)</option><option value="wallet"' + (f.o_pay === 'wallet' ? ' selected' : '') + '>Z kreditu zákazníka</option><option value="postpaid"' + (f.o_pay === 'postpaid' ? ' selected' : '') + '>Na fakturu (schválený úvěr)</option></select></label>' +
      '<label>Účtování<select data-f="o_period"><option value="month"' + (f.o_period !== 'year' ? ' selected' : '') + '>měsíčně</option><option value="year"' + (f.o_period === 'year' ? ' selected' : '') + '>ročně</option></select></label>' +
      '<label class="w">Na čí žádost (zapíše se k souhlasům a do auditu)<input type="text" data-f="o_note" value="' + esc(f.o_note || '') + '" placeholder="např. tiket #4411, telefonát s jednatelem"/></label>';
    var q = state.quote;
    if (q) html += '<div class="ohac-q">' + (q.lines || []).map(function (l) { return '<div><span>' + esc(l.name) + '</span><span>' + kc(l.net / 100) + '</span></div>'; }).join('') + '<div><span>DPH</span><span>' + kc(q.tax / 100) + '</span></div><div><span>Celkem</span><b>' + kc(q.total / 100) + '</b></div>' + (f.o_pay === 'wallet' && state.org && amt(state.org.spendable) < q.total / 100 ? '<div class="ohac-muted" style="color:#ff5a4a">Kredit zákazníka (' + kc(amt(state.org.spendable)) + ') nestačí — připište kredit nebo zvolte převod.</div>' : '') + '</div>';
    html += '<div style="grid-column:1 / -1;display:flex;gap:8px;flex-wrap:wrap"><button class="ohac-btn" data-a="quote"' + (state.busy ? ' disabled' : '') + '>Spočítat cenu</button><button class="ohac-btn p" data-a="order"' + (state.busy || !q ? ' disabled' : '') + '>Závazně objednat za zákazníka</button><button class="ohac-btn" data-a="panel" data-v="">Zrušit</button></div></div>';
    return html;
  }

  function render() {
    if (!root) return;
    var org = state.org, cur = (org && org.currency) || 'CZK', w = (org && org.wallet) || {};
    var body = '';
    if (state.msg) body += '<p class="ohac-msg' + (state.msg[0] === 'ok' ? ' ok' : '') + '">' + esc(state.msg[1]) + '</p>';
    if (!org) body += '<p class="ohac-muted">' + (state.loading ? 'Načítám účet…' : 'Účet není k dispozici.') + '</p>';
    else {
      body += '<div class="ohac-kpis"><div class="ohac-k"><span>K dispozici</span><b>' + kc(amt(org.spendable), cur) + '</b></div><div class="ohac-k"><span>Blokováno objednávkami</span><b>' + kc(amt(w.reserved), cur) + '</b></div><div class="ohac-k"><span>Připsáno (zaúčtováno)</span><b>' + kc(amt(w.posted), cur) + '</b></div><div class="ohac-k"><span>Bonusový kredit</span><b>' + kc(amt(w.promo), cur) + '</b></div></div>' +
        '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px"><button class="ohac-btn' + (state.panel === 'credit' ? ' p' : '') + '" data-a="panel" data-v="credit">+ Připsat kredit</button><button class="ohac-btn' + (state.panel === 'order' ? ' p' : '') + '" data-a="panel" data-v="order">+ Nová objednávka za zákazníka</button><button class="ohac-btn" data-a="reload">Obnovit</button></div>';
      if (state.panel === 'credit') body += '<div class="ohac-sec"><div class="ohac-st"><h3>Připsat kredit</h3><span class="ohac-muted">vyžaduje čerstvé ověření (2FA)</span></div>' + creditForm() + '</div>';
      if (state.panel === 'order') body += '<div class="ohac-sec"><div class="ohac-st"><h3>Nová objednávka za zákazníka</h3><span class="ohac-muted">stejná cena a proces jako v panelu zákazníka</span></div>' + orderForm() + '</div>';
      body += '<div class="ohac-sec"><div class="ohac-st"><h3>Objednávky</h3><span class="ohac-muted">' + (org.orders || []).length + '</span></div>' + orderRows(org) + '</div>' +
        '<div class="ohac-sec"><div class="ohac-st"><h3>Služby</h3><span class="ohac-muted">' + (org.services || []).length + '</span></div>' + ((org.services || []).length ? org.services.map(function (s) { return '<div class="ohac-row"><span><b>' + esc(s.label || s.name || s.product_key) + '</b><div class="ohac-muted">' + esc(s.product_key || '') + '</div></span><span class="ohac-muted">' + esc(s.plan_key || '') + '</span><span><span class="ohac-pill ' + (/ACTIVE/.test(s.state) ? 'ok' : (/FAIL|SUSP/.test(s.state) ? 'hot' : 'warn')) + '">' + esc(String(s.state || '').toLowerCase()) + '</span></span><span></span><span></span></div>'; }).join('') : '<div class="ohac-row"><span class="ohac-muted">Žádné služby.</span></div>') + '</div>' +
        '<div class="ohac-sec"><div class="ohac-st"><h3>Doklady</h3><span class="ohac-muted">' + (org.invoices || []).length + '</span></div>' + ((org.invoices || []).length ? org.invoices.slice(0, 20).map(function (i) { return '<div class="ohac-row"><span><b>' + esc(i.number || i.id) + '</b><div class="ohac-muted">' + esc(i.type || '') + '</div></span><span>' + kc(amt(i.total), i.currency) + '</span><span><span class="ohac-pill ' + (i.state === 'PAID' ? 'ok' : 'warn') + '">' + esc(String(i.state || '').toLowerCase()) + '</span></span><span class="ohac-muted">' + when(i.issued_at) + '</span><span></span></div>'; }).join('') : '<div class="ohac-row"><span class="ohac-muted">Žádné doklady.</span></div>') + '</div>';
    }
    root.innerHTML = '<div class="ohac-d" role="dialog" aria-modal="true"><div class="ohac-h"><h2>' + esc(org ? org.name : 'Zákazník') + '</h2><span class="ohac-muted">' + esc(org ? ((org.billing && org.billing.ico ? 'IČO ' + org.billing.ico + ' · ' : '') + (org.billing && org.billing.email ? org.billing.email : '')) : '') + '</span><button class="ohac-btn ohac-x" data-a="close">✕ Zavřít</button></div><div class="ohac-b">' + body + '</div></div>';
  }

  function items() {
    var f = state.form, p = (catalog || []).filter(function (x) { return x.key === f.o_product; })[0];
    if (!p) return null;
    var config = {};
    if (f.o_domain) config.domain = String(f.o_domain).trim().toLowerCase();
    if (f.o_name) { config.label = f.o_name; config.hostname = String(f.o_name).toLowerCase().replace(/[^a-z0-9.-]+/g, '-'); }
    if (p.family === 'cloud' && (f.o_image || (p.meta && p.meta.images && p.meta.images[0]))) config.image = f.o_image || p.meta.images[0];
    if (p.family === 'game') {
      config.egg = f.o_egg || ((p.meta && p.meta.eggs) || [])[0];
      var opts = {}; if (f.o_ram) opts.ram_gb = Number(f.o_ram); if (f.o_vcpu) opts.vcpu = Number(f.o_vcpu); if (f.o_nvme) opts.nvme_gb = Number(f.o_nvme);
      if (Object.keys(opts).length) config.options = opts;
    }
    return [{ product_key: p.key, plan_key: f.o_plan, qty: 1, period: f.o_period === 'year' ? 'year' : 'month', config: config }];
  }
  function fail(e) { state.busy = false; state.msg = ['err', (e && e.message) || 'Akce se nezdařila.']; render(); }

  function onClick(e) {
    var t = e.target.closest ? e.target.closest('[data-a]') : null;
    if (e.target === root) { close(); return; }
    if (!t || t.disabled) return;
    e.preventDefault();
    var a = t.getAttribute('data-a'), v = t.getAttribute('data-v');
    if (a === 'close') return close();
    if (a === 'reload') { state.msg = null; return load(); }
    if (a === 'panel') { state.panel = v || null; state.quote = null; state.msg = null; render(); if (v === 'order') loadCatalog().then(render).catch(fail); return; }
    if (a === 'credit') {
      var f = state.form;
      if (!(Number(f.c_amount) > 0) || String(f.c_note || '').trim().length < 3) { state.msg = ['err', 'Vyplňte částku a důvod připsání.']; return render(); }
      state.busy = true; render();
      A().post('/staff/customers/' + encodeURIComponent(state.id) + '/wallet/credit', { amount: Number(f.c_amount), kind: f.c_kind || 'manual', note: f.c_note.trim() }, A().key())
        .then(function (r) { var d = r.data || r; state.busy = false; state.panel = null; state.form = {}; state.msg = ['ok', 'Připsáno ' + kc(Number(f.c_amount)) + '. Zákazník má k dispozici ' + kc(amt(d.spendable)) + '.']; load(); }).catch(fail);
      return;
    }
    if (a === 'quote' || a === 'order') {
      var list = items();
      if (!list) return;
      if (a === 'order' && String(state.form.o_note || '').trim().length < 3) { state.msg = ['err', 'Uveďte, na čí žádost objednávku zadáváte.']; return render(); }
      state.busy = true; state.msg = null; render();
      if (a === 'quote') {
        A().post('/staff/customers/' + encodeURIComponent(state.id) + '/orders/quote', { items: list, commit_months: state.form.o_period === 'year' ? 12 : 1 }).then(function (r) { state.busy = false; state.quote = r.data || r; render(); }).catch(fail);
        return;
      }
      A().post('/staff/customers/' + encodeURIComponent(state.id) + '/orders', { items: list, payment: state.form.o_pay || 'bank', commit_months: state.form.o_period === 'year' ? 12 : 1, note: state.form.o_note.trim() }, A().key())
        .then(function (r) { var d = r.data || r; state.busy = false; state.panel = null; state.quote = null; state.form = {}; state.msg = ['ok', 'Objednávka ' + (d.number || '') + ' vytvořena · ' + (STATE[d.state] ? STATE[d.state][0] : d.state) + (d.bank_instructions && d.bank_instructions.variable_symbol ? ' · VS ' + d.bank_instructions.variable_symbol : '') + ' · kredit k dispozici ' + kc(amt(d.spendable)) + '.']; load(); }).catch(fail);
      return;
    }
    if (a === 'paid') {
      var o = ((state.org && state.org.orders) || []).filter(function (x) { return x.id === v; })[0]; if (!o) return;
      var vs = o.bank_instructions && o.bank_instructions.variable_symbol;
      if (!vs) { state.msg = ['err', 'Objednávka nemá variabilní symbol; platbu zapište v sekci Platby.']; return render(); }
      var sum = window.prompt('Přijatá částka pro ' + o.number + ' (VS ' + vs + '):', String(amt(o.total)));
      if (sum == null) return;
      state.busy = true; render();
      A().post('/staff/payments/bank/lines', { variable_symbol: vs, amount: Number(String(sum).replace(',', '.')), currency: o.currency || 'CZK', external_id: 'console-' + o.number + '-' + Date.now(), message: 'Potvrzeno v konzoli' }, A().key())
        .then(function () { state.busy = false; state.msg = ['ok', 'Platba zapsána — objednávka ' + o.number + ' se spáruje a zřídí.']; load(); }).catch(fail);
      return;
    }
    if (a === 'release' || a === 'reject') {
      var reason = window.prompt(a === 'release' ? 'Důvod uvolnění (audit):' : 'Důvod zamítnutí (zákazník ho uvidí):', '');
      if (reason == null) return;
      state.busy = true; render();
      A().post('/staff/orders/' + encodeURIComponent(v) + '/review', { decision: a, reason: reason }, A().key()).then(function () { state.busy = false; state.msg = ['ok', a === 'release' ? 'Objednávka uvolněna.' : 'Objednávka zamítnuta.']; load(); }).catch(fail);
    }
  }
  function onInput(e) {
    var k = e.target.getAttribute && e.target.getAttribute('data-f'); if (!k) return;
    state.form[k] = e.target.value;
    if (e.type === 'change' && /^o_(product|plan|egg|pay|period)$/.test(k)) { if (k === 'o_product') { state.form.o_plan = ''; state.form.o_egg = ''; } state.quote = null; render(); }
    else if (/^o_/.test(k) && state.quote && e.type === 'change') { state.quote = null; render(); }
  }
  function onKey(e) { if (e.key === 'Escape' && root) close(); }

  function open(id) {
    if (!A() || !id) return false;
    state = { id: id, org: null, loading: true, panel: null, form: {}, quote: null, busy: false, msg: null };
    if (!root) {
      if (!document.getElementById('ohac-css')) { var st = document.createElement('style'); st.id = 'ohac-css'; st.textContent = CSS; document.head.appendChild(st); }
      root = document.createElement('div'); root.className = 'ohac';
      root.addEventListener('click', onClick); root.addEventListener('input', onInput); root.addEventListener('change', onInput);
      document.addEventListener('keydown', onKey);
      document.body.appendChild(root);
    }
    load();
    return true;
  }
  function close() { if (root) { root.remove(); root = null; } document.removeEventListener('keydown', onKey); }

  window.OnhostAdminCustomer = { open: open, close: close };
})();
