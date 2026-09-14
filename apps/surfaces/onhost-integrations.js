/* onhost-integrations.js — kontrakt integrační vrstvy Onhostu.
 *
 * Pět cizích API, jedna fronta úloh, jeden log volání. Tenhle soubor je zároveň
 * datový zdroj plochy Onhost-integrace.dc.html a strojově čitelné zadání pro
 * Laravel backend: každá akce nese metodu, cestu, asynchronnost, jméno Laravel
 * jobu a potřebné oprávnění. Kdo mění endpointy, mění je tady — plocha ani
 * dokumentace si je nedrží podruhé.
 *
 * window.OnhostIntegrations = { providers, provider, actions, workflows, jobs,
 *   job, retryJob, cancelJob, mappings, calls, health, budgets, env, kpis, on }
 *
 * Nic nefetchuje. Data jsou seedovaná deterministickým LCG, aby se plocha
 * vykreslila stejně při každém načtení a šla srovnat mezi rolemi.
 */
(function () {
  'use strict';
  if (window.OnhostIntegrations) return;

  /* ---------- deterministický generátor ---------- */
  var seed = 20260905;
  function rnd() { seed = (seed * 1103515245 + 12345) & 0x7fffffff; return seed / 0x7fffffff; }
  function pick(a) { return a[Math.floor(rnd() * a.length) % a.length]; }
  function int(a, b) { return a + Math.floor(rnd() * (b - a + 1)); }
  function pad(n) { return n < 10 ? '0' + n : '' + n; }
  function clock(minsAgo) {
    var d = new Date(Date.now() - minsAgo * 60000);
    return pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
  }
  function ago(mins) {
    if (mins < 1) return 'teď';
    if (mins < 60) return mins + ' min';
    if (mins < 1440) return Math.round(mins / 60) + ' h';
    return Math.round(mins / 1440) + ' d';
  }

  /* ==========================================================================
   * Konektory
   * Cesty jsou reálné cesty produkčních API. `async: true` znamená, že volání
   * vrací handle úlohy (UPID, datalog id, externí proces u registrátora) a
   * hotovo je až po dotažení stavu — ne po HTTP 200.
   * ====================================================================== */
  var PROVIDERS = [
    {
      id: 'wedos', name: 'WEDOS WAPI', short: 'WEDOS',
      role: 'Registrátor domén, autoritativní DNS, kredit účtu',
      base: 'https://api.wedos.com/wapi/json',
      transport: 'POST application/x-www-form-urlencoded, tělo je pole request={"request":{…}}',
      auth: 'auth = sha1(login + sha1(wapi_heslo) + HH) — HH je aktuální hodina v Europe/Prague',
      authNote: 'WAPI heslo je jiné než heslo do účtu. Bez zapsané IP v whitelistu WAPI neodpovídá — v Laravelu proto volat vždy z workeru s pevnou výstupní IP, nikdy z webového dyna.',
      rate: { budget: 100, window: 'hodina (doménové dotazy)', note: 'domain-create a domain-transfer mají strop 100 doménových dotazů za hodinu; DNS příkazy jdou mimo tento limit, ale držíme si i tam vlastní strop.' },
      push: false,
      poll: 'domény 1×/h, kredit a expirace 1×/den',
      client: 'App\\\\Integrations\\\\Wedos\\\\WedosConnector',
      env: ['WEDOS_USER', 'WEDOS_WAPI_PASSWORD', 'WEDOS_ENDPOINT', 'WEDOS_TEST_MODE', 'WEDOS_EGRESS_IP'],
      caps: ['Registrace a prodloužení domény', 'Transfer a autorizační kód', 'DNS zóny a záznamy', 'NSSET a kontakty', 'Zůstatek kreditu'],
      quirks: [
        'Každá odpověď má vlastní kód v těle (1000 = OK), HTTP status je skoro vždy 200 — chybu poznáte jen z těla.',
        'Zápis do DNS je dvoufázový: dns-row-add / -update / -delete jen připraví změnu, publikuje ji až dns-domain-commit. Bez commitu je zóna beze změny a UI nesmí tvrdit „hotovo“.',
        'Test mód (příznak test: 1 v requestu) nevytváří skutečné objednávky — použít na CI a při ladění.',
        'Transfer .cz a .eu je synchronní (výsledek hned), ostatní TLD asynchronní přes notifikaci — job musí umět obojí.'
      ],
      actions: [
        { id: 'ping', label: 'Ping / kontrola spojení', m: 'POST', p: 'command=ping', async: false, job: 'Wedos\\CheckConnection', act: null },
        { id: 'domain-check', label: 'Dostupnost domény', m: 'POST', p: 'command=domain-check', async: false, job: 'Wedos\\CheckDomainAvailability', act: null },
        { id: 'domains-list', label: 'Seznam domén', m: 'POST', p: 'command=domains-list', async: false, job: 'Wedos\\SyncDomains', act: null },
        { id: 'domain-create', label: 'Registrace domény', m: 'POST', p: 'command=domain-create', async: true, job: 'Wedos\\RegisterDomain', act: 'order' },
        { id: 'domain-renew', label: 'Prodloužení domény', m: 'POST', p: 'command=domain-renew', async: true, job: 'Wedos\\RenewDomain', act: 'order' },
        { id: 'domain-transfer', label: 'Transfer domény', m: 'POST', p: 'command=domain-transfer', async: true, job: 'Wedos\\TransferDomain', act: 'order' },
        { id: 'domain-update-ns', label: 'Změna name serverů', m: 'POST', p: 'command=domain-update-ns', async: true, job: 'Wedos\\UpdateNameservers', act: 'order' },
        { id: 'dns-rows-list', label: 'Záznamy zóny', m: 'POST', p: 'command=dns-rows-list', async: false, job: 'Wedos\\PullZone', act: null },
        { id: 'dns-row-add', label: 'Přidat DNS záznam', m: 'POST', p: 'command=dns-row-add', async: false, job: 'Wedos\\WriteZoneRow', act: 'order' },
        { id: 'dns-row-delete', label: 'Smazat DNS záznam', m: 'POST', p: 'command=dns-row-delete', async: false, job: 'Wedos\\WriteZoneRow', act: 'order' },
        { id: 'dns-domain-commit', label: 'Publikovat zónu', m: 'POST', p: 'command=dns-domain-commit', async: true, job: 'Wedos\\CommitZone', act: 'order' },
        { id: 'credit-info', label: 'Zůstatek kreditu', m: 'POST', p: 'command=credit-info', async: false, job: 'Wedos\\SyncCredit', act: null }
      ]
    },
    {
      id: 'aapanel', name: 'aaPanel', short: 'aaPanel',
      role: 'Webhosting na sdílených uzlech — weby, databáze, certifikáty',
      base: 'https://{node}:8888',
      transport: 'POST application/x-www-form-urlencoded na akční cesty (?action=…)',
      auth: 'request_time = unix čas, request_token = md5(request_time + md5(api_key))',
      authNote: 'Pořadí v hashi je (čas + hash klíče), ne naopak — dřívější poznámka v onhost-live-adapter.js to měla obráceně. Panel navíc pouští jen IP z whitelistu a token platí ±60 s, takže hodiny workeru musí být v NTP.',
      rate: { budget: 600, window: 'minuta / uzel', note: 'Panel neškrtí, ale sdílí CPU s weby zákazníků — víc než 10 volání/s na uzel dělá latenci webům.' },
      push: false,
      poll: 'stav uzlu 30 s, seznam webů 5 min',
      client: 'App\\\\Integrations\\\\AaPanel\\\\AaPanelConnector',
      env: ['AAPANEL_NODES', 'AAPANEL_API_KEY_PRG1', 'AAPANEL_API_KEY_BRQ1', 'AAPANEL_TIMEOUT'],
      caps: ['Zakládání a mazání webů', 'MySQL databáze a uživatelé', 'Let\u2019s Encrypt a vynucení HTTPS', 'PHP verze a limity', 'Čtení logů webu', 'Cron úlohy'],
      quirks: [
        'Odpověď je někdy `{status:false, msg:"…"}` s HTTP 200 a někdy prostý string — mapper musí unést obojí.',
        'Klíč je na uzel, ne na cluster: každý aaPanel má vlastní API key a vlastní whitelist.',
        'Shell zákazníka nevede přes panel, ale přes bastion pod uživatelem webu — panel API k tomu nepoužívat.'
      ],
      actions: [
        { id: 'system-total', label: 'Zátěž uzlu', m: 'POST', p: '/system?action=GetSystemTotal', async: false, job: 'AaPanel\\PullNodeLoad', act: null },
        { id: 'sites', label: 'Seznam webů', m: 'POST', p: '/data?action=getData&table=sites', async: false, job: 'AaPanel\\SyncSites', act: null },
        { id: 'site-add', label: 'Založit web', m: 'POST', p: '/site?action=AddSite', async: false, job: 'AaPanel\\CreateSite', act: 'order' },
        { id: 'site-delete', label: 'Smazat web', m: 'POST', p: '/site?action=DeleteSite', async: false, job: 'AaPanel\\DeleteSite', act: 'order' },
        { id: 'db-add', label: 'Založit databázi', m: 'POST', p: '/database?action=AddDatabase', async: false, job: 'AaPanel\\CreateDatabase', act: 'order' },
        { id: 'php-set', label: 'Nastavit PHP verzi', m: 'POST', p: '/site?action=SetPHPVersion', async: false, job: 'AaPanel\\SetPhpVersion', act: 'order' },
        { id: 'ssl', label: 'Vydat certifikát', m: 'POST', p: '/site?action=SetSSL', async: true, job: 'AaPanel\\IssueCertificate', act: 'order' },
        { id: 'https', label: 'Vynutit HTTPS', m: 'POST', p: '/site?action=HttpToHttps', async: false, job: 'AaPanel\\ForceHttps', act: 'order' },
        { id: 'log', label: 'Číst log webu', m: 'POST', p: '/files?action=GetFileBody', async: false, job: 'AaPanel\\TailSiteLog', act: null },
        { id: 'cron', label: 'Cron úloha', m: 'POST', p: '/crontab?action=AddCrontab', async: false, job: 'AaPanel\\CreateCron', act: 'order' }
      ]
    },
    {
      id: 'proxmox', name: 'Proxmox VE', short: 'Proxmox',
      role: 'VPS a dedikované instance — cluster, disky, zálohy, konzole',
      base: 'https://{node}:8006/api2/json',
      transport: 'REST, JSON; POST/PUT form-encoded',
      auth: 'Authorization: PVEAPIToken=uzivatel@realm!tokenid=secret',
      authNote: 'Token má vlastní role (privilege separation). Provisioning potřebuje VM.Allocate, VM.Config.*, Datastore.AllocateSpace; NOC stačí VM.Audit + VM.Console. Ticket z /access/ticket vydáváme jen pro noVNC a s minutovou platností.',
      rate: { budget: 120, window: 'minuta / uzel', note: 'API běží na pvedaemonu vedle hypervizoru — dotazování /cluster/resources častěji než 2 s zvedá load na celém clusteru.' },
      push: false,
      poll: 'cluster/resources 2 s na dohledové zdi, task log podle UPID',
      client: 'App\\\\Integrations\\\\Proxmox\\\\ProxmoxConnector',
      env: ['PROXMOX_NODES', 'PROXMOX_TOKEN_ID', 'PROXMOX_TOKEN_SECRET', 'PROXMOX_VERIFY_TLS', 'PROXMOX_TEMPLATE_VMID'],
      caps: ['Vytvoření a klon VM', 'Start / stop / reset / shutdown', 'Změna CPU, RAM, disku', 'Snapshoty a vzdump zálohy', 'noVNC konzole', 'Task log a metriky'],
      quirks: [
        'Skoro každá měnící operace vrací UPID string, ne výsledek. Hotovo je až `exitstatus: "OK"` z /tasks/{upid}/status — job musí čekat na task, jinak se objednávka označí aktivní nad neexistující VM.',
        'vmid je unikátní v celém clusteru; brát ho z /cluster/nextid a rezervovat u nás, jinak dvě paralelní objednávky sáhnou po stejném čísle.',
        'TLS je z instalace self-signed — vlastní CA do trust store, nikdy `verify: false` v produkci.'
      ],
      actions: [
        { id: 'version', label: 'Verze a spojení', m: 'GET', p: '/version', async: false, job: 'Proxmox\\CheckConnection', act: null },
        { id: 'cluster-status', label: 'Stav clusteru', m: 'GET', p: '/cluster/status', async: false, job: 'Proxmox\\PullClusterStatus', act: null },
        { id: 'resources', label: 'Přehled VM', m: 'GET', p: '/cluster/resources?type=vm', async: false, job: 'Proxmox\\SyncGuests', act: null },
        { id: 'nextid', label: 'Volné vmid', m: 'GET', p: '/cluster/nextid', async: false, job: 'Proxmox\\ReserveVmid', act: 'order' },
        { id: 'clone', label: 'Klon šablony', m: 'POST', p: '/nodes/{node}/qemu/{vmid}/clone', async: true, job: 'Proxmox\\CloneTemplate', act: 'order' },
        { id: 'config', label: 'Změna parametrů', m: 'PUT', p: '/nodes/{node}/qemu/{vmid}/config', async: true, job: 'Proxmox\\ApplyConfig', act: 'order' },
        { id: 'resize', label: 'Zvětšení disku', m: 'PUT', p: '/nodes/{node}/qemu/{vmid}/resize', async: true, job: 'Proxmox\\ResizeDisk', act: 'order' },
        { id: 'power', label: 'Start / stop / reset', m: 'POST', p: '/nodes/{node}/qemu/{vmid}/status/{action}', async: true, job: 'Proxmox\\PowerAction', act: 'order' },
        { id: 'status', label: 'Stav a metriky VM', m: 'GET', p: '/nodes/{node}/qemu/{vmid}/status/current', async: false, job: 'Proxmox\\PullGuestStatus', act: null },
        { id: 'vzdump', label: 'Záloha', m: 'POST', p: '/nodes/{node}/vzdump', async: true, job: 'Proxmox\\RunBackup', act: 'order' },
        { id: 'vncproxy', label: 'noVNC ticket', m: 'POST', p: '/nodes/{node}/qemu/{vmid}/vncproxy', async: false, job: 'Proxmox\\MintConsoleTicket', act: null },
        { id: 'task', label: 'Stav úlohy', m: 'GET', p: '/nodes/{node}/tasks/{upid}/status', async: false, job: 'Proxmox\\AwaitTask', act: null }
      ]
    },
    {
      id: 'pterodactyl', name: 'Pterodactyl', short: 'Pterodactyl',
      role: 'Herní servery — instance, alokace, konzole, zálohy',
      base: 'https://panel.onhost.cz',
      transport: 'REST, JSON:API obálka { object, attributes }, ?include= pro relace',
      auth: 'Bearer ptla_… pro /api/application (interní), Bearer ptlc_… pro /api/client (jménem zákazníka)',
      authNote: 'Klientský klíč nikdy nesmí opustit server. Konzole v prohlížeči dostane jen krátkodobý token z /api/client/servers/{id}/websocket, který vyprší v minutách — přesně jak to prototyp předpokládá.',
      rate: { budget: 240, window: 'minuta', note: 'Výchozí limit panelu je 240 požadavků/min na klíč; překročení vrací 429 s Retry-After, který worker musí respektovat.' },
      push: true,
      poll: 'Wings WebSocket na konzoli a stavy, zbytek 15 s',
      client: 'App\\\\Integrations\\\\Pterodactyl\\\\PterodactylConnector',
      env: ['PTERODACTYL_URL', 'PTERODACTYL_APP_KEY', 'PTERODACTYL_CLIENT_KEY', 'PTERODACTYL_DEFAULT_NEST', 'PTERODACTYL_DEFAULT_EGG'],
      caps: ['Vytvoření serveru z eggu', 'Alokace portů', 'Power akce a RCON příkazy', 'Konzole přes Wings WS', 'Zálohy a plány', 'Databáze serveru'],
      quirks: [
        'Vytvoření serveru je asynchronní instalace: `POST /api/application/servers` vrátí 201, ale server je použitelný teprve po `attributes.container.installed = 1`.',
        'Alokace je potřeba vybrat předem z /nodes/{id}/allocations?filter[assigned]=0 — panel sám volnou nenajde.',
        'Chyby přicházejí v poli `errors[]` (JSON:API); mapper má vzít `detail` a `meta.rule`, ne jen HTTP kód.'
      ],
      actions: [
        { id: 'app-servers', label: 'Seznam serverů (app)', m: 'GET', p: '/api/application/servers?include=allocations', async: false, job: 'Pterodactyl\\SyncServers', act: null },
        { id: 'allocations', label: 'Volné alokace', m: 'GET', p: '/api/application/nodes/{id}/allocations?filter[assigned]=0', async: false, job: 'Pterodactyl\\PickAllocation', act: 'order' },
        { id: 'server-create', label: 'Vytvořit server', m: 'POST', p: '/api/application/servers', async: true, job: 'Pterodactyl\\CreateServer', act: 'order' },
        { id: 'server-suspend', label: 'Pozastavit server', m: 'POST', p: '/api/application/servers/{id}/suspend', async: false, job: 'Pterodactyl\\SuspendServer', act: 'order' },
        { id: 'server-delete', label: 'Smazat server', m: 'DELETE', p: '/api/application/servers/{id}', async: false, job: 'Pterodactyl\\DeleteServer', act: 'order' },
        { id: 'resources', label: 'Zátěž serveru', m: 'GET', p: '/api/client/servers/{id}/resources', async: false, job: 'Pterodactyl\\PullResources', act: null },
        { id: 'power', label: 'Power signál', m: 'POST', p: '/api/client/servers/{id}/power', async: false, job: 'Pterodactyl\\PowerAction', act: 'order' },
        { id: 'command', label: 'Příkaz do konzole', m: 'POST', p: '/api/client/servers/{id}/command', async: false, job: 'Pterodactyl\\SendCommand', act: 'ticket' },
        { id: 'websocket', label: 'Token pro konzoli', m: 'GET', p: '/api/client/servers/{id}/websocket', async: false, job: 'Pterodactyl\\MintConsoleToken', act: null },
        { id: 'backup', label: 'Vytvořit zálohu', m: 'POST', p: '/api/client/servers/{id}/backups', async: true, job: 'Pterodactyl\\CreateBackup', act: 'order' }
      ]
    },
    {
      id: 'ispconfig', name: 'ISPConfig 3', short: 'ISPConfig',
      role: 'Mailhosting, DNS na vlastních uzlech, weby korporátních klientů',
      base: 'https://{node}:8080/remote/json.php',
      transport: 'POST JSON na ?<funkce>, odpověď { code, message, response }',
      auth: 'login(username, password) → session_id, který jde do každého dalšího volání; na konci logout()',
      authNote: 'Remote uživatel má oprávnění po jednotlivých funkcích — provisioning účet nesmí mít `server_*` funkce. Session drží server v paměti, takže ji cachovat na worker (Redis, TTL 10 min) a nezakládat novou při každém jobu.',
      rate: { budget: 300, window: 'minuta / uzel', note: 'Limit není, ale každý zápis zakládá řádek v sys_datalog — dávkové operace dělat po 50 a mezi dávkami čekat na zpracování.' },
      push: false,
      poll: 'sys_datalog 5 s během provisioningu, jinak 60 s',
      client: 'App\\\\Integrations\\\\IspConfig\\\\IspConfigConnector',
      env: ['ISPCONFIG_NODES', 'ISPCONFIG_REMOTE_USER', 'ISPCONFIG_REMOTE_PASSWORD', 'ISPCONFIG_VERIFY_TLS'],
      caps: ['Klienti a limity', 'Weby a databáze', 'Mail domény a mailboxy', 'DNS zóny na vlastních NS', 'Kvóty a statistiky'],
      quirks: [
        'HTTP 200 s `code: "remote_fault"` je chyba — a text je v `message`. Nikdy nespoléhat na status.',
        'Zápis není hotový po odpovědi: změna leží v sys_datalog a aplikuje ji server cron (typicky do minuty). Job musí čekat na potvrzení, jinak zákazník dostane „hotovo“ nad neexistujícím mailboxem.',
        'Číslo serveru se zjišťuje přes server_get_serverid_by_ip — nehardkódovat ho do konfigurace.'
      ],
      actions: [
        { id: 'login', label: 'Přihlášení / session', m: 'POST', p: '?login', async: false, job: 'IspConfig\\OpenSession', act: null },
        { id: 'client-add', label: 'Založit klienta', m: 'POST', p: '?client_add', async: true, job: 'IspConfig\\CreateClient', act: 'order' },
        { id: 'client-get', label: 'Limity klienta', m: 'POST', p: '?client_get', async: false, job: 'IspConfig\\PullClient', act: null },
        { id: 'web-add', label: 'Založit web', m: 'POST', p: '?sites_web_domain_add', async: true, job: 'IspConfig\\CreateWebDomain', act: 'order' },
        { id: 'web-update', label: 'Změna webu', m: 'POST', p: '?sites_web_domain_update', async: true, job: 'IspConfig\\UpdateWebDomain', act: 'order' },
        { id: 'db-add', label: 'Založit databázi', m: 'POST', p: '?sites_database_add', async: true, job: 'IspConfig\\CreateDatabase', act: 'order' },
        { id: 'mail-domain', label: 'Mail doména', m: 'POST', p: '?mail_domain_add', async: true, job: 'IspConfig\\CreateMailDomain', act: 'order' },
        { id: 'mail-user', label: 'Mailbox', m: 'POST', p: '?mail_user_add', async: true, job: 'IspConfig\\CreateMailbox', act: 'order' },
        { id: 'dns-zone', label: 'DNS zóna', m: 'POST', p: '?dns_zone_add', async: true, job: 'IspConfig\\CreateDnsZone', act: 'order' },
        { id: 'datalog', label: 'Stav změn (sys_datalog)', m: 'POST', p: '?sys_datalog_get', async: false, job: 'IspConfig\\AwaitDatalog', act: null },
        { id: 'logout', label: 'Odhlášení', m: 'POST', p: '?logout', async: false, job: 'IspConfig\\CloseSession', act: null }
      ]
    }
  ];

  /* ==========================================================================
   * Workflow řetězy — co se stane po zaplacení objednávky.
   * V Laravelu to je Bus::chain() s jedním job na krok; `wait` krok je
   * job, který se releasuje zpět do fronty, dokud cizí systém nepotvrdí.
   * ====================================================================== */
  var WORKFLOWS = [
    {
      id: 'provision.webhosting', label: 'Webhosting', product: 'Web Start / Pro / Scale',
      chain: 'App\\\\Provisioning\\\\Workflows\\\\ProvisionWebhosting',
      steps: [
        { s: 'Kontrola objednávky a limitů zákazníka', p: 'onhost', a: 'ValidateOrder' },
        { s: 'Výběr uzlu podle volné kapacity', p: 'onhost', a: 'PickNode' },
        { s: 'Založení webu na panelu', p: 'aapanel', a: 'CreateSite' },
        { s: 'Databáze a uživatel', p: 'aapanel', a: 'CreateDatabase' },
        { s: 'DNS záznamy A/AAAA/MX', p: 'wedos', a: 'WriteZoneRow' },
        { s: 'Publikace zóny (commit)', p: 'wedos', a: 'CommitZone' },
        { s: 'Certifikát Let\u2019s Encrypt', p: 'aapanel', a: 'IssueCertificate' },
        { s: 'Objednávka → aktivní, mail Vítejte', p: 'onhost', a: 'ActivateOrder' }
      ]
    },
    {
      id: 'provision.vps', label: 'VPS', product: 'Compute 2 / 4 / 8',
      chain: 'App\\\\Provisioning\\\\Workflows\\\\ProvisionVps',
      steps: [
        { s: 'Rezervace vmid a uzlu', p: 'proxmox', a: 'ReserveVmid' },
        { s: 'Klon šablony', p: 'proxmox', a: 'CloneTemplate' },
        { s: 'Čekání na task (UPID)', p: 'proxmox', a: 'AwaitTask' },
        { s: 'CPU, RAM, disk podle tarifu', p: 'proxmox', a: 'ApplyConfig' },
        { s: 'Cloud-init, SSH klíč, síť', p: 'proxmox', a: 'ApplyCloudInit' },
        { s: 'První start a health check', p: 'proxmox', a: 'PowerAction' },
        { s: 'Reverzní DNS a A záznam', p: 'wedos', a: 'WriteZoneRow' },
        { s: 'Objednávka → aktivní, přístupy mailem', p: 'onhost', a: 'ActivateOrder' }
      ]
    },
    {
      id: 'provision.game', label: 'Herní server', product: 'Game 4 / 8 / 16 slotů',
      chain: 'App\\\\Provisioning\\\\Workflows\\\\ProvisionGameServer',
      steps: [
        { s: 'Výběr volné alokace na uzlu', p: 'pterodactyl', a: 'PickAllocation' },
        { s: 'Uživatel panelu pro zákazníka', p: 'pterodactyl', a: 'EnsureUser' },
        { s: 'Vytvoření serveru z eggu', p: 'pterodactyl', a: 'CreateServer' },
        { s: 'Čekání na instalaci (installed = 1)', p: 'pterodactyl', a: 'AwaitInstall' },
        { s: 'Subdomain v DNS na alokaci', p: 'wedos', a: 'WriteZoneRow' },
        { s: 'Plán záloh', p: 'pterodactyl', a: 'CreateSchedule' },
        { s: 'Objednávka → aktivní, konzole zákazníkovi', p: 'onhost', a: 'ActivateOrder' }
      ]
    },
    {
      id: 'provision.mail', label: 'Mailhosting', product: 'Mail Business',
      chain: 'App\\\\Provisioning\\\\Workflows\\\\ProvisionMailhosting',
      steps: [
        { s: 'Klient na ISPConfig uzlu', p: 'ispconfig', a: 'CreateClient' },
        { s: 'Mail doména', p: 'ispconfig', a: 'CreateMailDomain' },
        { s: 'Čekání na sys_datalog', p: 'ispconfig', a: 'AwaitDatalog' },
        { s: 'Mailboxy a aliasy', p: 'ispconfig', a: 'CreateMailbox' },
        { s: 'MX, SPF, DKIM, DMARC', p: 'wedos', a: 'WriteZoneRow' },
        { s: 'Publikace zóny (commit)', p: 'wedos', a: 'CommitZone' },
        { s: 'Kontrola doručitelnosti', p: 'onhost', a: 'VerifyDeliverability' },
        { s: 'Objednávka → aktivní', p: 'onhost', a: 'ActivateOrder' }
      ]
    },
    {
      id: 'domain.register', label: 'Registrace domény', product: 'Doména .cz / .eu / .com',
      chain: 'App\\\\Provisioning\\\\Workflows\\\\RegisterDomain',
      steps: [
        { s: 'Dostupnost a cena', p: 'wedos', a: 'CheckDomainAvailability' },
        { s: 'Kontakt a NSSET', p: 'wedos', a: 'EnsureContact' },
        { s: 'Registrace u registrátora', p: 'wedos', a: 'RegisterDomain' },
        { s: 'Čekání na potvrzení registru', p: 'wedos', a: 'AwaitRegistryResult' },
        { s: 'Zóna a výchozí záznamy', p: 'wedos', a: 'CreateZone' },
        { s: 'Objednávka → aktivní, doklad', p: 'onhost', a: 'ActivateOrder' }
      ]
    },
    {
      id: 'lifecycle.suspend', label: 'Pozastavení pro neplacení', product: 'napříč produkty',
      chain: 'App\\\\Provisioning\\\\Workflows\\\\SuspendService',
      steps: [
        { s: 'Faktura po splatnosti > 30 dní', p: 'onhost', a: 'DunningDecision' },
        { s: 'Web: zastavení a hlášková stránka', p: 'aapanel', a: 'StopSite' },
        { s: 'VPS: shutdown, disk zachován', p: 'proxmox', a: 'PowerAction' },
        { s: 'Hra: suspend v panelu', p: 'pterodactyl', a: 'SuspendServer' },
        { s: 'Mail: příjem ano, odesílání ne', p: 'ispconfig', a: 'LimitMailbox' },
        { s: 'Objednávka → pozastaveno, notifikace', p: 'onhost', a: 'SuspendOrder' }
      ]
    }
  ];

  /* ---------- mapování zdrojů ---------- */
  var PRODUCTS = {
    aapanel: ['Web Start', 'Web Pro', 'Web Scale'],
    proxmox: ['Compute 2', 'Compute 4', 'Compute 8', 'Compute 16'],
    pterodactyl: ['Game 8 slotů', 'Game 16 slotů', 'Game 32 slotů'],
    ispconfig: ['Mail Business', 'Mail Enterprise', 'Corp Web'],
    wedos: ['Doména .cz', 'Doména .eu', 'Doména .com', 'DNS zóna']
  };
  var NODES = {
    aapanel: ['prg1-web3', 'prg1-web4', 'brq1-web1'],
    proxmox: ['prg1-n2', 'prg1-n3', 'brq1-n1'],
    pterodactyl: ['prg1-game1', 'prg1-game2'],
    ispconfig: ['prg1-mail1', 'brq1-mail1'],
    wedos: ['wapi']
  };
  var CUSTOMERS = ['Alza Studio s.r.o.', 'Hraj.gg', 'Bistro Kotelna', 'MŠ Nová Ves', 'Tomáš Bervid',
    'Statutární město Brno', 'Wave Digital', 'Klinika Podolí', 'GameHost CZ', 'Petr Doležal'];

  function extRef(pid, i) {
    if (pid === 'proxmox') return 'vmid ' + (110 + i % 60) + ' @ ' + pick(NODES.proxmox);
    if (pid === 'pterodactyl') return 'srv ' + (1000 + i).toString(16) + ' @ ' + pick(NODES.pterodactyl);
    if (pid === 'aapanel') return 'site #' + (400 + i) + ' @ ' + pick(NODES.aapanel);
    if (pid === 'ispconfig') return 'client_id ' + (200 + i) + ' @ ' + pick(NODES.ispconfig);
    return 'domain #' + (7000 + i);
  }

  var MAPPINGS = (function () {
    var out = [], ids = ['aapanel', 'proxmox', 'pterodactyl', 'ispconfig', 'wedos'];
    for (var i = 0; i < 64; i++) {
      var pid = ids[i % ids.length];
      var drift = rnd() < 0.09;
      out.push({
        svc: 'S-' + (1000 + i * 7),
        customer: CUSTOMERS[i % CUSTOMERS.length],
        product: pick(PRODUCTS[pid]),
        provider: pid,
        ext: extRef(pid, i),
        state: rnd() < 0.9 ? 'aktivni' : (rnd() < 0.5 ? 'pozastaveno' : 'provisioning'),
        drift: drift,
        driftNote: drift ? pick([
          'disk 80 GB v Proxmoxu vs. 60 GB v tarifu',
          'PHP 8.1 na panelu vs. 8.3 v konfiguraci služby',
          'mailbox nad kvótou tarifu',
          'záznam A míří na starou IP',
          'server v panelu je suspended, u nás aktivní'
        ]) : '',
        sync: int(1, 240)
      });
    }
    return out;
  })();

  /* ---------- fronta úloh ---------- */
  var JOB_STATES = ['queued', 'running', 'done', 'failed', 'retrying'];
  var FAILS = [
    { m: 'HTTP 429 od panelu, Retry-After 12 s', p: 'pterodactyl' },
    { m: 'task UPID skončil exitstatus: got timeout', p: 'proxmox' },
    { m: 'sys_datalog nepotvrzen do 120 s', p: 'ispconfig' },
    { m: 'kód 2302: doména je již registrovaná', p: 'wedos' },
    { m: 'status:false — „SSL certifikát nelze vydat, A záznam nemíří na uzel"', p: 'aapanel' },
    { m: 'connect timeout na uzel (10 s)', p: 'proxmox' },
    { m: 'zóna nebyla publikována — chybí dns-domain-commit', p: 'wedos' }
  ];

  var JOBS = (function () {
    var out = [];
    for (var i = 0; i < 96; i++) {
      var wf = WORKFLOWS[i % WORKFLOWS.length];
      var step = int(1, wf.steps.length);
      var st = rnd() < 0.62 ? 'done' : pick(['queued', 'running', 'failed', 'retrying', 'done']);
      var pid = wf.steps[step - 1].p === 'onhost' ? wf.steps[Math.max(0, step - 2)].p : wf.steps[step - 1].p;
      if (pid === 'onhost') pid = 'wedos';
      var f = null;
      if (st === 'failed' || st === 'retrying') {
        f = FAILS.filter(function (x) { return x.p === pid; })[0] || pick(FAILS);
      }
      var mins = int(0, 2880);
      out.push({
        id: 'J-' + (4200 - i),
        wf: wf.id, wfLabel: wf.label,
        provider: pid,
        ref: 'ORD-' + (9000 + int(1, 400)),
        customer: CUSTOMERS[i % CUSTOMERS.length],
        state: st,
        step: step, steps: wf.steps.length,
        stepLabel: wf.steps[step - 1].s,
        attempts: st === 'retrying' ? int(2, 4) : (st === 'failed' ? 5 : 1),
        maxAttempts: 5,
        idem: 'ord-' + (9000 + i) + ':' + wf.id + ':v1',
        handle: pid === 'proxmox' ? 'UPID:prg1-n3:0004A1' + i.toString(16) + ':vzdump::root@pam:'
          : pid === 'ispconfig' ? 'datalog #' + (88000 + i)
            : pid === 'pterodactyl' ? 'install ' + (rnd() < 0.5 ? 'running' : 'done')
              : pid === 'wedos' ? 'wapi req #' + (5100 + i) : 'sync #' + (300 + i),
        mins: mins, when: ago(mins),
        ms: st === 'queued' ? 0 : int(180, 42000),
        error: f ? f.m : '',
        queue: 'provisioning:' + pid
      });
    }
    return out;
  })();

  /* ---------- log volání ---------- */
  var CALLS = (function () {
    var out = [];
    for (var i = 0; i < 180; i++) {
      var p = PROVIDERS[i % PROVIDERS.length];
      var a = p.actions[int(0, p.actions.length - 1)];
      var bad = rnd() < (p.id === 'ispconfig' ? 0.085 : 0.022);
      out.push({
        t: clock(i * 3 + int(0, 2)),
        provider: p.id,
        method: a.m,
        path: a.p,
        action: a.label,
        code: bad ? pick([429, 500, 504, 401, 200]) : 200,
        body: bad ? pick(['remote_fault', 'status:false', 'errors[0].detail', 'kód 2302', 'Retry-After 12']) : 'ok',
        ok: !bad,
        ms: bad ? int(800, 3000) : int(30, 600),
        who: pick(['worker:provisioning', 'worker:sync', 'jan.k@onhost.cz', 'scheduler', 'noc:vera.p@onhost.cz'])
      });
    }
    return out;
  })();

  /* ---------- zdraví a rozpočty ---------- */
  var HEALTH = PROVIDERS.map(function (p, i) {
    var mine = CALLS.filter(function (c) { return c.provider === p.id; });
    var bad = mine.filter(function (c) { return !c.ok; }).length;
    var lat = mine.map(function (c) { return c.ms; }).sort(function (a, b) { return a - b; });
    var used = [0.71, 0.34, 0.58, 0.83, 0.22][i];
    return {
      id: p.id, name: p.name,
      up: bad / Math.max(1, mine.length) < 0.2,
      calls24: mine.length * 8 + 40,
      errors24: bad * 8,
      errRate: Math.round((bad / Math.max(1, mine.length)) * 1000) / 10,
      p50: lat[Math.floor(lat.length * 0.5)] || 0,
      p95: lat[Math.floor(lat.length * 0.95)] || 0,
      last: ago(int(0, 14)),
      budget: p.rate.budget, budgetUsed: Math.round(p.rate.budget * used), budgetPct: Math.round(used * 100),
      window: p.rate.window
    };
  });

  /* ---------- .env kontrakt ---------- */
  var ENV_EXTRA = [
    { k: 'APP_URL', v: 'https://panel.onhost.cz', d: 'Kanonická adresa panelu — z ní se staví odkazy v mailech.' },
    { k: 'QUEUE_CONNECTION', v: 'redis', d: 'Horizon nad Redisem; fronty per konektor (provisioning:proxmox, …).' },
    { k: 'ONHOST_VAT_RATE', v: '21', d: 'DPH v procentech. Jedno místo pravdy, UI ji nepočítá po svém.' },
    { k: 'ONHOST_DUNNING_SUSPEND_DAYS', v: '30', d: 'Po kolika dnech po splatnosti se služba pozastaví.' },
    { k: 'ONHOST_CONSOLE_TOKEN_TTL', v: '120', d: 'Sekundy platnosti tokenu pro konzoli (Wings WS, noVNC ticket).' },
    { k: 'ONHOST_PROVIDER_TIMEOUT', v: '10', d: 'Výchozí timeout HTTP volání do cizího API v sekundách.' },
    { k: 'PAYMENT_GATEWAY', v: 'comgate', d: 'Jediné push rozhraní v systému — webhook o platbě.' },
    { k: 'PAYMENT_WEBHOOK_SECRET', v: '—', d: 'Podpis webhooku; volání bez platného podpisu se loguje a zahodí.' }
  ];

  /* ---------- API ---------- */
  var subs = [];
  var jobs = JOBS.slice();

  function emit() { subs.slice().forEach(function (f) { try { f(); } catch (e) {} }); }
  function audit(what, detail) {
    if (window.OnhostStore && window.OnhostStore.audit) {
      try { window.OnhostStore.audit('integrace', what, detail); } catch (e) {}
    }
  }

  window.OnhostIntegrations = {
    providers: function () { return PROVIDERS.slice(); },
    provider: function (id) { return PROVIDERS.filter(function (p) { return p.id === id; })[0] || PROVIDERS[0]; },
    actions: function (id) { return this.provider(id).actions.slice(); },
    workflows: function () { return WORKFLOWS.slice(); },
    workflow: function (id) { return WORKFLOWS.filter(function (w) { return w.id === id; })[0] || WORKFLOWS[0]; },
    jobs: function (f) {
      f = f || {};
      return jobs.filter(function (j) {
        if (f.state && j.state !== f.state) return false;
        if (f.provider && j.provider !== f.provider) return false;
        if (f.wf && j.wf !== f.wf) return false;
        return true;
      });
    },
    job: function (id) { return jobs.filter(function (j) { return j.id === id; })[0] || null; },
    retryJob: function (id, who) {
      var j = this.job(id); if (!j) return null;
      j.state = 'retrying'; j.attempts = Math.min(j.maxAttempts, j.attempts + 1);
      j.error = j.error || 'ruční opakování'; j.mins = 0; j.when = 'teď';
      audit('job.retry', id + ' · ' + j.wfLabel + ' · ' + (who || 'admin'));
      emit(); return j;
    },
    cancelJob: function (id, who) {
      var j = this.job(id); if (!j) return null;
      j.state = 'failed'; j.error = 'zrušeno ručně (' + (who || 'admin') + ')';
      audit('job.cancel', id + ' · ' + j.wfLabel);
      emit(); return j;
    },
    mappings: function (f) {
      f = f || {};
      return MAPPINGS.filter(function (m) {
        if (f.provider && m.provider !== f.provider) return false;
        if (f.drift && !m.drift) return false;
        return true;
      });
    },
    calls: function (f) {
      f = f || {};
      return CALLS.filter(function (c) {
        if (f.provider && c.provider !== f.provider) return false;
        if (f.errorsOnly && c.ok) return false;
        return true;
      });
    },
    health: function (id) {
      return id ? HEALTH.filter(function (h) { return h.id === id; })[0] : HEALTH.slice();
    },
    env: function () {
      var rows = [];
      PROVIDERS.forEach(function (p) {
        p.env.forEach(function (k) { rows.push({ k: k, provider: p.id, providerName: p.short, client: p.client }); });
      });
      return { providers: rows, platform: ENV_EXTRA.slice() };
    },
    kpis: function () {
      var q = jobs.filter(function (j) { return j.state === 'queued' || j.state === 'running'; }).length;
      var bad = jobs.filter(function (j) { return j.state === 'failed'; }).length;
      var drift = MAPPINGS.filter(function (m) { return m.drift; }).length;
      var lat = HEALTH.map(function (h) { return h.p95; }).sort(function (a, b) { return a - b; });
      var p95 = lat[Math.floor(lat.length / 2)];
      var up = HEALTH.filter(function (h) { return h.up; }).length;
      var worst = HEALTH.slice().sort(function (a, b) { return b.errRate - a.errRate; })[0];
      return { connectors: PROVIDERS.length, up: up, queue: q, failed: bad, drift: drift, p95: p95,
        worst: worst.name, worstErr: worst.errRate, worstP95: worst.p95 };
    },
    on: function (fn) { subs.push(fn); return function () { subs = subs.filter(function (f) { return f !== fn; }); }; }
  };
})();
