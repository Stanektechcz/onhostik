/* ONhost panel — "Nová služba" wizard seams (docs/ui/data-seams.md #16).
 *
 * The prototype's order wizard offers narrated products, sizes and locations and, on confirm, appends a fake server
 * row. On the API-backed panel the choices come from the real catalog and regions (window.ONHOST_PANEL.catalog /
 * .regions) and confirming places a real order: PUT /v1/cart → POST /v1/cart/quote → POST /v1/orders with the
 * customer's consents — paid from credit when it covers the quote, otherwise by proforma (bank transfer), exactly
 * like the public checkout. The surface stays byte-identical; SurfaceRenderer points the wizard at these functions. */
(function () {
  if (window.OnhostPanelOrder) return; // the prototype runtime executes helmet scripts twice

  function data() { return window.ONHOST_PANEL || null; }
  function isCs(cmp) { return !cmp || !cmp.state || cmp.state.lang !== 'en'; }
  function tr(cmp) { var cs = isCs(cmp); return function (a, b) { return cs ? a : b; }; }
  function money(cmp, n) { return cmp && typeof cmp.money === 'function' ? cmp.money(n || 0) : String(n || 0); }
  function flash(cmp, title, body) { if (cmp && typeof cmp.flash === 'function') cmp.flash(title, body); }
  function credit(cmp) { return cmp && cmp.state && typeof cmp.state.credit === 'number' ? cmp.state.credit : 0; }
  function num(m) { return m && typeof m === 'object' ? parseFloat(m.decimal || 0) : (parseFloat(m) || 0); }
  function product(key) { return ((data() && data().catalog) || []).filter(function (p) { return p.key === key; })[0] || null; }

  /* [key, name, description] rows for the "Co chcete spustit" step; domains are a product of their own (priced per TLD and year). */
  function types(cmp) {
    var d = data(), _ = tr(cmp);
    if (!d || !d.catalog || !d.catalog.length) return null;
    // add-ons (SSL, CDN, backups, extra IPv4…) belong to a service, never to the "what to start" step
    var rows = d.catalog.filter(function (p) { return p.orderable !== false && p.family !== 'addon'; }).map(function (p) { return [p.key, p.name, p.description || '']; });
    var domainOk = !d.nav || !d.nav.categories || d.nav.categories.some(function (c) { return c.key === 'domain' && c.orderable; });
    if (domainOk && d.tlds && d.tlds.length) rows.push(['domain', _('Doména', 'Domain'), _('registrace nové domény (' + d.tlds.slice(0, 4).map(function (t) { return '.' + t.tld; }).join(', ') + ' …) · držitelem jste vy', 'register a new domain (' + d.tlds.slice(0, 4).map(function (t) { return '.' + t.tld; }).join(', ') + ' …) · you are the registrant')]);
    return rows;
  }
  function tldOf(name) { var m = String(name || '').trim().toLowerCase().match(/^[a-z0-9-]+(?:\.[a-z0-9-]+)*\.([a-z0-9-]{2,})$/); return m ? m[1] : null; }
  function tldPolicy(tld) { return ((data() && data().tlds) || []).filter(function (t) { return t.tld === tld; })[0] || null; }
  /* Summary price label: services per month, domains per registration period of the entered name's TLD. */
  function priceLabel(cmp, md, orderSize) {
    var _ = tr(cmp);
    if (!md || md.type !== 'domain') return money(cmp, orderSize && orderSize[2]);
    var years = parseInt(orderSize && orderSize[0], 10) || 1, p = tldPolicy(tldOf(md.name));
    if (!p) return _('dle koncovky domény · doplňte název', 'per TLD · enter the name');
    return money(cmp, parseFloat(p.register) * years) + ' / ' + years + ' ' + (years === 1 ? _('rok', 'year') : _('roky', 'years')) + ' · ' + _('obnova ', 'renewal ') + money(cmp, parseFloat(p.renew)) + _(' / rok', ' / year');
  }

  /* [plan key, spec, monthly price] rows for the "Velikost" step of the chosen product. */
  function sizes(cmp, type) {
    var d = data(), _ = tr(cmp);
    if (!d || !d.catalog || !d.catalog.length) return null;
    if (type === 'domain' && d.tlds && d.tlds.length) {
      var cz = tldPolicy('cz') || d.tlds[0];
      var hint = _('cena podle koncovky, např. .', 'price per TLD, e.g. .') + cz.tld + ' ' + money(cmp, parseFloat(cz.register)) + _(' / rok', ' / year');
      return [1, 2, 3, 5, 10].map(function (y) { return [String(y), _('registrace na ' + y + ' ' + (y === 1 ? 'rok' : (y < 5 ? 'roky' : 'let')), 'register for ' + y + ' ' + (y === 1 ? 'year' : 'years')), 0, hint]; });
    }
    var p = product(type) || d.catalog[0];
    var floor = p.family === 'game' && p.eggs && p.eggs.length ? Math.min.apply(null, p.eggs.map(function (e) { return e.min_ram_mb || 0; })) : 0;
    return p.plans.map(function (pl) { return [pl.key, (pl.spec ? pl.name + ' · ' + pl.spec : pl.name) + (p.family === 'game' && pl.ram_mb ? ' · ' + _('hry do ', 'games up to ') + Math.round(pl.ram_mb / 1024) + ' GB' : ''), pl.monthly]; }).filter(function (row, i) { return !floor || !p.plans[i].ram_mb || p.plans[i].ram_mb >= floor; }); // §5t-2
  }

  /* "Systém a obraz" rows for the chosen product: game servers pick the game template (egg) here; other products keep the prototype's list. */
  function images(cmp, type) {
    var p = product(type), _ = tr(cmp);
    if (!p || p.family !== 'game' || !p.eggs || !p.eggs.length) return null;
    return p.eggs.reduce(function (rows, e, i) { // §5p: a template with versions is one row per version (`key@version`), newest first
      var note = (e.note || '') + (e.min_ram_mb ? (e.note ? ' · ' : '') + _('min. ', 'min. ') + Math.round(e.min_ram_mb / 1024) + ' GB RAM' : '') || (i === 0 ? _('doporučeno', 'recommended') : '');
      if (e.versions && e.versions.length) e.versions.forEach(function (v, j) { rows.push([e.key + '@' + v, (e.label || e.key) + ' ' + v, (j === 0 ? _('nejnovější', 'latest') + ' · ' : '') + note]); });
      else rows.push([e.key, e.label || e.key, note]);
      return rows;
    }, []);
  }

  /* Domain registration from the panel: availability check, cart line `domain`, registry + registrar consents, order. */
  function placeDomain(cmp, sel) {
    var _ = tr(cmp), A = window.OnhostApi, d = data();
    var fqdn = String(sel.name || '').trim().toLowerCase().replace(/^https?:\/\//, '').replace(/\/.*$/, '').replace(/\.$/, '');
    var tld = tldOf(fqdn), policy = tld ? tldPolicy(tld) : null;
    if (!tld) { flash(cmp, _('Zadejte název domény', 'Enter a domain name'), _('Do pole Jméno napište celou doménu, např. firma.cz.', 'Type the whole domain into the Name field, e.g. firma.cz.')); return; }
    if (!policy) { flash(cmp, _('Koncovku .' + tld + ' zatím nenabízíme', 'We do not offer .' + tld + ' yet'), _('Nabízíme: ', 'We offer: ') + (d.tlds || []).map(function (t) { return '.' + t.tld; }).join(', ')); return; }
    var years = parseInt(sel.size, 10) || policy.default_period || 1;
    if ((policy.periods || []).indexOf(years) < 0) years = policy.default_period || policy.periods[0] || 1;
    var person = (window.ONHOST && window.ONHOST.user && window.ONHOST.user.name) || '';
    flash(cmp, _('Ověřuji dostupnost…', 'Checking availability…'), fqdn);
    A.post('/domains/check', { names: [fqdn], currency: 'CZK' }).then(function (r) {
      var row = ((r.data || r)[0]) || {};
      if (row.available !== true) {
        flash(cmp, _('Doménu nelze registrovat', 'The domain cannot be registered'), row.available === false ? _('Doména ' + fqdn + ' je už registrovaná. Pokud je vaše, převod k nám domluvíme přes podporu.', 'Domain ' + fqdn + ' is already registered. If it is yours, arrange a transfer through support.') : _('Dostupnost se teď nepodařilo ověřit, zkuste to za chvíli.', 'Availability could not be checked right now, try again shortly.'));
        throw new Error('unavailable');
      }
      var consents = {};
      Object.keys(d.consents || {}).forEach(function (k) { consents[k] = { version: d.consents[k], person: person }; });
      if (!consents['registry_terms_' + tld]) consents['registry_terms_' + tld] = { version: 'current', person: person, url: policy.registry_terms_url };
      if (!consents.registrar_terms) consents.registrar_terms = { version: 'current', person: person, url: policy.registrar_terms_url };
      return A.put('/cart', { items: [{ product_key: 'domain', qty: 1, config: { fqdn: fqdn, period_years: years, action: 'register' } }], commit_months: 1, currency: 'CZK', promo_code: null })
        .then(function () { return A.post('/cart/quote', {}); })
        .then(function (q) {
          var quote = q.data || q;
          var raw = quote.total != null ? quote.total : (quote.totals && (quote.totals.gross || quote.totals.total));
          var total = typeof raw === 'number' ? raw / 100 : num(raw);
          var mode = paymentMode(cmp, sel, total);
          return A.post('/orders', { quote_id: quote.quote_id, consents: consents, payment: paymentPayload(mode), source: 'panel' }, 'panel-domain:' + quote.quote_id + ':' + mode).then(function (r2) { return { result: r2.data || r2, mode: mode, total: total }; });
        })
        .then(function (x) {
          if (redirectIfGateway(cmp, x)) return;
          var o = x.result.order || x.result;
          flash(cmp, _('Objednávka ', 'Order ') + (o.number || '') + _(' přijata', ' received'),
            x.mode === 'wallet' ? _('Uhrazeno z kreditu (' + money(cmp, x.total) + '). Doménu registrujeme; sledujte sekci Domény, potvrzení jde na e-mail.', 'Paid from credit (' + money(cmp, x.total) + '). The domain is being registered; watch Domains, a confirmation goes by e-mail.')
              : _('Zálohová faktura je v záložce Fakturace (' + money(cmp, x.total) + '); doménu registrujeme po připsání platby.', 'The proforma is in the Billing tab (' + money(cmp, x.total) + '); the domain is registered once the payment arrives.'));
          if (window.OnhostStore && window.OnhostStore.refresh) window.OnhostStore.refresh();
          setTimeout(function () { location.reload(); }, 3000);
        });
    }).catch(function (e) { if (!e || e.message !== 'unavailable') flash(cmp, _('Objednávka neprošla', 'Order failed'), (e && e.message) || _('Zkuste to prosím znovu.', 'Please try again.')); });
  }

  /* Wizard fields that make no sense for the chosen product (an OS image for web hosting) and labels that do. */
  function current(md) {
    var d = data();
    return product(md && md.type) || (((d && d.catalog) || []).filter(function (p) { return p.orderable !== false; })[0] || null);
  }
  function hidden(cmp, md, field) {
    var p = current(md), label = String((field && field.label) || '');
    if (md && md.type === 'domain') return /Syst\u00e9m a obraz|System and image|Lokalita|Location/.test(label);
    if (!p || p.family === 'cloud' || p.family === 'game') return false;
    return /Syst\u00e9m a obraz|System and image/.test(label);
  }
  function relabel(cmp, md, field) {
    var _ = tr(cmp), p = current(md), label = String((field && field.label) || '');
    if (md && md.type === 'domain' && /Jm\u00e9no instance|Instance name/.test(label)) return Object.assign({}, field, { label: _('Doména', 'Domain'), hint: _('celý název, např. firma.cz', 'the whole name, e.g. firma.cz'), ph: 'firma.cz' });
    if (p && p.family !== 'cloud' && p.family !== 'game' && /Jm\u00e9no instance|Instance name/.test(label)) return Object.assign({}, field, { label: _('Doména webu', 'Site domain'), hint: _('volitelné · bez domény dostane web dočasnou adresu', 'optional · without a domain the site gets a temporary address'), ph: 'firma.cz' });
    return field;
  }

  /* Summary rows of step 3 without the ones that do not apply (no location for a domain, no system image for hosting). */
  function summary(cmp, md, rows) {
    var p = current(md), domain = md && md.type === 'domain';
    return (rows || []).filter(function (r) {
      var label = String((r && r[0]) || '');
      if (domain && /^(Lokalita|Location|Syst\u00e9m|System)$/.test(label)) return false;
      if (!domain && p && p.family !== 'cloud' && p.family !== 'game' && /^(Syst\u00e9m|System)$/.test(label)) return false;
      return true;
    });
  }
  function period(cmp, md) {
    var _ = tr(cmp);
    return (md && md.type === 'domain') ? _('rok', 'year') : _('měsíc', 'month');
  }

  /* Payment choice of the wizard: credit when it covers the order, bank transfer (proforma), card (gateway). */
  function payOptions(cmp, md, orderSize) {
    var _ = tr(cmp), c = credit(cmp), gross = orderSize && orderSize[2] ? orderSize[2] * 1.21 : 0, enough = gross > 0 ? c >= gross : c > 0;
    return [
      ['wallet', _('Z kreditu', 'From credit'), enough ? _('zůstatek ', 'balance ') + money(cmp, c) + _(' · služba se spustí ihned', ' · the service starts at once') : _('zůstatek ', 'balance ') + money(cmp, c) + _(' nestačí — nejdřív dobijte', ' is not enough — top up first')],
      ['bank', _('Bankovní převod', 'Bank transfer'), _('zálohová faktura s variabilním symbolem · spustíme po připsání platby', 'proforma with a payment reference · starts once the money arrives')],
      ['card', _('Platební karta', 'Payment card'), _('Visa, Mastercard, Apple Pay, Google Pay · platební brána, spuštění ihned', 'Visa, Mastercard, Apple Pay, Google Pay · payment gateway, starts at once')]
    ];
  }
  function payLabel(cmp, md, orderSize) {
    var _ = tr(cmp), p = (md && md.pay) || '', c = credit(cmp), gross = orderSize && orderSize[2] ? orderSize[2] * 1.21 : 0;
    if (p === 'bank') return _('bankovním převodem (zálohová faktura)', 'bank transfer (proforma)');
    if (p === 'card') return _('platební kartou (brána)', 'card (payment gateway)');
    if (p === 'wallet' || (c > 0 && c >= gross)) return _('z kreditu · zůstatek ', 'from credit · balance ') + money(cmp, c);
    return _('bankovním převodem — nebo zvolte kartu, případně dobijte kredit', 'bank transfer — or choose card, or top up credit');
  }
  /* The API payment mode for the customer's choice; an explicit credit choice needs enough credit. */
  function paymentMode(cmp, sel, total) {
    var _ = tr(cmp), p = (sel && sel.pay) || '', enough = total > 0 && credit(cmp) >= total;
    if (p === 'wallet' && !enough) throw new Error(_('Kredit ' + money(cmp, credit(cmp)) + ' nestačí na ' + money(cmp, total) + '. Zvolte převod nebo kartu, nebo kredit dobijte.', 'Credit ' + money(cmp, credit(cmp)) + ' does not cover ' + money(cmp, total) + '. Choose transfer or card, or top up first.'));
    if (p === 'wallet') return 'wallet';
    if (p === 'bank') return 'bank';
    if (p === 'card') return 'gateway';
    return enough ? 'wallet' : 'bank';
  }
  function paymentPayload(mode) {
    return mode === 'gateway' ? { mode: 'gateway', method: 'card', return_urls: { success: location.origin + '/panel/fakturace', cancel: location.origin + '/panel/fakturace' } } : { mode: mode };
  }
  /* A gateway order: the customer continues on the payment page; the panel picks the paid order up on return. */
  function redirectIfGateway(cmp, x) {
    var _ = tr(cmp), url = x && x.result && (x.result.redirect_url || (x.result.order && x.result.order.redirect_url));
    if (x.mode !== 'gateway') return false;
    if (url) { flash(cmp, _('Přesměrování na platební bránu', 'Redirecting to the payment gateway'), money(cmp, x.total)); location.href = url; return true; }
    flash(cmp, _('Objednávka přijata', 'Order received'), _('Platební brána zatím není dostupná; doklad zaplaťte v sekci Fakturace převodem nebo z kreditu.', 'The payment gateway is not available yet; pay the document in Billing by transfer or from credit.'));
    return true;
  }

  /* [code, label, datacenter] rows for the "Lokalita" step from the real regions. */
  function regions(cmp) {
    var d = data();
    if (!d || !d.regions || !d.regions.length) return null;
    return d.regions.map(function (r) { return [r.code, r.code.toUpperCase() + (r.name ? ' · ' + r.name : ''), r.datacenter || '']; });
  }

  /* The region the order goes to: the chosen one when it exists, otherwise the first real region (the prototype's
   * default "PRG1" is not a region of this installation). */
  function region(code) {
    var d = data(), list = (d && d.regions) || [];
    var hit = list.filter(function (r) { return r.code === code; })[0];
    return hit || list[0] || null;
  }
  function regionLabel(cmp, code) {
    var r = region(code);
    return r ? r.code.toUpperCase() + (r.name ? ' · ' + r.name : '') : (code || '');
  }

  /* Places the order through the same API path as the public checkout. */
  function place(cmp, sel, orderType, orderSize) {
    var _ = tr(cmp), A = window.OnhostApi, d = data();
    if (!A || !d) return;
    if (sel.type === 'domain') { placeDomain(cmp, sel); return; }
    var reg = region(sel.region);
    sel = Object.assign({}, sel, { region: reg ? reg.code : null });
    var p = product(sel.type), plan = p && p.plans.filter(function (x) { return x.key === sel.size; })[0];
    if (!p || !plan) { flash(cmp, _('Objednávku nelze sestavit', 'Cannot build the order'), _('Vyberte službu a velikost z katalogu.', 'Pick a service and size from the catalogue.')); return; }
    var name = String(sel.name || '').trim();
    var config = {};
    if (name) config.label = name;
    if (sel.region) config.region_code = sel.region;
    if (p.family === 'game') {
      var eggKeys = (p.eggs || []).map(function (e) { return e.key; });
      var os = String(sel.os || ''), at = os.indexOf('@'), eggKey = at > 0 ? os.slice(0, at) : os; // §5p: `key@version` from the wizard
      config.egg = eggKeys.indexOf(eggKey) >= 0 ? eggKey : (eggKeys[0] || undefined); // the chosen game template
      if (at > 0 && config.egg === eggKey) config.version = os.slice(at + 1);
      var chosen = (p.eggs || []).filter(function (e) { return e.key === config.egg; })[0]; // §5s: values the template cannot start without (a Steam token)
      if (chosen && chosen.min_ram_mb && plan.ram_mb && plan.ram_mb < chosen.min_ram_mb) { // §5t-2: a plan below the template's floor never reaches the quote
        var fits = p.plans.filter(function (x) { return x.ram_mb >= chosen.min_ram_mb; })[0];
        flash(cmp, _('Tarif je pro ' + (chosen.label || chosen.key) + ' malý', 'The plan is too small for ' + (chosen.label || chosen.key)), _('Šablona potřebuje aspoň ' + Math.round(chosen.min_ram_mb / 1024) + ' GB RAM', 'The template needs at least ' + Math.round(chosen.min_ram_mb / 1024) + ' GB RAM') + (fits ? _(' — zvolte například ' + fits.name + '.', ' — pick e.g. ' + fits.name + '.') : '.'));
        return;
      }
      if (chosen && chosen.inputs && chosen.inputs.length) {
        config.environment = {};
        for (var ii = 0; ii < chosen.inputs.length; ii++) {
          var inp = chosen.inputs[ii], v = window.prompt(_('Šablona ' + (chosen.label || chosen.key) + ' potřebuje ' + inp.env + ' (' + inp.rules + '):', 'Template ' + (chosen.label || chosen.key) + ' needs ' + inp.env + ' (' + inp.rules + '):'), '');
          if (v === null || !String(v).trim()) { flash(cmp, _('Objednávka nedokončena', 'Order not finished'), _('Bez hodnoty ' + inp.env + ' server nelze vytvořit.', 'The server cannot be created without ' + inp.env + '.')); return; }
          config.environment[inp.env] = String(v).trim();
        }
      }
      if (name) config.hostname = name.toLowerCase().replace(/[^a-z0-9.-]+/g, '-').replace(/^-+|-+$/g, '');
    } else if (p.family === 'cloud') {
      if (sel.os) config.image = sel.os;
      if (name) config.hostname = name.toLowerCase().replace(/[^a-z0-9.-]+/g, '-').replace(/^-+|-+$/g, '');
    } else if (/^[a-z0-9.-]+\.[a-z]{2,}$/i.test(name)) {
      config.domain = name.toLowerCase();
    }
    var consents = {}, person = (window.ONHOST && window.ONHOST.user && window.ONHOST.user.name) || '';
    Object.keys(d.consents || {}).forEach(function (k) { consents[k] = { version: d.consents[k], person: person }; });
    flash(cmp, _('Odesílám objednávku…', 'Placing the order…'), p.name + ' · ' + plan.name);
    A.put('/cart', { items: [{ product_key: p.key, plan_key: plan.key, qty: 1, config: config }], commit_months: 1, currency: 'CZK', promo_code: null })
      .then(function () { return A.post('/cart/quote', {}); })
      .then(function (q) {
        var quote = q.data || q;
        // /v1/cart/quote reports amounts in minor units (haléře); Money objects carry a decimal string
        var raw = quote.total != null ? quote.total : (quote.totals && (quote.totals.gross || quote.totals.total));
        var total = typeof raw === 'number' ? raw / 100 : num(raw);
        var mode = paymentMode(cmp, sel, total);
        return A.post('/orders', { quote_id: quote.quote_id, consents: consents, payment: paymentPayload(mode), source: 'panel' }, 'panel-order:' + quote.quote_id + ':' + mode)
          .then(function (r) { return { result: r.data || r, mode: mode, total: total }; });
      })
      .then(function (x) {
        if (redirectIfGateway(cmp, x)) return;
        var o = x.result.order || x.result;
        flash(cmp, _('Objednávka ', 'Order ') + (o.number || '') + _(' přijata', ' received'),
          x.mode === 'wallet'
            ? _('Uhrazeno z kreditu (' + money(cmp, x.total) + '). Služba se nasazuje — sledujte sekci Služby, potvrzení jde na e-mail.', 'Paid from credit (' + money(cmp, x.total) + '). The service is being deployed — watch the Services section; the confirmation goes by e-mail.')
            : _('Zálohová faktura je v záložce Fakturace (' + money(cmp, x.total) + '); po připsání platby službu spustíme. Rychlejší je dobít kredit.', 'The proforma is in the Billing tab (' + money(cmp, x.total) + '); the service starts once the payment arrives. Topping up credit is faster.'));
        if (window.OnhostStore && window.OnhostStore.refresh) window.OnhostStore.refresh();
        setTimeout(function () { location.reload(); }, 3000);
      })
      .catch(function (e) { flash(cmp, _('Objednávka neprošla', 'Order failed'), (e && e.message) || _('Zkuste to prosím znovu.', 'Please try again.')); });
  }

  window.OnhostPanelOrder = { types: types, sizes: sizes, images: images, regions: regions, regionLabel: regionLabel, priceLabel: priceLabel, payOptions: payOptions, payLabel: payLabel, hidden: hidden, relabel: relabel, summary: summary, period: period, place: place };
})();
