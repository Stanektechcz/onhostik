<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Nastavení systému · Integrace providerů — ONhost</title>
@if ($stylesheet)
<link rel="stylesheet" href="{{ $stylesheet }}">
@endif
<style>
  body { margin: 0; background: var(--color-bg, #f3f2f2); color: var(--color-text, #201e1d); font-family: var(--font-body, "Archivo", system-ui, sans-serif); font-size: 14px; }
  .topbar { display: flex; align-items: center; gap: 16px; padding: 12px 24px; border-bottom: 2px solid var(--color-text, #201e1d); background: var(--color-surface, #eae9e9); }
  .topbar .brand { font-family: var(--font-heading, inherit); font-weight: 800; letter-spacing: .08em; text-transform: uppercase; font-size: 13px; }
  .topbar a { color: inherit; }
  .topbar .spacer { flex: 1; }
  main { max-width: 1320px; margin: 0 auto; padding: 24px; display: grid; gap: 24px; }
  h1 { font-size: 26px; margin: 0 0 6px; }
  h2 { font-size: 16px; margin: 0 0 12px; letter-spacing: .04em; text-transform: uppercase; }
  .lead { color: var(--color-neutral-700, #605d5d); max-width: 900px; }
  .panel { border: 2px solid var(--color-text, #201e1d); background: var(--color-bg, #f3f2f2); padding: 18px; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th { text-align: left; font-size: 10px; letter-spacing: .12em; text-transform: uppercase; color: var(--color-neutral-700, #605d5d); padding: 6px 8px; border-bottom: 1px solid var(--color-divider, #999); }
  td { padding: 9px 8px; border-bottom: 1px solid color-mix(in srgb, var(--color-text, #201e1d) 14%, transparent); vertical-align: top; }
  .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; }
  .tag { display: inline-block; padding: 2px 8px; font-size: 10.5px; letter-spacing: .06em; text-transform: uppercase; border: 1px solid var(--color-text, #201e1d); }
  .tag.ok { background: #b8ff2e; border-color: transparent; color: #1a1918; }
  .tag.warn { background: #e0a100; border-color: transparent; color: #1a1918; }
  .tag.bad { background: var(--color-accent, #ec3013); border-color: transparent; color: #fff; }
  .tag.off { background: color-mix(in srgb, var(--color-text, #201e1d) 14%, transparent); border-color: transparent; }
  .btn { font: inherit; font-size: 11px; letter-spacing: .06em; text-transform: uppercase; padding: 7px 12px; border: 2px solid var(--color-text, #201e1d); background: transparent; color: inherit; cursor: pointer; }
  .btn.primary { background: var(--color-accent, #ec3013); border-color: var(--color-accent, #ec3013); color: #fff; }
  .btn:disabled { opacity: .5; cursor: default; }
  .actions { display: flex; gap: 6px; flex-wrap: wrap; }
  form.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px; }
  .field { display: flex; flex-direction: column; gap: 5px; }
  .field label { font-size: 10.5px; letter-spacing: .1em; text-transform: uppercase; color: var(--color-neutral-700, #605d5d); }
  .field input, .field select, .field textarea { font: inherit; font-size: 13px; padding: 8px 10px; border: 1px solid var(--color-text, #201e1d); background: #fff; color: inherit; }
  .field textarea { min-height: 84px; font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 12px; }
  .field .hint { font-size: 11.5px; color: var(--color-neutral-700, #605d5d); }
  .span2 { grid-column: span 2; }
  .checks { display: flex; flex-wrap: wrap; gap: 10px 18px; font-size: 13px; }
  .console { background: #1a1918; color: #d9d7d2; padding: 12px 14px; min-height: 80px; max-height: 360px; overflow: auto; white-space: pre-wrap; word-break: break-word; font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 12px; }
  .msg { padding: 10px 14px; border-left: 4px solid var(--color-accent, #ec3013); background: color-mix(in srgb, var(--color-accent, #ec3013) 10%, transparent); font-size: 13px; }
  .msg.ok { border-color: #5a9b00; background: color-mix(in srgb, #b8ff2e 30%, transparent); }
  dialog { border: 2px solid var(--color-text, #201e1d); padding: 20px; max-width: 420px; font: inherit; }
  dialog::backdrop { background: rgba(0,0,0,.45); }
  .muted { color: var(--color-neutral-700, #605d5d); }
  details summary { cursor: pointer; font-size: 12px; letter-spacing: .06em; text-transform: uppercase; }
  .nodes { margin-top: 10px; }
</style>
</head>
<body data-env="{{ $environment }}">
<header class="topbar">
  <span class="brand">ONhost · Nastavení systému</span>
  <a href="/sprava">← Zpět do administrace</a>
  <a href="/sprava/nastaveni/integrace"><strong>Integrace</strong></a>
  <a href="/sprava/nastaveni/provoz">Provoz</a>
  <a href="/sprava/nastaveni/zivotni-cyklus">Životní cyklus služeb</a>
  <span class="spacer"></span>
  <span class="muted">{{ $user->name }} · {{ $user->email }}</span>
</header>
<main>
  <section>
    <h1>Integrace providerů</h1>
    <p class="lead">Instance vendor API (Proxmox, PBS, ISPConfig, aaPanel, Pterodactyl, PowerDNS, WEDOS, Kubernetes) se zde registrují, prověřují a uvádějí do provozu. Přístupové údaje se ukládají šifrovaně v úložišti tajemství (<span class="mono">db://provider_instances/&lt;klíč&gt;</span>), nikdy se nezobrazují zpět. Změny s vysokým rizikem vyžadují druhé ověření (TOTP / záložní kód).</p>
    <div id="alert" hidden></div>
  </section>

  <section class="panel">
    <h2>Instance</h2>
    <table id="instances">
      <thead><tr><th>Klíč</th><th>Provider</th><th>Název · region</th><th>URL</th><th>Stav</th><th>Zdraví</th><th>Přístupy</th><th>Akce</th></tr></thead>
      <tbody><tr><td colspan="8" class="muted">Načítám…</td></tr></tbody>
    </table>
    <div id="detail" class="nodes" hidden></div>
  </section>

  <section class="panel">
    <h2 id="form-title">Přidat instanci</h2>
    <form id="instance-form" class="grid" autocomplete="off">
      <div class="field"><label for="f-provider">Provider</label><select id="f-provider" name="provider" required></select><span class="hint" id="provider-hint"></span></div>
      <div class="field"><label for="f-key">Klíč</label><input id="f-key" name="key" pattern="[a-z0-9][a-z0-9-]{1,58}" placeholder="proxmox-cz1" required><span class="hint">malá písmena, číslice, pomlčky · po vytvoření neměnný</span></div>
      <div class="field"><label for="f-name">Název</label><input id="f-name" name="name" placeholder="PVE Praha 1"></div>
      <div class="field"><label for="f-region">Region</label><select id="f-region" name="region_code"><option value="">— bez regionu —</option></select><span class="hint">uzly se plánují podle regionu</span></div>
      <div class="field span2"><label for="f-base">Base URL</label><input id="f-base" name="base_url" type="url" placeholder="https://pve.onhost.internal:8006" required><span class="hint">aaPanel: host a port bez bezpečnostní cesty · ISPConfig: kořen panelu (bez /remote/json.php)</span></div>
      <div class="field"><label for="f-state">Stav</label><select id="f-state" name="state"><option value="active">active</option><option value="maintenance">maintenance</option><option value="draining">draining</option><option value="disabled">disabled</option></select></div>
      <div class="field"><label>Schopnosti</label><div class="checks" id="f-capabilities"></div></div>
      <div class="field span2"><label>Přístupové údaje</label><div class="grid" id="f-credentials" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px"></div><span class="hint">prázdné pole ponechá uloženou hodnotu</span></div>
      <div class="field"><label><input type="checkbox" id="f-verify" checked> ověřovat TLS certifikát</label><span class="hint">vypnutí je možné jen mimo produkci; v produkci připněte certifikát níže</span></div>
      <div class="field span2"><label for="f-ca">Připnutý certifikát / CA (PEM)</label><textarea id="f-ca" placeholder="-----BEGIN CERTIFICATE-----&#10;…&#10;-----END CERTIFICATE-----"></textarea><span class="hint">pro samopodepsané certifikáty (Proxmox, PBS, aaPanel); uloží se jako option tls_ca</span></div>
      <div class="field span2"><label for="f-options">Další volby (JSON)</label><textarea id="f-options" placeholder='{"storage": "nvme", "bridge": "vmbr0", "server_id": 1, "shell": "api|ssh", "ssh_host": "…", "agent_chroot": "jailkit", "webmail_url": "https://…", "deploy_strategy": "symlink|rsync", "redis_host": "127.0.0.1"}'></textarea><span class="hint">Proxmox: storage, bridge, pbs_datastore · ISPConfig: server_id / server_ip (jinak se odvodí) · Pterodactyl: nest_id, egg_id · PowerDNS: server_id</span></div>
      <div class="actions span2">
        <button class="btn primary" type="submit" id="f-submit">Uložit instanci</button>
        <button class="btn" type="button" id="f-reset">Nová instance</button>
      </div>
    </form>
  </section>

  <section class="panel">
    <h2>Umístění tarifů</h2>
    <p class="lead">Který panel (instance) a případně který server obsluhuje daný produkt nebo tarif. Bez umístění plánovač vybere libovolný použitelný uzel dané role v regionu; umístění tarifu má přednost před umístěním produktu. Zákazník název panelu nikdy nevidí.</p>
    <table id="placements">
      <thead><tr><th>Produkt</th><th>Tarif</th><th>Region</th><th>Instance (panel)</th><th>Server (uzel)</th><th>Stav</th><th>Akce</th></tr></thead>
      <tbody><tr><td colspan="7" class="muted">Načítám…</td></tr></tbody>
    </table>
    <form id="placement-form" class="grid" style="margin-top:16px" autocomplete="off">
      <div class="field"><label for="p-product">Produkt</label><select id="p-product" required></select></div>
      <div class="field"><label for="p-plan">Tarif</label><select id="p-plan"><option value="">— celý produkt —</option></select></div>
      <div class="field"><label for="p-region">Region</label><select id="p-region"><option value="">— všechny regiony —</option></select></div>
      <div class="field"><label for="p-instance">Instance (panel)</label><select id="p-instance" required></select><span class="hint" id="p-compatible"></span></div>
      <div class="field"><label for="p-node">Server (uzel)</label><select id="p-node"><option value="">— libovolný uzel instance —</option></select></div>
      <div class="field"><label for="p-note">Poznámka</label><input id="p-note" maxlength="250" placeholder="např. Profi tarify na výkonnějším panelu"></div>
      <div class="actions span2"><button class="btn primary" type="submit">Uložit umístění</button></div>
    </form>
  </section>

  <section class="panel">
    <h2>Registrátoři domén</h2>
    <p class="lead">Naše prodejní ceny domén jsou v ceníku; zde jsou nákupní ceny každého napojeného registrátora. Novou registraci nebo transfer dostane registrátor s nejnižší nákupní cenou (porovnání v CZK), prodloužení zůstává u registrátora, který doménu drží. TLD lze připnout k jednomu registrátorovi. Registrátoři s ceníkovým API se aktualizují denně, ostatním se cena udržuje ručně. Zákazník název registrátora nikdy nevidí.</p>
    <div class="actions" style="margin-bottom:12px"><button class="btn" type="button" id="r-refresh">Aktualizovat ceníky z API</button> <button class="btn" type="button" id="r-scrape">Načíst veřejné ceníky (web registrátorů)</button> <span class="hint" id="r-meta"></span></div>
    <p class="hint">Veřejný ceník je maloobchodní cena registrátora, tedy horní odhad našich nákladů; ručně zadaná nebo z API načtená cena má přednost a scrape ji nepřepíše.</p>
    <table id="registrars">
      <thead><tr><th>TLD</th><th>Naše cena (CZK reg. / obnova)</th><th id="r-heads">Nákupní ceny</th><th>Vítěz</th><th>Marže</th><th>Připnout</th></tr></thead>
      <tbody><tr><td colspan="6" class="muted">Načítám…</td></tr></tbody>
    </table>
    <form id="cost-form" class="grid" style="margin-top:16px" autocomplete="off">
      <div class="field"><label for="c-registrar">Registrátor</label><select id="c-registrar" required></select></div>
      <div class="field"><label for="c-tld">TLD</label><input id="c-tld" required maxlength="32" placeholder="cz"></div>
      <div class="field"><label for="c-currency">Měna</label><select id="c-currency"><option>CZK</option><option>EUR</option><option>USD</option></select></div>
      <div class="field"><label for="c-register">Registrace</label><input id="c-register" type="number" step="0.01" min="0" placeholder="165"></div>
      <div class="field"><label for="c-renew">Obnova</label><input id="c-renew" type="number" step="0.01" min="0"></div>
      <div class="field"><label for="c-transfer">Transfer</label><input id="c-transfer" type="number" step="0.01" min="0"></div>
      <div class="field"><label for="c-note">Poznámka</label><input id="c-note" maxlength="250" placeholder="např. ceník z 1. 9. 2026"></div>
      <div class="actions span2"><button class="btn primary" type="submit">Uložit nákupní cenu</button></div>
    </form>
  </section>

  <section class="panel" id="connections">
    <h2>Zákaznická připojení registrátorů</h2>
    <p class="lead">Účty WEDOS, které si zákazníci připojili vlastním WAPI heslem (uloženo v úložišti tajemství, zde se nezobrazuje). Platforma z nich zrcadlí domény a zóny, hlídá expirace a kredit a páruje domény s hostingem; zákaznické instance nikdy neslouží platformě. Pozastavení zastaví synchronizaci i párování, dokud ho neobnovíte.</p>
    <table id="connections-table">
      <thead><tr><th>Organizace</th><th>Účet</th><th>Stav</th><th>Domény / zóny</th><th>Kredit</th><th>Poslední sync</th><th>Chyba</th><th>Akce</th></tr></thead>
      <tbody><tr><td colspan="8" class="muted">Načítám…</td></tr></tbody>
    </table>
  </section>

  <section class="panel" id="pricing">
    <h2>Slevy, závazky a promo kódy</h2>
    <p class="lead">Delší závazek sám o sobě slevu nedává: procento za 12 nebo 24 měsíců platí jen pro rodiny produktů, kde ho zde schválíte (řádek „výchozí“ platí pro ostatní). Domény se prodávají za ceníkovou cenu na celé roky, nejméně na jeden; sleva na koncovku platí jen v zadaném období a zobrazí se zákazníkovi s popiskem. Promo kódy platí pro vybrané rodiny produktů (nic nevybráno = všechny). Web i košík přebírají tato pravidla okamžitě.</p>
    <h3>Závazkové slevy (% z ceny prvního období)</h3>
    <table id="commit-table">
      <thead><tr><th>Rodina produktů</th><th>12 měsíců</th><th>24 měsíců</th></tr></thead>
      <tbody><tr><td colspan="3" class="muted">Načítám…</td></tr></tbody>
    </table>
    <div class="actions" style="margin:10px 0 18px"><button class="btn primary" type="button" id="commit-save">Uložit závazkové slevy</button></div>
    <h3>Slevy na domény</h3>
    <table id="domain-discounts">
      <thead><tr><th>TLD</th><th>Registrace</th><th>Obnova</th><th>Transfer</th><th>Platí</th><th>Popisek</th><th></th></tr></thead>
      <tbody><tr><td colspan="7" class="muted">Načítám…</td></tr></tbody>
    </table>
    <form id="dd-form" class="grid" style="margin-top:12px" autocomplete="off">
      <div class="field"><label for="dd-tld">TLD</label><select id="dd-tld" required></select></div>
      <div class="field"><label for="dd-register">Registrace %</label><input id="dd-register" type="number" min="0" max="100" step="0.5" value="0"></div>
      <div class="field"><label for="dd-renew">Obnova %</label><input id="dd-renew" type="number" min="0" max="100" step="0.5" value="0"></div>
      <div class="field"><label for="dd-transfer">Transfer %</label><input id="dd-transfer" type="number" min="0" max="100" step="0.5" value="0"></div>
      <div class="field"><label for="dd-from">Platí od</label><input id="dd-from" type="date"></div>
      <div class="field"><label for="dd-to">Platí do</label><input id="dd-to" type="date"></div>
      <div class="field"><label for="dd-label">Popisek (vidí zákazník)</label><input id="dd-label" maxlength="120" placeholder="např. Podzimní akce .cz"></div>
      <div class="actions span2"><button class="btn primary" type="submit">Uložit slevu na doménu</button></div>
    </form>
    <h3>Promo kódy</h3>
    <table id="promos">
      <thead><tr><th>Kód</th><th>Sleva</th><th>Platí pro</th><th>Platnost</th><th>Použití</th><th>Stav</th><th></th></tr></thead>
      <tbody><tr><td colspan="7" class="muted">Načítám…</td></tr></tbody>
    </table>
    <form id="promo-form" class="grid" style="margin-top:12px" autocomplete="off">
      <div class="field"><label for="pc-code">Kód</label><input id="pc-code" required maxlength="40" placeholder="JARO2026"></div>
      <div class="field"><label for="pc-kind">Typ</label><select id="pc-kind"><option value="percent">procenta</option><option value="fixed">pevná částka</option></select></div>
      <div class="field"><label for="pc-value">Hodnota</label><input id="pc-value" type="number" min="0" step="0.01" required placeholder="10"></div>
      <div class="field"><label for="pc-currency">Měna (pevná částka)</label><select id="pc-currency"><option>CZK</option><option>EUR</option></select></div>
      <div class="field"><label for="pc-from">Platí od</label><input id="pc-from" type="date"></div>
      <div class="field"><label for="pc-to">Platí do</label><input id="pc-to" type="date"></div>
      <div class="field"><label for="pc-max">Max. počet použití</label><input id="pc-max" type="number" min="1" step="1"></div>
      <div class="field"><label for="pc-state">Stav</label><select id="pc-state"><option value="active">aktivní</option><option value="paused">pozastavený</option><option value="retired">ukončený</option></select></div>
      <div class="field span2"><label>Platí pro rodiny produktů</label><div id="pc-families" style="display:flex;flex-wrap:wrap;gap:10px 16px"></div></div>
      <div class="field span2"><label><input id="pc-first" type="checkbox" checked> jen první období (obnovy za ceníkovou cenu)</label></div>
      <div class="actions span2"><button class="btn primary" type="submit">Uložit promo kód</button></div>
    </form>
  </section>

  <section class="panel" id="addons">
    <h2>Doplňky a konfigurátor</h2>
    <p class="lead">Ke každému produktu určíte, které doplňkové produkty se u něj v košíku nabídnou (každá položka košíku má své doplňky), a ceník jeho volitelných parametrů: příplatky u pevných tarifů (přepínač, posuvník navíc, výběr) i parametry tarifu na míru — u produktu s konfigurátorem je hodnota posuvníku absolutní („3 weby“), u pevných tarifů je navíc k tarifu („+10 GB“). Cena za jednotku je měsíční bez DPH; web, konfigurátor i košík ji přebírají okamžitě.</p>
    <div class="field" style="max-width:420px"><label for="ao-product">Produkt</label><select id="ao-product"></select></div>
    <h3>Doplňkové produkty nabízené v košíku</h3>
    <div id="ao-products" style="display:flex;flex-wrap:wrap;gap:10px 18px;margin-bottom:8px"></div>
    <div class="actions" style="margin:6px 0 18px"><button class="btn primary" type="button" id="ao-products-save">Uložit doplňkové produkty</button></div>
    <h3>Volitelné parametry a příplatky</h3>
    <table id="ao-options">
      <thead><tr><th>Klíč</th><th>Typ</th><th>Název</th><th>Rozsah / volby</th><th>Cena za jednotku</th><th>Entitlement</th><th></th></tr></thead>
      <tbody><tr><td colspan="7" class="muted">Vyberte produkt.</td></tr></tbody>
    </table>
    <form id="ao-form" class="grid" style="margin-top:12px" autocomplete="off">
      <div class="field"><label for="op-key">Klíč (a–z, 0–9, _)</label><input id="op-key" required maxlength="60" placeholder="nvme_gb"></div>
      <div class="field"><label for="op-kind">Typ</label><select id="op-kind"><option value="addon">přepínač (ano/ne)</option><option value="slider">posuvník</option><option value="select">výběr z možností</option></select></div>
      <div class="field"><label for="op-label-cs">Název (cs)</label><input id="op-label-cs" required maxlength="120" placeholder="Prostor navíc"></div>
      <div class="field"><label for="op-label-en">Název (en)</label><input id="op-label-en" maxlength="120" placeholder="Extra storage"></div>
      <div class="field span2"><label for="op-desc">Popis (cs)</label><input id="op-desc" maxlength="250" placeholder="NVMe nad rámec tarifu"></div>
      <div class="field"><label for="op-unit">Jednotka</label><input id="op-unit" maxlength="24" placeholder="GB"></div>
      <div class="field"><label for="op-price">Cena CZK / jednotka / měsíc</label><input id="op-price" type="number" min="0" step="0.01" required placeholder="3"></div>
      <div class="field"><label for="op-price-eur">Cena EUR (prázdné = přepočet)</label><input id="op-price-eur" type="number" min="0" step="0.01"></div>
      <div class="field"><label for="op-min">Min (posuvník)</label><input id="op-min" type="number" step="any"></div>
      <div class="field"><label for="op-max">Max (posuvník)</label><input id="op-max" type="number" step="any"></div>
      <div class="field"><label for="op-step">Krok</label><input id="op-step" type="number" step="any"></div>
      <div class="field"><label for="op-default">Výchozí hodnota (v ceně)</label><input id="op-default" type="number" step="any"></div>
      <div class="field span2"><label for="op-choices">Volby výběru (řádek = klíč | popisek | násobek ceny)</label><textarea id="op-choices" rows="3" placeholder="backup-7 | 7 dní | 0&#10;backup-30 | 30 dní | 1&#10;backup-90 | 90 dní | 2.5"></textarea></div>
      <div class="field"><label for="op-ent-key">Entitlement (klíč parametru služby)</label><input id="op-ent-key" maxlength="40" placeholder="nvme_gb"></div>
      <div class="field"><label for="op-ent-mode">Režim hodnoty</label><select id="op-ent-mode"><option value="extra">navíc k tarifu</option><option value="absolute">absolutní (konfigurátor)</option></select></div>
      <div class="actions span2"><button class="btn primary" type="submit">Uložit parametr</button></div>
    </form>
  </section>

  <section class="panel" id="bank">
    <h2>Bankovní platby</h2>
    <p class="lead">Převody nemají webhook: příchozí platby se párují podle variabilního symbolu a částky na čekající zálohové faktury a dobití kreditu. S tokenem Fio API (<code>ONHOST_BANK_FIO_TOKEN</code>) se výpis stahuje automaticky každých 5 minut; platbu z výpisu jiné banky zaznamenejte ručně níže. Spárování zaplatí zálohovou fakturu a spustí zřízení služeb (u dobití připíše kredit) a vystaví doklad o přijetí platby. Stejný řádek výpisu se nikdy nezaúčtuje dvakrát.</p>
    <div class="actions" style="margin:0 0 12px;align-items:center"><button class="btn" type="button" id="bank-sync">Stáhnout výpis z banky (Fio)</button><span class="hint" id="bank-fio"></span></div>
    <h3>Čeká na platbu převodem</h3>
    <table id="bank-pending">
      <thead><tr><th>VS</th><th>Částka</th><th>Účel</th><th>Zákazník</th><th>Vytvořeno</th><th></th></tr></thead>
      <tbody><tr><td colspan="6" class="muted">Načítám…</td></tr></tbody>
    </table>
    <h3>Zaznamenat příchozí platbu</h3>
    <form id="bank-form" class="grid" style="margin-top:12px" autocomplete="off">
      <div class="field"><label for="bk-vs">Variabilní symbol</label><input id="bk-vs" required maxlength="20" placeholder="20260012"></div>
      <div class="field"><label for="bk-amount">Částka</label><input id="bk-amount" type="number" min="0.01" step="0.01" required placeholder="809.49"></div>
      <div class="field"><label for="bk-currency">Měna</label><select id="bk-currency"><option>CZK</option><option>EUR</option></select></div>
      <div class="field"><label for="bk-date">Datum připsání</label><input id="bk-date" type="date"></div>
      <div class="field"><label for="bk-ext">ID pohybu z výpisu</label><input id="bk-ext" maxlength="190" placeholder="nepovinné — chrání před dvojím zadáním"></div>
      <div class="field"><label for="bk-counterparty">Protiúčet / plátce</label><input id="bk-counterparty" maxlength="190"></div>
      <div class="field span2"><label for="bk-msg">Zpráva pro příjemce</label><input id="bk-msg" maxlength="250"></div>
      <div class="actions span2"><button class="btn primary" type="submit">Zaznamenat a spárovat</button></div>
    </form>
    <h3>Poslední řádky výpisu</h3>
    <table id="bank-lines">
      <thead><tr><th>Připsáno</th><th>VS</th><th>Částka</th><th>Plátce</th><th>Zpráva</th><th>Stav</th></tr></thead>
      <tbody><tr><td colspan="6" class="muted">Načítám…</td></tr></tbody>
    </table>
  </section>

  <section class="panel" id="nav">
    <h2>Navigace klientského panelu</h2>
    <p class="lead">Levé menu klientské sekce se skládá z kategorií služeb. Kategorie se zobrazí, když je zapnutá a katalog v ní něco aktivně nabízí; zákazníkovi, který v ní už službu má, nezmizí nikdy. Vypnutá kategorie se neobjeví ani v průvodci objednávkou. Pořadí a názvy jsou vaše, kategorie samotné dává katalog (produkty s executorem ISPConfig, aaPanel, Pterodactyl, Proxmox a registrátoři domén).</p>
    <table id="nav-cats">
      <thead><tr><th>Pořadí</th><th>Zapnuto</th><th>Kategorie</th><th>Název (cs)</th><th>Název (en)</th><th>Katalog nabízí</th><th>Zákazníků se službou</th></tr></thead>
      <tbody><tr><td colspan="7" class="muted">Načítám…</td></tr></tbody>
    </table>
    <h3>Volitelné položky menu</h3>
    <div id="nav-links" class="grid" style="margin-top:8px"></div>
    <div class="actions" style="margin-top:12px;align-items:center"><button class="btn primary" type="button" id="nav-save">Uložit navigaci</button><span class="hint">Klientský panel se přizpůsobí po obnovení stránky.</span></div>
  </section>

  <section class="panel">
    <h2>Výsledek poslední akce</h2>
    <div id="console" class="console">—</div>
  </section>
</main>

<dialog id="stepup">
  <form method="dialog" id="stepup-form">
    <h2>Druhé ověření</h2>
    <p class="muted">Tato akce má vysoké riziko. Zadejte kód z autentikátoru nebo záložní kód.</p>
    <div class="field"><label for="s-method">Metoda</label><select id="s-method"><option value="totp">TOTP (autentikátor)</option><option value="recovery">záložní kód</option><option value="password">heslo (jen dokud není zapnuté TOTP a mimo produkci)</option></select></div>
    <div class="field" style="margin-top:10px"><label for="s-code">Kód</label><input id="s-code" inputmode="numeric" autocomplete="one-time-code" required></div>
    <div class="actions" style="margin-top:14px"><button class="btn primary" value="ok">Ověřit a pokračovat</button><button class="btn" value="cancel" type="button" id="s-cancel">Zrušit</button></div>
    <p class="hint" id="s-error" style="color:#ae1800"></p>
  </form>
</dialog>

<dialog id="node-dialog">
  <form method="dialog" id="node-form">
    <h2>Uzel instance <span id="n-instance" class="mono"></span></h2>
    <div class="grid" style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <div class="field"><label for="n-name">Název</label><input id="n-name" required placeholder="web01"></div>
      <div class="field"><label for="n-role">Role</label><select id="n-role"><option>web</option><option>managed</option><option>mail</option><option>dns</option><option>game</option><option>apps</option><option>backup</option><option>compute</option></select></div>
      <div class="field"><label for="n-region">Region</label><select id="n-region"><option value="">— region instance —</option></select></div>
      <div class="field"><label for="n-state">Stav</label><select id="n-state"><option>active</option><option>maintenance</option><option>draining</option><option>disabled</option></select></div>
      <div class="field" style="grid-column:span 2"><label for="n-capacity">Kapacita (JSON)</label><textarea id="n-capacity" placeholder='{"cpu_cores": 16, "ram_mb": 65536, "disk_gb": 2000, "sites": 500}'></textarea></div>
      <div class="field" style="grid-column:span 2"><label for="n-tags">Tagy (JSON)</label><textarea id="n-tags" placeholder='{"ipv4": "185.66.14.8"}'></textarea></div>
    </div>
    <div class="actions" style="margin-top:14px"><button class="btn primary" value="ok">Uložit uzel</button><button class="btn" type="button" value="cancel" id="n-cancel">Zrušit</button></div>
    <p class="hint" id="n-error" style="color:#ae1800"></p>
  </form>
</dialog>

<script>
(function () {
  'use strict';
  var API = '/v1';
  var csrf = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
  var state = { schema: null, instances: [], editing: null, retry: null, detailKey: null };
  var $ = function (id) { return document.getElementById(id); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var xsrf = function () { var m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/); return m ? decodeURIComponent(m[1]) : ''; };
  var key = function () { return 'settings-' + Date.now().toString(36) + Math.random().toString(36).slice(2, 8); };

  function api(method, path, body) {
    return fetch(API + path, {
      method: method, credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-XSRF-TOKEN': xsrf(), 'X-Requested-With': 'XMLHttpRequest', 'Idempotency-Key': key() },
      body: body ? JSON.stringify(body) : undefined
    }).then(function (r) {
      return r.text().then(function (t) {
        var j = {}; try { j = t ? JSON.parse(t) : {}; } catch (e) { j = { message: t.slice(0, 200) }; }
        if (!r.ok) { var e = new Error(j.message || r.statusText); e.error = j.error; e.status = r.status; e.errors = j.errors; e.payload = j; throw e; }
        return j;
      });
    });
  }

  function say(text, ok) { var a = $('alert'); a.hidden = false; a.className = 'msg' + (ok ? ' ok' : ''); a.textContent = text; }
  function log(title, data) { $('console').textContent = title + '\n' + (typeof data === 'string' ? data : JSON.stringify(data, null, 2)); }

  /* HIGH-risk commands answer step_up_required until the session holds a fresh grant: ask, verify, retry. */
  function guarded(action) {
    return action().catch(function (e) {
      if (e.error !== 'step_up_required') throw e;
      return new Promise(function (resolve, reject) {
        state.retry = function () { return action().then(resolve, reject); };
        state.cancel = function () { reject(new Error('Druhé ověření zrušeno — akce neproběhla.')); };
        $('s-error').textContent = ''; $('s-code').value = '';
        $('stepup').showModal();
      });
    });
  }
  $('stepup-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    api('POST', '/auth/step-up', { method: $('s-method').value, code: $('s-code').value.trim() }).then(function () {
      $('stepup').close(); var r = state.retry; state.retry = null; state.cancel = null;
      if (r) r(); // outcome flows to the caller's own then/catch
    }).catch(function (e) { $('s-error').textContent = e.message || 'Ověření se nezdařilo.'; });
  });
  $('s-cancel').addEventListener('click', function () { $('stepup').close(); state.retry = null; var c = state.cancel; state.cancel = null; if (c) c(); });

  function healthTag(i) {
    var h = i.integration || {};
    if (h.up === true) return '<span class="tag ok">up</span> <span class="muted">' + esc(h.p95_ms || i.health && i.health.latency_ms || '') + (h.p95_ms || (i.health && i.health.latency_ms) ? ' ms' : '') + '</span>';
    if (h.up === false) return '<span class="tag bad">down</span> <span class="muted" title="' + esc(h.last_error || (i.health && i.health.error) || '') + '">' + esc(((h.last_error || (i.health && i.health.error) || '')).slice(0, 60)) + '</span>';
    return '<span class="tag off">nezkoušeno</span>';
  }
  function stateTag(s) { return '<span class="tag ' + (s === 'active' ? 'ok' : s === 'disabled' ? 'off' : 'warn') + '">' + esc(s) + '</span>'; }
  function credTag(i) {
    var c = i.credentials || {}; var missing = c.missing || [];
    if (missing.length) return '<span class="tag bad">chybí ' + esc(missing.join(', ')) + '</span>';
    return '<span class="tag ok">' + esc((c.present || c.stored || []).length || 'ok') + ' uloženo</span>' + (c.secret_ref ? ' <span class="mono muted">' + esc(c.secret_ref) + '</span>' : '');
  }

  function renderInstances() {
    var tb = $('instances').querySelector('tbody');
    if (!state.instances.length) { tb.innerHTML = '<tr><td colspan="8" class="muted">Zatím žádná instance. Přidejte první v formuláři níže.</td></tr>'; return; }
    tb.innerHTML = state.instances.map(function (i) {
      return '<tr data-key="' + esc(i.key) + '">'
        + '<td class="mono">' + esc(i.key) + '</td><td>' + esc(i.provider) + (i.vendor_version ? ' <span class="muted">' + esc(i.vendor_version) + '</span>' : '') + '</td>'
        + '<td>' + esc(i.name) + '<br><span class="muted">' + esc(i.region || '—') + '</span></td>'
        + '<td class="mono">' + esc(i.base_url || '') + '</td><td>' + stateTag(i.state) + '</td><td>' + healthTag(i) + '</td><td>' + credTag(i) + '</td>'
        + '<td class="actions">'
        + '<button class="btn" data-act="probe">Probe</button>'
        + '<button class="btn" data-act="prerequisites" title="API, verze PHP, cron API, fronta úloh, mod_proxy">Prerekvizity</button>'
        + '<button class="btn" data-act="discover">Discover</button>'
        + '<button class="btn" data-act="detail">Uzly</button>'
        + '<button class="btn" data-act="edit">Upravit</button>'
        + '<select data-act="state" class="btn"><option value="">stav…</option><option value="active">active</option><option value="maintenance">maintenance</option><option value="draining">draining</option><option value="disabled">disabled</option></select>'
        + '</td></tr>';
    }).join('');
  }

  function loadInstances() {
    return api('GET', '/staff/integrations').then(function (r) {
      state.instances = (r.data || []).map(function (i) { return i; });
      // the list has no base_url; fetch details lazily for the table (few instances)
      return Promise.all(state.instances.map(function (i) { return api('GET', '/staff/integrations/' + encodeURIComponent(i.key)).then(function (d) { Object.assign(i, d.data || d); }).catch(function () {}); }));
    }).then(renderInstances);
  }

  function loadSchema() {
    return api('GET', '/staff/integrations/schema').then(function (r) {
      state.schema = r.data || r;
      var sel = $('f-provider'); sel.innerHTML = state.schema.providers.map(function (p) { return '<option value="' + esc(p.provider) + '">' + esc(p.provider) + '</option>'; }).join('');
      var regions = state.schema.regions || [];
      var opts = regions.map(function (r) { return '<option value="' + esc(r.code) + '">' + esc(r.code) + ' · ' + esc(r.name || '') + '</option>'; }).join('');
      $('f-region').innerHTML = '<option value="">— bez regionu —</option>' + opts;
      $('n-region').innerHTML = '<option value="">— region instance —</option>' + opts;
      renderProviderFields();
    });
  }

  function providerDef() { var p = $('f-provider').value; return (state.schema.providers || []).filter(function (x) { return x.provider === p; })[0] || null; }
  function renderProviderFields(current) {
    var p = providerDef(); if (!p) return;
    $('provider-hint').textContent = p.credentials.hint || '';
    var creds = (p.credentials.required || []).map(function (k) { return { k: k, req: true }; }).concat((p.credentials.optional || []).map(function (k) { return { k: k, req: false }; }));
    $('f-credentials').innerHTML = creds.map(function (c) {
      var secret = /secret|password|key|token/i.test(c.k);
      return '<div class="field"><label for="c-' + esc(c.k) + '">' + esc(c.k) + (c.req ? ' *' : '') + '</label><input id="c-' + esc(c.k) + '" data-cred="' + esc(c.k) + '" type="' + (secret ? 'password' : 'text') + '" autocomplete="new-password"' + (c.req && !state.editing ? ' required' : '') + '></div>';
    }).join('');
    var caps = current && current.capabilities ? current.capabilities : (p.capabilities || {});
    var all = Object.assign({}, p.capabilities || {}, caps);
    $('f-capabilities').innerHTML = Object.keys(all).map(function (k) { return '<label><input type="checkbox" data-cap="' + esc(k) + '"' + (all[k] ? ' checked' : '') + '> ' + esc(k) + '</label>'; }).join('');
  }
  $('f-provider').addEventListener('change', function () { renderProviderFields(); });

  function resetForm() {
    state.editing = null; $('form-title').textContent = 'Přidat instanci'; $('f-submit').textContent = 'Uložit instanci';
    $('instance-form').reset(); $('f-key').disabled = false; $('f-provider').disabled = false; $('f-verify').checked = true; $('f-ca').value = ''; $('f-options').value = '';
    renderProviderFields();
  }
  $('f-reset').addEventListener('click', resetForm);

  function editInstance(i) {
    state.editing = i; $('form-title').textContent = 'Upravit instanci ' + i.key; $('f-submit').textContent = 'Uložit změny';
    $('f-provider').value = i.provider; $('f-provider').disabled = true; renderProviderFields(i);
    $('f-key').value = i.key; $('f-key').disabled = true; $('f-name').value = i.name || ''; $('f-region').value = i.region || ''; $('f-base').value = i.base_url || ''; $('f-state').value = i.state || 'active';
    var o = Object.assign({}, i.options || {}); $('f-verify').checked = o.verify_tls !== false; $('f-ca').value = o.tls_ca || ''; delete o.verify_tls; delete o.tls_ca;
    $('f-options').value = Object.keys(o).length ? JSON.stringify(o, null, 2) : '';
    window.scrollTo({ top: $('instance-form').getBoundingClientRect().top + window.scrollY - 80, behavior: 'smooth' });
  }

  $('instance-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var options = {};
    try { options = $('f-options').value.trim() ? JSON.parse($('f-options').value) : {}; } catch (e) { say('Další volby nejsou platný JSON.', false); return; }
    if (typeof options !== 'object' || Array.isArray(options)) { say('Další volby musí být JSON objekt.', false); return; }
    options.verify_tls = $('f-verify').checked;
    if ($('f-ca').value.trim()) options.tls_ca = $('f-ca').value.trim(); else delete options.tls_ca;
    var caps = {}; Array.prototype.forEach.call(document.querySelectorAll('#f-capabilities input[data-cap]'), function (c) { caps[c.getAttribute('data-cap')] = c.checked; });
    var creds = {}; Array.prototype.forEach.call(document.querySelectorAll('#f-credentials input[data-cred]'), function (c) { if (c.value !== '') creds[c.getAttribute('data-cred')] = c.value; });
    var body = { key: $('f-key').value.trim(), provider: $('f-provider').value, name: $('f-name').value.trim() || undefined, region_code: $('f-region').value || null, base_url: $('f-base').value.trim(), state: $('f-state').value, options: options, capabilities: caps, credentials: creds };
    var editing = !!state.editing;
    // two refusals are questions, not dead ends: a panel address on another host (H311) and an access the panel did not confirm (H314)
    var save = function () {
      return guarded(function () { return api(editing ? 'PUT' : 'POST', editing ? '/staff/integrations/' + encodeURIComponent(body.key) : '/staff/integrations', body); }).catch(function (e) {
        if (e.error === 'instance_host_change_unconfirmed' && !body.confirm_host_change && window.confirm('Adresa panelu míří na jiný server. Uložené přístupy by se příště posílaly tam.\n\nPotvrdit změnu? Instance zůstane zamčená, dokud Probe nepotvrdí, že panel na nové adrese odpovídá.')) { body.confirm_host_change = true; return save(); }
        if (e.error === 'instance_credentials_unverified' && !body.force_credentials && window.confirm('Panel nový přístup nepřijal, takže zůstal platný ten uložený:\n' + e.message + '\n\nUložit nový přístup i tak? Jen pokud je uložený přístup kompromitovaný nebo je panel nedostupný — instance s ním přestane fungovat, dokud přístup neopravíte.')) { body.force_credentials = true; return save(); }
        throw e;
      });
    };
    save()
      .then(function (r) {
        Array.prototype.forEach.call(document.querySelectorAll('#f-credentials input[data-cred]'), function (c) { c.value = ''; });
        say('Instance ' + body.key + ' uložena. Spusťte Probe pro ověření spojení.', true); log('Uloženo', r);
        return loadInstances();
      })
      .catch(function (e) { say(e.message + (e.errors ? ' (' + Object.keys(e.errors).join(', ') + ')' : ''), false); log('Chyba', e.payload || e.message); });
  });

  function renderDetail(d) {
    var box = $('detail'); box.hidden = false; state.detailKey = d.key;
    var nodes = d.nodes || [];
    box.innerHTML = '<h2>Uzly · ' + esc(d.key) + '</h2>'
      + (nodes.length ? '<table><thead><tr><th>Uzel</th><th>Role</th><th>Region</th><th>Stav</th><th>Kapacita</th><th>Využití</th><th>Vzdálené ID</th><th>Naposledy</th></tr></thead><tbody>'
        + nodes.map(function (n) { return '<tr><td class="mono">' + esc(n.name) + '</td><td>' + esc(n.role) + '</td><td>' + esc(n.region) + '</td><td>' + stateTag(n.state) + '</td><td class="mono">' + esc(JSON.stringify(n.capacity || {})) + '</td><td class="mono">' + esc(JSON.stringify(n.usage || {})) + '</td><td class="mono">' + esc(n.remote_id || '') + '</td><td class="muted">' + esc((n.last_seen_at || '').replace('T', ' ').slice(0, 16)) + '</td></tr>'; }).join('')
        + '</tbody></table>' : '<p class="muted">Instance zatím nemá uzly. Proxmox a ISPConfig je importují přes Discover, ostatní registrujte ručně.</p>')
      + '<div class="actions" style="margin-top:10px"><button class="btn" id="node-add">Přidat uzel ručně</button>'
      + '<details style="margin-left:auto"><summary>Volby a stav instance</summary><pre class="mono" style="white-space:pre-wrap">' + esc(JSON.stringify({ options: d.options, capabilities: d.capabilities, credentials: d.credentials, health: d.health, health_checked_at: d.health_checked_at }, null, 2)) + '</pre></details></div>';
    $('node-add').addEventListener('click', function () { $('n-instance').textContent = d.key; $('n-region').value = ''; $('n-error').textContent = ''; $('node-dialog').showModal(); });
  }
  function loadDetail(k) { return api('GET', '/staff/integrations/' + encodeURIComponent(k)).then(function (r) { renderDetail(r.data || r); }); }

  $('node-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var capacity = {}, tags = {};
    try { capacity = $('n-capacity').value.trim() ? JSON.parse($('n-capacity').value) : {}; tags = $('n-tags').value.trim() ? JSON.parse($('n-tags').value) : {}; } catch (e) { $('n-error').textContent = 'Kapacita a tagy musí být platný JSON.'; return; }
    var k = state.detailKey;
    var body = { name: $('n-name').value.trim(), role: $('n-role').value, region_code: $('n-region').value || null, state: $('n-state').value, capacity: capacity, tags: tags };
    guarded(function () { return api('POST', '/staff/integrations/' + encodeURIComponent(k) + '/nodes', body); })
      .then(function (r) { $('node-dialog').close(); say('Uzel ' + body.name + ' uložen.', true); log('Uzel', r); return loadDetail(k); })
      .catch(function (e) { $('n-error').textContent = e.message; });
  });
  $('n-cancel').addEventListener('click', function () { $('node-dialog').close(); });

  $('instances').addEventListener('click', function (ev) {
    var b = ev.target.closest('button[data-act]'); if (!b) return;
    var k = b.closest('tr').getAttribute('data-key'); var i = state.instances.filter(function (x) { return x.key === k; })[0];
    var act = b.getAttribute('data-act');
    if (act === 'edit') { editInstance(i); return; }
    if (act === 'detail') { loadDetail(k).catch(function (e) { say(e.message, false); }); return; }
    b.disabled = true;
    var path = '/staff/integrations/' + encodeURIComponent(k) + '/' + act;
    if (act === 'prerequisites') {
      guarded(function () { return api('POST', path, {}); })
        .then(function (r) { var d = r.data || r; log('Prerekvizity ' + k, d); say('Instance ' + k + ': API ' + d.api + (d.php_versions && d.php_versions.length ? ' · PHP ' + d.php_versions.join(', ') : '') + ' · cron API ' + d.cron_api + ' · mod_proxy ' + d.mod_proxy + (d.warnings && d.warnings.length ? ' · ' + d.warnings.length + ' upozornění (viz log)' : ' · bez upozornění'), !(d.warnings && d.warnings.length)); })
        .catch(function (e) { say(e.message, false); log('Chyba', e.payload || e.message); })
        .then(function () { b.disabled = false; });
      return;
    }
    guarded(function () { return api('POST', path, {}); })
      .then(function (r) { var d = r.data || r; log((act === 'probe' ? 'Probe ' : 'Discover ') + k, d); say(act === 'probe' ? (d.up ? 'Instance ' + k + ' odpovídá (' + (d.latency_ms || '?') + ' ms' + (d.version ? ', verze ' + d.version : '') + ').' : 'Instance ' + k + ' neodpovídá: ' + (d.error || '')) : 'Discover ' + k + ': ' + ((d.nodes || []).length) + ' uzlů.', act === 'probe' ? !!d.up : true); return loadInstances().then(function () { if (act === 'discover') return loadDetail(k); }); })
      .catch(function (e) { say(e.message, false); log('Chyba', e.payload || e.message); })
      .then(function () { b.disabled = false; });
  });
  $('instances').addEventListener('change', function (ev) {
    var s = ev.target; if (!s.matches('select[data-act=state]') || !s.value) return;
    var k = s.closest('tr').getAttribute('data-key'); var v = s.value;
    var reason = window.prompt('Důvod změny stavu instance ' + k + ' na ' + v + ':', '') ; if (reason === null) { s.value = ''; return; }
    guarded(function () { return api('POST', '/staff/integrations/' + encodeURIComponent(k) + '/state', { state: v, reason: reason || undefined }); })
      .then(function (r) { say('Stav instance ' + k + ': ' + v, true); log('Stav', r); return loadInstances(); })
      .catch(function (e) { say(e.message, false); log('Chyba', e.payload || e.message); s.value = ''; });
  });

  /* ── plan placements ─────────────────────────────────────────────────── */
  var placements = { data: null };
  function renderPlacements() {
    var d = placements.data, tb = $('placements').querySelector('tbody');
    if (!d) return;
    tb.innerHTML = d.placements.length ? d.placements.map(function (p) {
      return '<tr data-id="' + esc(p.id) + '"><td class="mono">' + esc(p.product_key) + '</td><td class="mono">' + esc(p.plan_key || '— celý produkt —') + '</td><td>' + esc(p.region_code || '—') + '</td><td class="mono">' + esc(p.provider_instance_key || '?') + ' <span class="muted">' + esc(p.provider || '') + '</span></td><td class="mono">' + esc(p.node_name || '— libovolný —') + '</td><td>' + stateTag(p.state) + '</td><td class="actions"><button class="btn" data-pact="delete">Odebrat</button></td></tr>';
    }).join('') : '<tr><td colspan="7" class="muted">Žádné umístění — plánovač vybírá uzly podle role a regionu.</td></tr>';
    var prod = $('p-product');
    if (!prod.options.length) {
      prod.innerHTML = d.products.map(function (p) { return '<option value="' + esc(p.key) + '">' + esc(p.key) + ' · ' + esc(p.name) + ' (' + esc(p.executor) + ')</option>'; }).join('');
      $('p-region').innerHTML = '<option value="">— všechny regiony —</option>' + ((state.schema && state.schema.regions) || []).map(function (r) { return '<option value="' + esc(r.code) + '">' + esc(r.code) + '</option>'; }).join('');
      renderPlacementChoices();
    }
  }
  function renderPlacementChoices() {
    var d = placements.data; if (!d) return;
    var p = d.products.filter(function (x) { return x.key === $('p-product').value; })[0] || d.products[0];
    $('p-plan').innerHTML = '<option value="">— celý produkt —</option>' + (p ? p.plans.map(function (pl) { return '<option value="' + esc(pl.key) + '">' + esc(pl.key) + ' · ' + esc(pl.name) + '</option>'; }).join('') : '');
    var ok = d.instances.filter(function (i) { return p && p.compatible.indexOf(i.provider) >= 0; });
    $('p-instance').innerHTML = ok.map(function (i) { return '<option value="' + esc(i.key) + '">' + esc(i.key) + ' · ' + esc(i.provider) + (i.usable ? '' : ' (nepoužitelná)') + '</option>'; }).join('');
    $('p-compatible').textContent = p ? 'kompatibilní providery: ' + p.compatible.join(', ') : '';
    renderNodeChoices();
  }
  function renderNodeChoices() {
    var d = placements.data; if (!d) return;
    var i = d.instances.filter(function (x) { return x.key === $('p-instance').value; })[0];
    $('p-node').innerHTML = '<option value="">— libovolný uzel instance —</option>' + (i ? i.nodes.map(function (n) { return '<option value="' + esc(n.id) + '">' + esc(n.name) + ' · ' + esc(n.role) + ' · ' + esc(n.state) + '</option>'; }).join('') : '');
  }
  function loadPlacements() { return api('GET', '/staff/placements').then(function (r) { placements.data = r.data || r; renderPlacements(); }); }
  $('p-product').addEventListener('change', renderPlacementChoices);
  $('p-instance').addEventListener('change', renderNodeChoices);
  $('placement-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var body = { product_key: $('p-product').value, plan_key: $('p-plan').value || null, region_code: $('p-region').value || null, provider_instance_key: $('p-instance').value, node_id: $('p-node').value || null, note: $('p-note').value.trim() || null };
    guarded(function () { return api('PUT', '/staff/placements', body); })
      .then(function (r) { say('Umístění uloženo: ' + body.product_key + (body.plan_key ? ' / ' + body.plan_key : '') + ' → ' + body.provider_instance_key, true); log('Umístění', r); return loadPlacements(); })
      .catch(function (e) { say(e.message, false); log('Chyba', e.payload || e.message); });
  });
  $('placements').addEventListener('click', function (ev) {
    var b = ev.target.closest('button[data-pact=delete]'); if (!b) return;
    var id = b.closest('tr').getAttribute('data-id');
    if (!window.confirm('Odebrat umístění? Nové služby tohoto tarifu bude plánovat scheduler podle role a regionu.')) return;
    guarded(function () { return api('DELETE', '/staff/placements/' + encodeURIComponent(id)); })
      .then(function () { say('Umístění odebráno.', true); return loadPlacements(); })
      .catch(function (e) { say(e.message, false); });
  });

  /* ── registrar price book ────────────────────────────────────────────── */
  var registrars = { data: null };
  function czk(minor) { return minor == null ? '—' : (minor / 100).toLocaleString('cs-CZ', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + ' Kč'; }
  function money(minor, cur) { return minor == null ? '—' : (minor / 100).toLocaleString('cs-CZ', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + ' ' + cur; }
  function renderRegistrars() {
    var d = registrars.data, tb = $('registrars').querySelector('tbody');
    if (!d) return;
    var provs = d.providers || [];
    $('r-heads').textContent = 'Nákupní ceny (' + (provs.length ? provs.join(' · ') : 'žádný registrátor') + ')';
    $('r-meta').textContent = d.registrars.length ? d.registrars.map(function (r) { return r.key + (r.has_price_api ? ' (ceník z API)' : ' (ruční ceník)') + (r.credit != null ? ' · kredit ' + r.credit + ' ' + (r.currency || '') : ''); }).join(' · ') : 'Žádná instance registrátora není aktivní.';
    tb.innerHTML = d.tlds.length ? d.tlds.map(function (t) {
      var sell = t.selling.CZK ? czk(t.selling.CZK.register) + ' / ' + czk(t.selling.CZK.renew) : '—';
      var costs = provs.map(function (p) {
        var c = t.costs[p];
        if (!c) return '<span class="muted">' + esc(p) + ': —</span>';
        return '<span class="' + (t.winner === p ? 'ok' : '') + '">' + esc(p) + ': ' + esc(money(c.register, c.currency)) + (c.currency !== 'CZK' ? ' (' + esc(czk(c.register_czk)) + ')' : '') + (c.stale ? ' ⚠' : '') + ' <small class="muted">' + esc(c.source) + '</small></span>';
      }).join('<br>');
      var pin = '<select data-tld="' + esc(t.tld) + '" class="pin"><option value="auto"' + (t.pinned === 'auto' ? ' selected' : '') + '>auto (nejlevnější)</option>' + provs.map(function (p) { return '<option value="' + esc(p) + '"' + (t.pinned === p ? ' selected' : '') + '>' + esc(p) + '</option>'; }).join('') + '</select>';
      return '<tr><td class="mono">.' + esc(t.tld) + '</td><td>' + sell + '</td><td>' + costs + '</td><td class="mono">' + esc(t.winner || '—') + ' <small class="muted">' + esc(t.reason || '') + '</small></td><td>' + esc(czk(t.margin_czk)) + '</td><td>' + pin + '</td></tr>';
    }).join('') : '<tr><td colspan="6" class="muted">Ceník TLD je prázdný.</td></tr>';
    var sel = $('c-registrar');
    if (!sel.options.length) sel.innerHTML = provs.map(function (p) { return '<option value="' + esc(p) + '">' + esc(p) + '</option>'; }).join('');
  }
  function renderConnections(rows) {
    var tb = $('connections-table').querySelector('tbody');
    if (!rows.length) { tb.innerHTML = '<tr><td colspan="8" class="muted">Zatím žádné připojení.</td></tr>'; return; }
    tb.innerHTML = rows.map(function (c) {
      var st = c.state === 'active' ? 'ok' : c.state === 'error' ? 'warn' : 'off';
      var actions = c.state === 'disabled' ? (c.staff_disabled ? '<button class="btn" data-conn="' + esc(c.id) + '" data-op="enable">Obnovit</button>' : '<span class="hint">odpojeno zákazníkem</span>') : '<button class="btn" data-conn="' + esc(c.id) + '" data-op="sync">Synchronizovat</button> <button class="btn" data-conn="' + esc(c.id) + '" data-op="disable">Pozastavit</button>';
      return '<tr><td>' + esc((c.organization && c.organization.name) || '') + '<div class="hint mono">' + esc(c.organization ? c.organization.id : '') + '</div></td><td>' + esc(c.label) + '<div class="hint">' + esc(c.login) + '</div></td><td><span class="tag ' + st + '">' + esc(c.state) + '</span></td><td>' + esc(c.domains == null ? '—' : c.domains) + ' / ' + esc(c.zones == null ? '—' : c.zones) + '</td><td>' + esc(c.credit ? c.credit.balance + ' ' + c.credit.currency : '—') + '</td><td>' + esc(c.last_synced_at ? new Date(c.last_synced_at).toLocaleString('cs-CZ') : '—') + '</td><td class="hint">' + esc(c.last_error || '') + '</td><td class="actions">' + actions + '</td></tr>';
    }).join('');
  }
  function loadConnections() {
    return api('GET', '/staff/registrar-connections?limit=100').then(function (r) { renderConnections(r.data || []); })
      .catch(function (e) { $('connections-table').querySelector('tbody').innerHTML = '<tr><td colspan="8" class="muted">' + esc((e && e.message) || 'Nelze načíst') + '</td></tr>'; });
  }
  $('connections-table').addEventListener('click', function (ev) {
    var b = ev.target.closest('button[data-conn]');
    if (!b) return;
    var id = b.getAttribute('data-conn'), op = b.getAttribute('data-op'), body = null;
    if (op === 'disable') { var reason = window.prompt('Důvod pozastavení (zákazník ho uvidí v historii):'); if (!reason) return; body = { reason: reason }; }
    api('POST', '/staff/registrar-connections/' + encodeURIComponent(id) + '/' + op, body)
      .then(function (r) { say('Připojení registrátora: ' + (op === 'sync' ? 'synchronizováno' : op === 'disable' ? 'pozastaveno' : 'obnoveno') + '.', true); log('Připojení registrátora', r); return loadConnections(); })
      .catch(function (e) { say('Chyba: ' + ((e && e.message) || e), false); log('Připojení registrátora', String(e)); });
  });
  loadConnections();

  function loadRegistrars() { return api('GET', '/staff/registrars').then(function (r) { registrars.data = r.data || r; renderRegistrars(); }); }
  $('r-refresh').addEventListener('click', function () {
    guarded(function () { return api('POST', '/staff/registrars/costs/refresh', {}); })
      .then(function (r) { var d = r.data || r; say('Ceníky aktualizovány: ' + Object.keys(d.registrars || {}).map(function (k) { return k + ' ' + d.registrars[k].tlds + ' TLD' + (d.registrars[k].error ? ' (' + d.registrars[k].error + ')' : ''); }).join(', '), true); log('Ceníky', r); return loadRegistrars(); })
      .catch(function (e) { say(e.message, false); log('Chyba', e.payload || e.message); });
  });
  $('r-scrape').addEventListener('click', function () {
    guarded(function () { return api('POST', '/staff/registrars/costs/scrape', {}); })
      .then(function (r) { var d = r.data || r; say('Veřejné ceníky načteny: ' + Object.keys(d.registrars || {}).map(function (k) { var x = d.registrars[k]; return k + ' ' + x.imported + ' TLD' + (x.skipped ? ' (' + x.skipped + ' ponecháno)' : '') + (x.error ? ' — ' + x.error : ''); }).join(', '), true); log('Veřejné ceníky', r); return loadRegistrars(); })
      .catch(function (e) { say(e.message, false); log('Chyba', e.payload || e.message); });
  });
  $('cost-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var body = { registrar_provider: $('c-registrar').value, tld: $('c-tld').value.trim().replace(/^\./, ''), currency: $('c-currency').value, register: $('c-register').value || null, renew: $('c-renew').value || null, transfer: $('c-transfer').value || null, note: $('c-note').value || null };
    guarded(function () { return api('PUT', '/staff/registrars/costs', body); })
      .then(function (r) { say('Nákupní cena uložena: .' + body.tld + ' u ' + body.registrar_provider, true); log('Nákupní cena', r); return loadRegistrars(); })
      .catch(function (e) { say(e.message, false); log('Chyba', e.payload || e.message); });
  });
  $('registrars').addEventListener('change', function (ev) {
    var s = ev.target.closest('select.pin'); if (!s) return;
    var tld = s.getAttribute('data-tld'), v = s.value;
    guarded(function () { return api('PUT', '/staff/registrars/policy', { tld: tld, registrar_provider: v }); })
      .then(function (r) { say('.' + tld + ' → ' + (v === 'auto' ? 'nejlevnější registrátor' : v), true); log('Připnutí TLD', r); return loadRegistrars(); })
      .catch(function (e) { say(e.message, false); log('Chyba', e.payload || e.message); });
  });

  /* ── pricing rules: commitment discounts, domain discounts, promo codes ─────────────────────────────── */
  var pricing = { data: null };
  function pct(v) { return (v == null || v === '') ? '—' : (Number(v) + ' %'); }
  function fdate(iso) { return iso ? new Date(iso).toLocaleDateString('cs-CZ') : ''; }
  function renderPricing() {
    var d = pricing.data; if (!d) return;
    var fams = (d.families || []).filter(function (f) { return f !== 'addon'; });
    var c = d.commit_discounts || { default: {}, families: {} };
    var row = function (name, key, vals) { return '<tr><td>' + esc(name) + '</td><td><input type="number" min="0" max="90" step="0.5" data-fam="' + esc(key) + '" data-m="12" value="' + esc(vals['12'] != null ? vals['12'] : '') + '" placeholder="0" style="width:90px"></td><td><input type="number" min="0" max="90" step="0.5" data-fam="' + esc(key) + '" data-m="24" value="' + esc(vals['24'] != null ? vals['24'] : '') + '" placeholder="0" style="width:90px"></td></tr>'; };
    $('commit-table').querySelector('tbody').innerHTML = row('výchozí (ostatní rodiny)', '*', c.default || {}) + fams.map(function (f) { return row(f, f, (c.families || {})[f] || {}); }).join('');
    var dd = d.domain_discounts || {}, keys = Object.keys(dd);
    $('domain-discounts').querySelector('tbody').innerHTML = keys.length ? keys.map(function (t) { var r = dd[t]; return '<tr><td class="mono">.' + esc(t) + '</td><td>' + pct(r.register) + '</td><td>' + pct(r.renew) + '</td><td>' + pct(r.transfer) + '</td><td>' + esc((fdate(r.valid_from) || '…') + ' – ' + (fdate(r.valid_to) || '…')) + '</td><td>' + esc(r.label || '') + '</td><td><button class="btn" type="button" data-dd-del="' + esc(t) + '">Zrušit</button></td></tr>'; }).join('') : '<tr><td colspan="7" class="muted">Žádná sleva — domény se prodávají za ceníkovou cenu.</td></tr>';
    var sel = $('dd-tld'); if (!sel.options.length) sel.innerHTML = (d.tlds || []).map(function (t) { return '<option value="' + esc(t) + '">.' + esc(t) + '</option>'; }).join('');
    var promos = d.promo_codes || [];
    $('promos').querySelector('tbody').innerHTML = promos.length ? promos.map(function (p) { return '<tr><td class="mono">' + esc(p.code) + '</td><td>' + esc(p.kind === 'fixed' ? p.value + ' ' + (p.currency || '') : p.value + ' %') + '</td><td>' + esc((p.applies_to || []).join(', ') || 'vše') + '</td><td>' + esc((fdate(p.valid_from) || '…') + ' – ' + (fdate(p.valid_to) || '…')) + '</td><td>' + esc(p.uses + (p.max_uses ? ' / ' + p.max_uses : '')) + '</td><td class="' + (p.usable ? 'ok' : 'muted') + '">' + esc(p.state) + (p.usable ? '' : ' (neplatný)') + '</td><td><button class="btn" type="button" data-promo-del="' + esc(p.code) + '">Smazat</button></td></tr>'; }).join('') : '<tr><td colspan="7" class="muted">Žádný promo kód.</td></tr>';
    if (!$('pc-families').children.length) $('pc-families').innerHTML = fams.concat(['domain']).map(function (f) { return '<label><input type="checkbox" value="' + esc(f) + '" data-pc-fam> ' + esc(f) + '</label>'; }).join('');
  }
  function loadPricing() { return api('GET', '/staff/pricing').then(function (r) { pricing.data = r.data || r; renderPricing(); renderAddons(); }); }
  $('commit-save').addEventListener('click', function () {
    var body = { default: {}, families: {} };
    $('commit-table').querySelectorAll('input[data-fam]').forEach(function (i) { var v = i.value === '' ? 0 : Number(i.value); if (i.getAttribute('data-fam') === '*') body.default[i.getAttribute('data-m')] = v; else { body.families[i.getAttribute('data-fam')] = body.families[i.getAttribute('data-fam')] || {}; body.families[i.getAttribute('data-fam')][i.getAttribute('data-m')] = v; } });
    guarded(function () { return api('PUT', '/staff/pricing/commit-discounts', body); })
      .then(function (r) { say('Závazkové slevy uloženy', true); log('Závazkové slevy', r); return loadPricing(); })
      .catch(function (e) { say(e.message, false); log('Chyba', e.payload || e.message); });
  });
  $('dd-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var body = { tld: $('dd-tld').value, register: $('dd-register').value || 0, renew: $('dd-renew').value || 0, transfer: $('dd-transfer').value || 0, valid_from: $('dd-from').value || null, valid_to: $('dd-to').value || null, label: $('dd-label').value.trim() || null };
    guarded(function () { return api('PUT', '/staff/pricing/domain-discounts', body); })
      .then(function (r) { say('Sleva na .' + body.tld + ' uložena', true); log('Sleva na doménu', r); return loadPricing(); })
      .catch(function (e) { say(e.message, false); log('Chyba', e.payload || e.message); });
  });
  $('domain-discounts').addEventListener('click', function (ev) {
    var b = ev.target.closest('button[data-dd-del]'); if (!b) return;
    var tld = b.getAttribute('data-dd-del');
    guarded(function () { return api('DELETE', '/staff/pricing/domain-discounts/' + encodeURIComponent(tld)); })
      .then(function () { say('Sleva na .' + tld + ' zrušena', true); return loadPricing(); })
      .catch(function (e) { say(e.message, false); log('Chyba', e.payload || e.message); });
  });
  $('promo-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var fams = Array.prototype.filter.call($('pc-families').querySelectorAll('input[data-pc-fam]'), function (i) { return i.checked; }).map(function (i) { return i.value; });
    var body = { code: $('pc-code').value.trim(), kind: $('pc-kind').value, value: $('pc-value').value, currency: $('pc-currency').value, valid_from: $('pc-from').value || null, valid_to: $('pc-to').value || null, max_uses: $('pc-max').value || null, applies_to: fams, first_period_only: $('pc-first').checked, state: $('pc-state').value };
    guarded(function () { return api('PUT', '/staff/pricing/promo-codes', body); })
      .then(function (r) { say('Promo kód ' + body.code.toUpperCase() + ' uložen', true); log('Promo kód', r); return loadPricing(); })
      .catch(function (e) { say(e.message, false); log('Chyba', e.payload || e.message); });
  });
  $('promos').addEventListener('click', function (ev) {
    var b = ev.target.closest('button[data-promo-del]'); if (!b) return;
    var code = b.getAttribute('data-promo-del');
    if (!window.confirm('Smazat promo kód ' + code + '?')) return;
    guarded(function () { return api('DELETE', '/staff/pricing/promo-codes/' + encodeURIComponent(code)); })
      .then(function () { say('Promo kód ' + code + ' smazán', true); return loadPricing(); })
      .catch(function (e) { say(e.message, false); log('Chyba', e.payload || e.message); });
  });

  /* ── add-on products and priced options per product ──────────────────────────────────────────────── */
  function currentProduct() { var d = pricing.data; if (!d) return null; var key = $('ao-product').value; return (d.products || []).filter(function (p) { return p.key === key; })[0] || null; }
  function renderAddons() {
    var d = pricing.data; if (!d) return;
    var sel = $('ao-product');
    if (!sel.options.length) { sel.innerHTML = (d.products || []).filter(function (p) { return p.family !== 'addon'; }).map(function (p) { return '<option value="' + esc(p.key) + '">' + esc(p.name) + ' (' + esc(p.key) + (p.builder ? ', konfigurátor' : '') + ')</option>'; }).join(''); }
    var p = currentProduct(); if (!p) return;
    $('ao-products').innerHTML = (d.addon_candidates || []).map(function (c) { return '<label><input type="checkbox" value="' + esc(c.key) + '" data-ao-prod' + ((p.addon_products || []).indexOf(c.key) >= 0 ? ' checked' : '') + '> ' + esc(c.name) + ' <span class="muted mono">' + esc(c.key) + '</span></label>'; }).join('') || '<span class="muted">Žádné doplňkové produkty v katalogu.</span>';
    var opts = p.options || [];
    $('ao-options').querySelector('tbody').innerHTML = opts.length ? opts.map(function (o) {
      var range = o.kind === 'slider' ? (o.min != null ? o.min : 0) + ' – ' + (o.max != null ? o.max : '∞') + ' ' + (o.unit || '') + ' (krok ' + (o.step || 1) + ', v ceně ' + (o['default'] != null ? o['default'] : 0) + ')' : (o.kind === 'select' ? (o.choices || []).map(function (c) { return (typeof c.label === 'object' ? (c.label.cs || c.key) : (c.label || c.key)) + ' ×' + c.units; }).join(', ') : 'ano / ne');
      var ent = o.entitlement ? (o.entitlement.key || '') + (o.entitlement.mode ? ' (' + o.entitlement.mode + ')' : '') + (o.entitlement.value != null ? ' = ' + JSON.stringify(o.entitlement.value) : '') + (o.entitlement.values ? ' (mapa)' : '') : '—';
      return '<tr><td class="mono">' + esc(o.key) + '</td><td>' + esc(o.kind) + '</td><td>' + esc((o.label && o.label.cs) || o.key) + '<br><small class="muted">' + esc((o.desc && o.desc.cs) || '') + '</small></td><td>' + esc(range) + '</td><td>' + esc(o.price_czk + ' Kč / ' + o.price_eur + ' €') + '</td><td class="mono">' + esc(ent) + '</td><td><button class="btn" type="button" data-op-edit="' + esc(o.key) + '">Upravit</button> <button class="btn" type="button" data-op-del="' + esc(o.key) + '">Smazat</button></td></tr>';
    }).join('') : '<tr><td colspan="7" class="muted">Produkt zatím nemá volitelné parametry — přidejte první níže.</td></tr>';
  }
  $('ao-product').addEventListener('change', renderAddons);
  $('ao-products-save').addEventListener('click', function () {
    var p = currentProduct(); if (!p) return;
    var keys = Array.prototype.filter.call($('ao-products').querySelectorAll('input[data-ao-prod]'), function (i) { return i.checked; }).map(function (i) { return i.value; });
    guarded(function () { return api('PUT', '/staff/pricing/addon-products', { product_key: p.key, addon_products: keys }); })
      .then(function (r) { say('Doplňkové produkty pro ' + p.key + ' uloženy', true); log('Doplňkové produkty', r); return loadPricing(); })
      .catch(function (e) { say(e.message, false); log('Chyba', e.payload || e.message); });
  });
  $('ao-options').addEventListener('click', function (ev) {
    var p = currentProduct(); if (!p) return;
    var del = ev.target.closest('button[data-op-del]'), edit = ev.target.closest('button[data-op-edit]');
    if (del) {
      var key = del.getAttribute('data-op-del');
      if (!window.confirm('Smazat parametr ' + key + ' produktu ' + p.key + '?')) return;
      guarded(function () { return api('DELETE', '/staff/pricing/options/' + encodeURIComponent(p.key) + '/' + encodeURIComponent(key)); })
        .then(function () { say('Parametr ' + key + ' smazán', true); return loadPricing(); })
        .catch(function (e) { say(e.message, false); log('Chyba', e.payload || e.message); });
    }
    if (edit) {
      var o = (p.options || []).filter(function (x) { return x.key === edit.getAttribute('data-op-edit'); })[0]; if (!o) return;
      $('op-key').value = o.key; $('op-kind').value = o.kind; $('op-label-cs').value = (o.label && o.label.cs) || ''; $('op-label-en').value = (o.label && o.label.en) || ''; $('op-desc').value = (o.desc && o.desc.cs) || '';
      $('op-unit').value = o.unit || ''; $('op-price').value = o.price_czk; $('op-price-eur').value = o.price_eur; $('op-min').value = o.min != null ? o.min : ''; $('op-max').value = o.max != null ? o.max : ''; $('op-step').value = o.step != null ? o.step : ''; $('op-default').value = o['default'] != null ? o['default'] : '';
      $('op-choices').value = (o.choices || []).map(function (c) { return c.key + ' | ' + (typeof c.label === 'object' ? (c.label.cs || c.key) : (c.label || c.key)) + ' | ' + (c.units || 0); }).join('\n');
      $('op-ent-key').value = (o.entitlement && o.entitlement.key) || ''; $('op-ent-mode').value = (o.entitlement && o.entitlement.mode) || 'extra';
      $('op-key').focus();
    }
  });
  $('ao-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var p = currentProduct(); if (!p) return;
    var choices = $('op-choices').value.split('\n').map(function (l) { return l.split('|').map(function (x) { return x.trim(); }); }).filter(function (c) { return c[0]; }).map(function (c) { return { key: c[0], label: c[1] || c[0], units: Number(c[2] || 0) }; });
    var entKey = $('op-ent-key').value.trim();
    var body = { product_key: p.key, key: $('op-key').value.trim(), kind: $('op-kind').value, label: { cs: $('op-label-cs').value.trim(), en: $('op-label-en').value.trim() || null }, desc: { cs: $('op-desc').value.trim() || null }, unit: $('op-unit').value.trim() || null,
      min: $('op-min').value || null, max: $('op-max').value || null, step: $('op-step').value || null, default: $('op-default').value || null, price_czk: $('op-price').value, price_eur: $('op-price-eur').value || null, choices: $('op-kind').value === 'select' ? choices : null,
      entitlement: entKey ? { key: entKey, mode: $('op-ent-mode').value } : null };
    if ($('op-kind').value === 'addon' && entKey) body.entitlement.value = true;
    guarded(function () { return api('PUT', '/staff/pricing/options', body); })
      .then(function (r) { say('Parametr ' + body.key + ' uložen', true); log('Parametr', r); return loadPricing(); })
      .catch(function (e) { say(e.message, false); log('Chyba', e.payload || e.message); });
  });

  /* ── Bankovní platby: čekající převody, ruční záznam řádku výpisu, Fio sync ───────────────────── */
  var bank = { data: null };
  function mny(a) { if (a == null) return '—'; if (typeof a === 'number') return a.toFixed(2); if (typeof a === 'string') return a; if (a.decimal != null) return String(a.decimal); if (a.amount != null) return String(a.amount); if (a.minor != null) return (a.minor / 100).toFixed(2); return JSON.stringify(a); }
  function when(s) { return s ? new Date(s).toLocaleString('cs-CZ') : '—'; }
  function renderBank() {
    var d = bank.data; if (!d) return;
    $('bank-fio').textContent = d.fio_configured ? 'Fio API token je nastaven, výpis se stahuje automaticky každých 5 minut.' : 'Fio API token (ONHOST_BANK_FIO_TOKEN) není nastaven — platby zaznamenávejte ručně.';
    $('bank-sync').disabled = !d.fio_configured;
    $('bank-pending').querySelector('tbody').innerHTML = d.pending.length ? d.pending.map(function (p) {
      return '<tr><td><code>' + esc(p.variable_symbol || '—') + '</code></td><td>' + esc(mny(p.amount)) + ' ' + esc(p.currency) + '</td><td>' + esc(p.purpose === 'topup' ? 'dobití kreditu' : 'objednávka') + (p.message ? ' · ' + esc(p.message) : '') + '</td><td>' + esc(p.organization || p.organization_id) + '</td><td>' + esc(when(p.created_at)) + '</td><td><button class="btn" type="button" data-fill="' + esc(p.variable_symbol || '') + '" data-amount="' + esc(mny(p.amount)) + '" data-currency="' + esc(p.currency) + '">Do formuláře</button></td></tr>';
    }).join('') : '<tr><td colspan="6" class="muted">Žádná platba převodem nečeká.</td></tr>';
    $('bank-lines').querySelector('tbody').innerHTML = d.lines.length ? d.lines.map(function (l) {
      return '<tr><td>' + esc(when(l.booked_at)) + '</td><td><code>' + esc(l.variable_symbol || '—') + '</code></td><td>' + esc(mny(l.amount)) + ' ' + esc(l.currency) + '</td><td>' + esc(l.counterparty || '—') + '</td><td>' + esc(l.message || '—') + '</td><td>' + (l.state === 'matched' ? '<strong style="color:#5a9b00">spárováno</strong>' : '<span class="muted">nespárováno</span>') + '</td></tr>';
    }).join('') : '<tr><td colspan="6" class="muted">Zatím žádný řádek výpisu.</td></tr>';
  }
  function loadBank() { return api('GET', '/staff/payments/bank').then(function (r) { bank.data = r.data || r; renderBank(); }); }
  $('bank-pending').addEventListener('click', function (ev) {
    var b = ev.target.closest('button[data-fill]'); if (!b) return;
    $('bk-vs').value = b.getAttribute('data-fill'); $('bk-amount').value = b.getAttribute('data-amount'); $('bk-currency').value = b.getAttribute('data-currency'); $('bk-amount').focus();
  });
  $('bank-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var body = { variable_symbol: $('bk-vs').value.trim(), amount: $('bk-amount').value, currency: $('bk-currency').value, booked_at: $('bk-date').value || null, external_id: $('bk-ext').value.trim() || null, counterparty: $('bk-counterparty').value.trim() || null, message: $('bk-msg').value.trim() || null };
    guarded(function () { return api('POST', '/staff/payments/bank/lines', body); })
      .then(function (r) {
        var d = r.data || r;
        var text = { matched: 'Platba spárována a zaúčtována', already_matched: 'Tento řádek už byl spárován dříve', duplicate: 'Řádek už byl zaznamenán', amount_mismatch: 'Částka nesouhlasí s očekávanou — založena položka k odsouhlasení', no_symbol: 'Bez variabilního symbolu nelze párovat', unmatched: 'K tomuto symbolu nečeká žádná platba' }[d.result] || d.result;
        say(text, d.result === 'matched'); log('Bankovní platba', d); $('bank-form').reset(); return loadBank();
      })
      .catch(function (e) { say(e.message, false); log('Chyba', e.payload || e.message); });
  });
  $('bank-sync').addEventListener('click', function () {
    guarded(function () { return api('POST', '/staff/payments/bank/sync', {}); })
      .then(function (r) { var d = r.data || r; say('Výpis stažen: ' + d.fetched + ' pohybů, ' + d.recorded + ' nových, ' + d.matched + ' spárováno', true); log('Fio sync', d); return loadBank(); })
      .catch(function (e) { say(e.message, false); log('Chyba', e.payload || e.message); });
  });

  /* --- customer panel sidebar: categories (order, switch, labels) and optional links --- */
  var navCfg = { categories: [], links: [] };
  function renderNav() {
    var tb = $('nav-cats').querySelector('tbody'); tb.innerHTML = '';
    navCfg.categories.forEach(function (c, i) {
      var tr = document.createElement('tr');
      tr.innerHTML = '<td><button type="button" class="btn" data-move="up" data-i="' + i + '"' + (i === 0 ? ' disabled' : '') + '>▲</button> <button type="button" class="btn" data-move="down" data-i="' + i + '"' + (i === navCfg.categories.length - 1 ? ' disabled' : '') + '>▼</button></td>'
        + '<td><input type="checkbox" data-enabled="' + i + '"' + (c.enabled ? ' checked' : '') + '></td>'
        + '<td class="mono">' + esc(c.key) + '</td>'
        + '<td><input data-label="cs" data-i="' + i + '" maxlength="40" value="' + esc(c.label.cs || '') + '" placeholder="' + esc(c.default_label.cs) + '"></td>'
        + '<td><input data-label="en" data-i="' + i + '" maxlength="40" value="' + esc(c.label.en || '') + '" placeholder="' + esc(c.default_label.en) + '"></td>'
        + '<td>' + (c.offered ? 'ano' : '<span class="muted">ne — v katalogu není aktivní produkt</span>') + '</td>'
        + '<td>' + esc(String(c.owners)) + (c.owners > 0 && !c.enabled ? ' <span class="muted">(těm zůstane viditelná)</span>' : '') + '</td>';
      tb.appendChild(tr);
    });
    var box = $('nav-links'); box.innerHTML = '';
    navCfg.links.forEach(function (l, i) {
      var lab = document.createElement('label'); lab.className = 'field';
      lab.innerHTML = '<span><input type="checkbox" data-link="' + i + '"' + (l.enabled ? ' checked' : '') + '> ' + esc(l.label.cs) + ' <span class="muted">(' + esc(l.label.en) + ')</span></span>';
      box.appendChild(lab);
    });
  }
  function readNavInputs() {
    navCfg.categories.forEach(function (c, i) {
      var en = document.querySelector('[data-enabled="' + i + '"]'); if (en) c.enabled = en.checked;
      ['cs', 'en'].forEach(function (l) { var inp = document.querySelector('[data-label="' + l + '"][data-i="' + i + '"]'); if (inp) c.label[l] = inp.value; });
    });
    navCfg.links.forEach(function (l, i) { var cb = document.querySelector('[data-link="' + i + '"]'); if (cb) l.enabled = cb.checked; });
  }
  function loadNav() { return api('GET', '/staff/settings/panel-nav').then(function (r) { var d = r.data || r; navCfg = { categories: d.categories || [], links: d.links || [] }; renderNav(); }); }
  $('nav-cats').addEventListener('click', function (ev) {
    var b = ev.target.closest('button[data-move]'); if (!b) return;
    readNavInputs();
    var i = parseInt(b.getAttribute('data-i'), 10), j = b.getAttribute('data-move') === 'up' ? i - 1 : i + 1;
    if (j < 0 || j >= navCfg.categories.length) return;
    var t = navCfg.categories[i]; navCfg.categories[i] = navCfg.categories[j]; navCfg.categories[j] = t;
    renderNav();
  });
  $('nav-save').addEventListener('click', function () {
    readNavInputs();
    var body = { categories: {}, links: {} };
    navCfg.categories.forEach(function (c, i) { body.categories[c.key] = { enabled: c.enabled, order: i, label: { cs: c.label.cs || '', en: c.label.en || '' } }; });
    navCfg.links.forEach(function (l) { body.links[l.key] = l.enabled; });
    guarded(function () { return api('PUT', '/staff/settings/panel-nav', body); })
      .then(function (r) { say('Navigace klientského panelu uložena', true); log('Navigace panelu', r.data || r); return loadNav(); })
      .catch(function (e) { say(e.message, false); log('Chyba', e.payload || e.message); });
  });

  loadSchema().then(loadInstances).then(loadPlacements).then(loadRegistrars).then(loadPricing).then(loadBank).then(loadNav).catch(function (e) { say('Nelze načíst nastavení: ' + e.message, false); });
})();
</script>
</body>
</html>
