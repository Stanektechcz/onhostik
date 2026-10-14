<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Nastavení systému · Blog a reference — ONhost</title>
@if ($stylesheet)
<link rel="stylesheet" href="{{ $stylesheet }}">
@endif
<style>
  body { margin: 0; background: var(--color-bg, #f3f2f2); color: var(--color-text, #201e1d); font-family: var(--font-body, "Archivo", system-ui, sans-serif); font-size: 14px; }
  .topbar { display: flex; align-items: center; gap: 16px; padding: 12px 24px; border-bottom: 2px solid var(--color-text, #201e1d); background: var(--color-surface, #eae9e9); flex-wrap: wrap; }
  .topbar .brand { font-family: var(--font-heading, inherit); font-weight: 800; letter-spacing: .08em; text-transform: uppercase; font-size: 13px; }
  .topbar a { color: inherit; }
  .topbar .spacer { flex: 1; }
  main { max-width: 1320px; margin: 0 auto; padding: 24px; display: grid; gap: 24px; }
  h1 { font-size: 26px; margin: 0 0 6px; }
  h2 { font-size: 16px; margin: 0 0 12px; letter-spacing: .04em; text-transform: uppercase; }
  .lead { color: var(--color-neutral-700, #605d5d); max-width: 900px; }
  .panel { border: 2px solid var(--color-text, #201e1d); background: var(--color-bg, #f3f2f2); padding: 18px; overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th { text-align: left; font-size: 10px; letter-spacing: .12em; text-transform: uppercase; color: var(--color-neutral-700, #605d5d); padding: 6px 8px; border-bottom: 1px solid var(--color-divider, #9a9797); }
  td { padding: 8px; border-bottom: 1px solid color-mix(in srgb, var(--color-text, #201e1d) 14%, transparent); vertical-align: top; }
  .tag { display: inline-block; padding: 2px 8px; font-size: 10.5px; letter-spacing: .06em; text-transform: uppercase; border: 1px solid var(--color-text, #201e1d); }
  .tag.ok { background: #b8ff2e; border-color: transparent; color: #1a1918; }
  .tag.off { background: color-mix(in srgb, var(--color-text, #201e1d) 14%, transparent); border-color: transparent; }
  .btn { font: inherit; font-size: 11px; letter-spacing: .06em; text-transform: uppercase; padding: 7px 12px; border: 2px solid var(--color-text, #201e1d); background: transparent; color: inherit; cursor: pointer; }
  .btn.primary { background: var(--color-accent, #ec3013); border-color: var(--color-accent, #ec3013); color: #fff; }
  .actions { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
  .msg { padding: 10px 14px; border-left: 4px solid var(--color-accent, #ec3013); background: color-mix(in srgb, var(--color-accent, #ec3013) 10%, transparent); font-size: 13px; }
  .msg.ok { border-color: #5a9b00; background: color-mix(in srgb, #b8ff2e 30%, transparent); }
  .muted { color: var(--color-neutral-700, #605d5d); }
  .hint { font-size: 12px; color: var(--color-neutral-700, #605d5d); margin-top: 4px; }
  label { display: block; font-size: 10.5px; letter-spacing: .1em; text-transform: uppercase; color: var(--color-neutral-700, #605d5d); margin-bottom: 5px; }
  input[type=text], input[type=number], textarea { font: inherit; padding: 7px 9px; border: 2px solid var(--color-text, #201e1d); background: var(--color-bg, #f3f2f2); color: inherit; width: 100%; box-sizing: border-box; }
  textarea { min-height: 120px; resize: vertical; }
  .grid2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; }
  .field { display: flex; flex-direction: column; gap: 5px; margin-bottom: 12px; }
</style>
</head>
<body>
<header class="topbar">
  <span class="brand">ONhost · Nastavení systému</span>
  <a href="/sprava">← Zpět do administrace</a>
  <a href="/sprava/nastaveni/integrace">Integrace</a>
  <a href="/sprava/nastaveni/tarify">Tarify a verze</a>
  <a href="/sprava/nastaveni/obsah"><strong>Blog a reference</strong></a>
  <span class="spacer"></span>
  <span class="muted">{{ $user->name }} · {{ $user->email }}</span>
</header>
<main>
  <section>
    <h1>Blog a reference</h1>
    <p class="lead">Články blogu a reference zákazníků, jak je ukazuje veřejný web. Text se ukládá jako prostý text (značky HTML se odstraní). Koncept na webu není; zveřejněný článek nejde smazat, jen stáhnout do konceptu nebo archivovat. Referenci zveřejněte jen se souhlasem citovaného zákazníka. Každá změna se zapisuje do auditu.</p>
    <div id="alert" hidden></div>
  </section>

  <section class="panel">
    <h2>Články</h2>
    <table><thead><tr><th>Titulek</th><th>Adresa</th><th>Stav</th><th>Zveřejněno</th><th></th></tr></thead><tbody id="posts"><tr><td colspan="5" class="muted">načítám…</td></tr></tbody></table>
  </section>

  <section class="panel">
    <h2 id="post-heading">Nový článek</h2>
    <form id="post-form">
      <div class="grid2">
        <div class="field"><label for="p-title-cs">Titulek (česky)</label><input type="text" id="p-title-cs" maxlength="250" required></div>
        <div class="field"><label for="p-title-en">Titulek (anglicky)</label><input type="text" id="p-title-en" maxlength="250"></div>
        <div class="field"><label for="p-cat-cs">Kategorie (česky)</label><input type="text" id="p-cat-cs" maxlength="60" required></div>
        <div class="field"><label for="p-cat-en">Kategorie (anglicky)</label><input type="text" id="p-cat-en" maxlength="60"></div>
        <div class="field"><label for="p-slug">Adresa (slug)</label><input type="text" id="p-slug" maxlength="120" pattern="[a-z0-9]+(-[a-z0-9]+)*"><div class="hint">Prázdné = z titulku. Po zveřejnění se adresa nemění.</div></div>
        <div class="field"><label for="p-author">Autor</label><input type="text" id="p-author" maxlength="120" placeholder="Tým ONhost"></div>
        <div class="field"><label for="p-excerpt-cs">Perex (česky)</label><textarea id="p-excerpt-cs" maxlength="500" style="min-height:60px"></textarea></div>
        <div class="field"><label for="p-excerpt-en">Perex (anglicky)</label><textarea id="p-excerpt-en" maxlength="500" style="min-height:60px"></textarea></div>
        <div class="field"><label for="p-body-cs">Text (česky)</label><textarea id="p-body-cs" required style="min-height:220px"></textarea><div class="hint">Odstavce oddělte prázdným řádkem. Řádek začínající „## “ je nadpis oddílu.</div></div>
        <div class="field"><label for="p-body-en">Text (anglicky)</label><textarea id="p-body-en" style="min-height:220px"></textarea></div>
      </div>
      <div class="actions">
        <button class="btn primary" type="submit" id="p-save">Uložit koncept</button>
        <button class="btn" type="button" id="p-reset">Nový článek</button>
      </div>
    </form>
  </section>

  <section class="panel">
    <h2>Reference zákazníků</h2>
    <table><thead><tr><th>Pořadí</th><th>Citace</th><th>Kdo</th><th>Stav</th><th></th></tr></thead><tbody id="refs"><tr><td colspan="5" class="muted">načítám…</td></tr></tbody></table>
  </section>

  <section class="panel">
    <h2 id="ref-heading">Nová reference</h2>
    <form id="ref-form">
      <div class="grid2">
        <div class="field"><label for="r-quote-cs">Citace (česky)</label><textarea id="r-quote-cs" maxlength="600" required style="min-height:80px"></textarea></div>
        <div class="field"><label for="r-quote-en">Citace (anglicky)</label><textarea id="r-quote-en" maxlength="600" style="min-height:80px"></textarea></div>
        <div class="field"><label for="r-who">Jméno citovaného</label><input type="text" id="r-who" maxlength="120" required></div>
        <div class="field"><label for="r-role-cs">Role a firma (česky)</label><input type="text" id="r-role-cs" maxlength="160"></div>
        <div class="field"><label for="r-role-en">Role a firma (anglicky)</label><input type="text" id="r-role-en" maxlength="160"></div>
      </div>
      <div class="actions">
        <button class="btn primary" type="submit">Uložit (nezveřejněno)</button>
        <button class="btn" type="button" id="r-reset">Nová reference</button>
      </div>
    </form>
  </section>
</main>
<script>
(function () {
  var API = '/v1/staff/content';
  var csrf = document.querySelector('meta[name=csrf-token]').getAttribute('content');
  var state = { posts: [], refs: [], post: null, ref: null };
  var $ = function (id) { return document.getElementById(id); };
  var STATE = { draft: 'koncept', published: 'zveřejněno', archived: 'archiv' };
  function xsrf() { var m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/); return m ? decodeURIComponent(m[1]) : ''; }
  function key() { return 'content-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8); }
  function el(tag, text, cls) { var e = document.createElement(tag); if (text != null) e.textContent = String(text); if (cls) e.className = cls; return e; }
  function say(text, ok) { var a = $('alert'); a.hidden = false; a.className = 'msg' + (ok ? ' ok' : ''); a.textContent = text; window.scrollTo({ top: 0, behavior: 'smooth' }); }
  function api(method, path, body) {
    return fetch(API + path, {
      method: method, credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-XSRF-TOKEN': xsrf(), 'X-Requested-With': 'XMLHttpRequest', 'Idempotency-Key': key() },
      body: body ? JSON.stringify(body) : undefined
    }).then(function (r) {
      return r.text().then(function (t) {
        var j = {}; try { j = t ? JSON.parse(t) : {}; } catch (e) { j = { message: t.slice(0, 200) }; }
        if (!r.ok) { throw new Error(j.message || r.statusText); }
        return j;
      });
    });
  }
  function fail(e) { say(e.message || 'Akce se nezdařila.'); }
  /* the body: blocks separated by an empty line; a block whose first line starts with "## " is a heading followed by its paragraph */
  function toSections(text) {
    return String(text || '').split(/\n\s*\n/).map(function (b) { return b.trim(); }).filter(Boolean).map(function (b) {
      if (b.indexOf('## ') === 0) { var nl = b.indexOf('\n'); return nl < 0 ? [b.slice(3).trim(), ''] : [b.slice(3, nl).trim(), b.slice(nl + 1).trim()]; }
      return ['', b];
    });
  }
  function fromSections(list) {
    return (list || []).map(function (s) { return (s[0] ? '## ' + s[0] + (s[1] ? '\n' : '') : '') + (s[1] || ''); }).join('\n\n');
  }
  function button(label, onClick, primary) { var b = el('button', label, 'btn' + (primary ? ' primary' : '')); b.type = 'button'; b.addEventListener('click', onClick); return b; }
  function loc(map, l) { return (map && (map[l] || '')) || ''; }

  function renderPosts() {
    var body = $('posts'); body.textContent = '';
    if (!state.posts.length) { var tr = el('tr'); var td = el('td', 'Zatím žádný článek.', 'muted'); td.colSpan = 5; tr.appendChild(td); body.appendChild(tr); return; }
    state.posts.forEach(function (p) {
      var tr = el('tr');
      tr.appendChild(el('td', loc(p.title, 'cs')));
      tr.appendChild(el('td', p.slug));
      var st = el('td'); st.appendChild(el('span', STATE[p.state] || p.state, 'tag ' + (p.state === 'published' ? 'ok' : 'off'))); tr.appendChild(st);
      tr.appendChild(el('td', p.published_on || '—'));
      var act = el('td'); var box = el('div', null, 'actions');
      box.appendChild(button('Upravit', function () { editPost(p); }));
      if (p.state !== 'published') { box.appendChild(button('Zveřejnit', function () { setPostState(p, 'published'); }, true)); }
      if (p.state === 'published') { box.appendChild(button('Stáhnout do konceptu', function () { setPostState(p, 'draft'); })); }
      if (p.state !== 'archived') { box.appendChild(button('Archivovat', function () { setPostState(p, 'archived'); })); }
      if (p.state !== 'published') { box.appendChild(button('Smazat', function () { if (window.confirm('Smazat článek „' + loc(p.title, 'cs') + '“? Nelze vrátit.')) { api('DELETE', '/posts/' + encodeURIComponent(p.id)).then(function () { say('Článek smazán.', true); load(); }).catch(fail); } })); }
      act.appendChild(box); tr.appendChild(act); body.appendChild(tr);
    });
  }
  function editPost(p) {
    state.post = p; $('post-heading').textContent = 'Úprava: ' + loc(p.title, 'cs');
    $('p-title-cs').value = loc(p.title, 'cs'); $('p-title-en').value = loc(p.title, 'en');
    $('p-cat-cs').value = loc(p.category, 'cs'); $('p-cat-en').value = loc(p.category, 'en');
    $('p-slug').value = p.slug; $('p-slug').disabled = !!p.published_on; $('p-author').value = p.author || '';
    $('p-excerpt-cs').value = loc(p.excerpt, 'cs'); $('p-excerpt-en').value = loc(p.excerpt, 'en');
    $('p-body-cs').value = fromSections((p.body || {}).cs); $('p-body-en').value = fromSections((p.body || {}).en);
    $('p-save').textContent = 'Uložit změny'; $('post-form').scrollIntoView({ behavior: 'smooth' });
  }
  function resetPost() { state.post = null; $('post-form').reset(); $('p-slug').disabled = false; $('post-heading').textContent = 'Nový článek'; $('p-save').textContent = 'Uložit koncept'; }
  function setPostState(p, s) { api('POST', '/posts/' + encodeURIComponent(p.id) + '/state', { state: s }).then(function () { say('Stav článku: ' + (STATE[s] || s) + '.', true); load(); }).catch(fail); }
  $('p-reset').addEventListener('click', resetPost);
  $('post-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var body = {
      title: { cs: $('p-title-cs').value, en: $('p-title-en').value }, category: { cs: $('p-cat-cs').value, en: $('p-cat-en').value },
      excerpt: { cs: $('p-excerpt-cs').value, en: $('p-excerpt-en').value }, author: $('p-author').value || null,
      body: { cs: toSections($('p-body-cs').value), en: toSections($('p-body-en').value) }
    };
    if (!$('p-slug').disabled && $('p-slug').value) { body.slug = $('p-slug').value; }
    var req = state.post ? api('PATCH', '/posts/' + encodeURIComponent(state.post.id), body) : api('POST', '/posts', body);
    req.then(function () { say(state.post ? 'Článek uložen.' : 'Koncept uložen; na webu se objeví po zveřejnění.', true); resetPost(); load(); }).catch(fail);
  });

  function renderRefs() {
    var body = $('refs'); body.textContent = '';
    if (!state.refs.length) { var tr = el('tr'); var td = el('td', 'Zatím žádná reference.', 'muted'); td.colSpan = 5; tr.appendChild(td); body.appendChild(tr); return; }
    state.refs.forEach(function (r, i) {
      var tr = el('tr');
      var ord = el('td'); var ob = el('div', null, 'actions');
      if (i > 0) { ob.appendChild(button('↑', function () { move(i, -1); })); }
      if (i < state.refs.length - 1) { ob.appendChild(button('↓', function () { move(i, 1); })); }
      ord.appendChild(ob); tr.appendChild(ord);
      tr.appendChild(el('td', loc(r.quote, 'cs')));
      tr.appendChild(el('td', r.who + (loc(r.role, 'cs') ? ' · ' + loc(r.role, 'cs') : '')));
      var st = el('td'); st.appendChild(el('span', r.published ? 'zveřejněno' : 'skryto', 'tag ' + (r.published ? 'ok' : 'off')));
      if (r.demo) { st.appendChild(document.createTextNode(' ')); st.appendChild(el('span', 'DEMO', 'tag')); }
      tr.appendChild(st);
      var act = el('td'); var box = el('div', null, 'actions');
      box.appendChild(button('Upravit', function () { editRef(r); }));
      box.appendChild(button(r.published ? 'Skrýt' : 'Zveřejnit', function () { api('POST', '/references/' + encodeURIComponent(r.id) + '/state', { published: !r.published }).then(function () { say(r.published ? 'Reference skryta.' : 'Reference zveřejněna.', true); load(); }).catch(fail); }, !r.published));
      box.appendChild(button('Smazat', function () { if (window.confirm('Smazat referenci od ' + r.who + '?')) { api('DELETE', '/references/' + encodeURIComponent(r.id)).then(function () { say('Reference smazána.', true); load(); }).catch(fail); } }));
      act.appendChild(box); tr.appendChild(act); body.appendChild(tr);
    });
  }
  function move(i, d) {
    var ids = state.refs.map(function (r) { return r.id; }); var t = ids[i]; ids[i] = ids[i + d]; ids[i + d] = t;
    api('POST', '/references/order', { ids: ids }).then(function () { load(); }).catch(fail);
  }
  function editRef(r) {
    state.ref = r; $('ref-heading').textContent = 'Úprava reference: ' + r.who;
    $('r-quote-cs').value = loc(r.quote, 'cs'); $('r-quote-en').value = loc(r.quote, 'en'); $('r-who').value = r.who;
    $('r-role-cs').value = loc(r.role, 'cs'); $('r-role-en').value = loc(r.role, 'en'); $('ref-form').scrollIntoView({ behavior: 'smooth' });
  }
  function resetRef() { state.ref = null; $('ref-form').reset(); $('ref-heading').textContent = 'Nová reference'; }
  $('r-reset').addEventListener('click', resetRef);
  $('ref-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var body = { quote: { cs: $('r-quote-cs').value, en: $('r-quote-en').value }, who: $('r-who').value, role: { cs: $('r-role-cs').value, en: $('r-role-en').value } };
    var req = state.ref ? api('PATCH', '/references/' + encodeURIComponent(state.ref.id), body) : api('POST', '/references', body);
    req.then(function () { say('Reference uložena.', true); resetRef(); load(); }).catch(fail);
  });

  function load() {
    api('GET', '/posts?limit=100').then(function (j) { state.posts = j.data || []; renderPosts(); }).catch(fail);
    api('GET', '/references?limit=200').then(function (j) { state.refs = j.data || []; renderRefs(); }).catch(fail);
  }
  load();
})();
</script>
</body>
</html>
