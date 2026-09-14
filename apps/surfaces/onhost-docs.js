// Onhost — obsah dokumentace (docs.onhost.cz).
// Jeden článek = { id, sec, title, lead, updated, read, blocks[] }.
// Bloky: p | h | code | note | steps | list | table | kbd.
// cs=true → čeština, jinak angličtina.

export function docsSections(cs) {
  const _ = (a, b) => (cs ? a : b);
  return [
    { id: 'start', title: _('Začínáme', 'Getting started'), note: _('účet, převod, první den', 'account, migration, day one') },
    { id: 'web', title: _('Webhosting', 'Web hosting'), note: _('PHP, cron, certifikáty', 'PHP, cron, certificates') },
    { id: 'vps', title: _('VPS a servery', 'VPS and servers'), note: _('SSH, firewall, zálohy', 'SSH, firewall, backups') },
    { id: 'game', title: _('Herní servery', 'Game servers'), note: _('Minecraft, pluginy, hráči', 'Minecraft, plugins, players') },
    { id: 'api', title: _('API a automatizace', 'API and automation'), note: _('tokeny, limity, webhooky', 'tokens, limits, webhooks') }
  ];
}

export function docsArticles(cs) {
  const _ = (a, b) => (cs ? a : b);

  return [
    // ── Začínáme ─────────────────────────────────────────────────────────
    {
      id: 'ucet', sec: 'start', read: '6 min', updated: _('12. srpna 2026', '12 August 2026'),
      title: _('První přihlášení a zabezpečení účtu', 'First sign-in and securing your account'),
      lead: _('Účet je klíč ke všem službám. Deset minut na začátku ušetří noc, kterou byste jinak strávili obnovou z zálohy.', 'Your account is the key to every service. Ten minutes now saves a night you would otherwise spend restoring from backup.'),
      blocks: [
        { k: 'p', t: _('Po objednávce dostanete dva e-maily: potvrzení objednávky a odkaz na nastavení hesla. Odkaz platí 24 hodin. Když propadne, vyžádejte si nový na přihlašovací stránce — starý tím okamžitě přestane fungovat.', 'After ordering you get two e-mails: an order confirmation and a link to set your password. The link is valid for 24 hours. If it expires, request a new one on the sign-in page — the old one stops working immediately.') },
        { k: 'h', t: _('Zapněte dvoufázové ověření', 'Turn on two-factor authentication') },
        { k: 'p', t: _('Bez druhého faktoru stojí mezi útočníkem a vaším serverem jen heslo, které jste možná použili i jinde. V panelu jde nastavení najít v Účet → Přihlášení a bezpečnost.', 'Without a second factor the only thing between an attacker and your server is a password you may have reused. In the panel it lives under Account → Sign-in and security.') },
        { k: 'steps', items: [
          _('Otevřete Účet → Přihlášení a bezpečnost a klikněte na Zapnout 2FA.', 'Open Account → Sign-in and security and click Enable 2FA.'),
          _('Naskenujte QR kód aplikací (Aegis, 1Password, Bitwarden — SMS nedoporučujeme, dá se přesměrovat).', 'Scan the QR code with an app (Aegis, 1Password, Bitwarden — we do not recommend SMS, it can be hijacked).'),
          _('Opište šestimístný kód a potvrďte.', 'Type the six-digit code and confirm.'),
          _('Stáhněte deset záložních kódů a uložte je mimo prohlížeč. Tohle je jediná chvíle, kdy je uvidíte.', 'Download the ten recovery codes and store them outside your browser. This is the only time you will see them.')
        ] },
        { k: 'note', tone: 'warn', title: _('Záložní kódy neukládejte do stejného správce hesel', 'Do not store recovery codes in the same password manager'),
          t: _('Pokud vám vypadne telefon i správce hesel zároveň, jsou kódy jediná cesta zpět. Ztráta obojího znamená ověření totožnosti přes podporu, které trvá dva pracovní dny.', 'If you lose both your phone and your password manager, the codes are the only way back. Losing both means identity verification through support, which takes two business days.') },
        { k: 'h', t: _('Rozdělte přístupy do týmu', 'Split access across your team') },
        { k: 'p', t: _('Sdílené přihlášení je nejčastější příčina incidentu, který nikdo nedokáže vysvětlit. Každý člověk má mít vlastní účet s vlastními právy — v auditním logu je pak vidět, kdo co udělal.', 'A shared login is the most common cause of an incident nobody can explain. Every person should have their own account with their own permissions — the audit log then shows who did what.') },
        { k: 'table', head: [_('Role', 'Role'), _('Co může', 'Can do'), _('Co nemůže', 'Cannot do')], rows: [
          [_('Vlastník', 'Owner'), _('všechno včetně zrušení účtu', 'everything including closing the account'), '—'],
          [_('Administrátor', 'Admin'), _('služby, domény, tým, tikety', 'services, domains, team, tickets'), _('měnit fakturační údaje, zrušit účet', 'change billing details, close the account')],
          [_('Technik', 'Technician'), _('restart, konzole, nasazení, zálohy', 'restart, console, deploys, backups'), _('vidět faktury a ceny', 'see invoices and prices')],
          [_('Fakturace', 'Billing'), _('faktury, platby, dokumenty', 'invoices, payments, documents'), _('sáhnout na servery', 'touch servers')],
          [_('Čtení', 'Read-only'), _('vidět stav a metriky', 'see status and metrics'), _('cokoli změnit', 'change anything')]
        ] },
        { k: 'note', tone: 'tip', title: _('Klíče místo hesel', 'Keys instead of passwords'),
          t: _('SSH klíč nahraný v účtu se propíše do každého nového serveru. Heslo do SSH tím padá úplně a s ním i devadesát procent pokusů o průlom.', 'An SSH key uploaded to your account is copied to every new server. Password SSH login goes away entirely, and with it ninety percent of break-in attempts.') }
      ]
    },
    {
      id: 'prevod', sec: 'start', read: '9 min', updated: _('4. srpna 2026', '4 August 2026'),
      title: _('Převod webu bez odstávky', 'Migrating a site with zero downtime'),
      lead: _('Postup, který používáme my, když web přenášíme za vás. Klíč je nepřepnout DNS dřív, než nová kopie prokazatelně funguje.', 'The procedure we follow when we migrate a site for you. The key is not to switch DNS before the new copy demonstrably works.'),
      blocks: [
        { k: 'p', t: _('Odstávka při převodu nevzniká přenosem dat, ale okamžikem, kdy DNS ukazuje na server, který ještě není hotový. Proto se nejdřív postaví celá kopie, ověří přes hosts, a teprve pak se mění záznam.', 'Downtime during a migration is not caused by copying data but by the moment DNS points at a server that is not ready. So you build the whole copy first, verify it through hosts, and only then change the record.') },
        { k: 'h', t: _('1 — Snižte TTL, den předem', '1 — Lower the TTL, a day ahead') },
        { k: 'p', t: _('Den před převodem nastavte u záznamu A a AAAA TTL na 300 sekund. Návrat zpět je pak otázkou pěti minut, ne osmi hodin.', 'The day before, set the TTL on the A and AAAA records to 300 seconds. Rolling back is then a matter of five minutes, not eight hours.') },
        { k: 'code', lang: 'dns', t: 'www.example.cz.   300  IN  A     185.66.14.21\nexample.cz.       300  IN  A     185.66.14.21' },
        { k: 'h', t: _('2 — Přeneste data a databázi', '2 — Move files and the database') },
        { k: 'code', tabs: [
          { name: 'rsync', t: 'rsync -avz --delete \\\n  --exclude "wp-content/cache" \\\n  ./public_html/ user@web.prg1.onhost.cz:/www/example.cz/' },
          { name: 'mysql', t: 'mysqldump --single-transaction --quick db_old \\\n  | gzip > db.sql.gz\n\nzcat db.sql.gz | mysql -h db.prg1.onhost.cz -u db_new -p db_new' },
          { name: 'git', t: 'ssh user@web.prg1.onhost.cz\ncd /www/example.cz\ngit clone git@github.com:firma/web.git .\ncomposer install --no-dev -o' }
        ] },
        { k: 'note', tone: 'tip', title: _('--single-transaction není kosmetika', '--single-transaction is not cosmetic'),
          t: _('Bez něj dump uzamkne tabulky a e-shop během exportu stojí. S ním se čte konzistentní snímek a objednávky dál chodí.', 'Without it the dump locks tables and your shop stalls during the export. With it you read a consistent snapshot and orders keep coming.') },
        { k: 'h', t: _('3 — Ověřte přes hosts, ne přes DNS', '3 — Verify through hosts, not DNS') },
        { k: 'p', t: _('Do souboru hosts na svém počítači přidejte novou IP a projděte web jako uživatel: přihlášení, košík, platba, odeslání formuláře, administrace. Tohle je jediný krok, který nelze uspěchat.', 'Add the new IP to the hosts file on your machine and walk the site as a user: login, cart, payment, form submit, admin. This is the one step you cannot rush.') },
        { k: 'code', lang: 'hosts', t: '185.66.14.21  example.cz www.example.cz' },
        { k: 'list', items: [
          _('projde odeslání e-mailu z formuláře (a nespadne do spamu)', 'a form e-mail goes out (and does not land in spam)'),
          _('funguje nahrávání souborů — práva a vlastník adresářů', 'file uploads work — directory owner and permissions'),
          _('cron má stejné úlohy jako na starém hostingu', 'cron has the same jobs as the old host'),
          _('platební brána má povolenou novou IP', 'the payment gateway allows the new IP')
        ] },
        { k: 'h', t: _('4 — Přepněte a nechte staré běžet', '4 — Switch, and leave the old one running') },
        { k: 'p', t: _('Po změně záznamu držte starý hosting ještě 72 hodin. Část resolverů TTL ignoruje a vaši zákazníci by jinak dva dny psali do prázdna.', 'After changing the record, keep the old host alive for another 72 hours. Some resolvers ignore TTL and your customers would otherwise be writing into the void for two days.') },
        { k: 'note', tone: 'danger', title: _('Databázi nikdy nepřenášejte dvakrát', 'Never move the database twice'),
          t: _('Objednávky přijaté po dumpu na starém serveru se druhým importem nenávratně přepíšou. Když se přepnutí odkládá, dělá se nový dump — nikdy sloučení.', 'Orders taken on the old server after the dump are irreversibly overwritten by a second import. If the switch slips, take a fresh dump — never merge.') }
      ]
    },

    // ── Webhosting ───────────────────────────────────────────────────────
    {
      id: 'php', sec: 'web', read: '5 min', updated: _('20. srpna 2026', '20 August 2026'),
      title: _('Verze PHP, limity a rozšíření', 'PHP version, limits and extensions'),
      lead: _('Verzi měníte kliknutím a vrátíte stejně rychle. Pozor jen na jedno: web a příkazová řádka mají verzi zvlášť.', 'You change the version with one click and revert just as fast. Watch for one thing: the web and the command line have separate versions.'),
      blocks: [
        { k: 'p', t: _('V panelu vyberte službu → Nastavení → PHP. Přepnutí je okamžité, restart nic nepotřebuje. Předchozí verzi držíme dostupnou, takže návrat je jedno kliknutí.', 'In the panel pick the service → Settings → PHP. The switch is instant and needs no restart. We keep the previous version available, so rolling back is a single click.') },
        { k: 'table', head: [_('Verze', 'Version'), _('Stav', 'State'), _('Podpora do', 'Supported until')], rows: [
          ['8.4', _('doporučená', 'recommended'), _('prosinec 2028', 'December 2028')],
          ['8.3', _('stabilní', 'stable'), _('prosinec 2027', 'December 2027')],
          ['8.2', _('stabilní', 'stable'), _('prosinec 2026', 'December 2026')],
          ['8.1', _('jen bezpečnostní opravy', 'security fixes only'), _('prosinec 2026', 'December 2026')],
          ['7.4', _('na vyžádání, izolovaně', 'on request, isolated'), _('bez podpory', 'unsupported')]
        ] },
        { k: 'note', tone: 'warn', title: _('CLI verze je jiná proměnná', 'The CLI version is a separate setting'),
          t: _('Cron a Composer běží pod PHP-CLI. Když se verze rozejdou, web běží a cron tiše padá — nebo naopak. Nastavte obě stejně, dokud nemáte důvod je rozdělit.', 'Cron and Composer run under PHP-CLI. If the versions diverge, the site runs while cron silently fails — or the other way round. Set both the same until you have a reason not to.') },
        { k: 'h', t: _('Limity, které se opravdu mění', 'Limits that actually matter') },
        { k: 'code', lang: 'ini', t: 'memory_limit = 512M        ; 1024M na vyžádání\nupload_max_filesize = 128M\npost_max_size = 128M       ; nikdy menší než upload_max_filesize\nmax_execution_time = 120   ; web; cron má 3600\nopcache.enable = 1' },
        { k: 'p', t: _('Vlastní hodnoty patří do .user.ini v korenu webu; načte se do minuty. Direktivy typu open_basedir nebo disable_functions jsou zamčené na naší straně — mění je podpora, když popíšete, co potřebujete.', 'Your own values go into .user.ini in the web root; it is picked up within a minute. Directives such as open_basedir or disable_functions are locked on our side — support changes them once you describe what you need.') },
        { k: 'h', t: _('Rozšíření', 'Extensions') },
        { k: 'list', items: [
          _('předinstalováno: mbstring, intl, gd, imagick, bcmath, curl, zip, redis, pdo_mysql, pdo_pgsql, sodium', 'preinstalled: mbstring, intl, gd, imagick, bcmath, curl, zip, redis, pdo_mysql, pdo_pgsql, sodium'),
          _('na vyžádání: ioncube, sqlsrv, ldap, gmp, ssh2', 'on request: ioncube, sqlsrv, ldap, gmp, ssh2'),
          _('nikdy: pcntl, exec bez omezení — na sdíleném hostingu ne, na VPS ano', 'never: unrestricted pcntl or exec — not on shared hosting, yes on a VPS')
        ] },
        { k: 'code', lang: 'bash', t: 'php -v\nphp -m | grep -i redis\nphp -i | grep memory_limit' }
      ]
    },
    {
      id: 'cron', sec: 'web', read: '4 min', updated: _('18. srpna 2026', '18 August 2026'),
      title: _('Cron a naplánované úlohy', 'Cron and scheduled jobs'),
      lead: _('Úloha, o které se nedozvíte, že spadla, je horší než žádná úloha. Proto každý cron u nás loguje a umí zavolat na sebe watchdog.', 'A job you never learn has failed is worse than no job at all. That is why every cron here logs, and can point a watchdog at itself.'),
      blocks: [
        { k: 'p', t: _('Úlohy se nastavují v panelu ve službě → Cron. Minimální interval je jedna minuta, souběžné spuštění stejné úlohy blokujeme — druhý běh se zahodí a zapíše do logu, místo aby ti dva přepisovali stejná data.', 'Jobs live in the panel under the service → Cron. The minimum interval is one minute; we block concurrent runs of the same job — the second run is dropped and logged instead of two processes writing the same data.') },
        { k: 'code', lang: 'cron', t: '# minuta hodina den měsíc den-v-týdnu  příkaz\n*/5   *   *   *   *   php /www/example.cz/bin/console app:sync\n0     3   *   *   *   php /www/example.cz/bin/console app:report\n15    4   *   *   1   /www/example.cz/bin/backup.sh' },
        { k: 'note', tone: 'tip', title: _('Nikdy neplánujte na celou hodinu', 'Never schedule on the hour'),
          t: _('V nule každé hodiny startuje polovina internetu. Posuňte úlohu na 03:17 — dostane víc I/O a doběhne dřív než ta v 03:00.', 'At the top of the hour half the internet starts up. Move the job to 03:17 — it gets more I/O and finishes sooner than the one at 03:00.') },
        { k: 'h', t: _('Výstup a chyby', 'Output and errors') },
        { k: 'p', t: _('Standardní výstup ukládáme 30 dní a je vidět v panelu u každého běhu, včetně návratového kódu a doby trvání. Když chcete vlastní soubor, přesměrujte oba proudy — jinak se chybová hlášení nikde neobjeví.', 'We keep standard output for 30 days, visible in the panel for every run along with the exit code and duration. If you want your own file, redirect both streams — otherwise error messages appear nowhere.') },
        { k: 'code', lang: 'bash', t: 'php bin/console app:sync >> /www/example.cz/log/sync.log 2>&1' },
        { k: 'h', t: _('Když úloha nesmí tiše chybět', 'When the job must not silently vanish') },
        { k: 'p', t: _('Zapněte u úlohy hlídač: nastavíte očekávaný interval a při vynechání dostanete e-mail nebo webhook. Hlídač hlásí i běh, který trvá třikrát déle než obvykle — to bývá první příznak, že databáze roste přes limity.', 'Enable the watchdog on the job: you set the expected interval and get an e-mail or webhook when a run is missed. The watchdog also reports a run that takes three times longer than usual — usually the first sign the database is outgrowing its limits.') },
        { k: 'code', lang: 'bash', t: 'curl -fsS -m 10 --retry 3 \\\n  https://hlidac.onhost.cz/p/6f2a91c4 > /dev/null' }
      ]
    },
    {
      id: 'ssl', sec: 'web', read: '5 min', updated: _('26. srpna 2026', '26 August 2026'),
      title: _('HTTPS, certifikáty a HSTS', 'HTTPS, certificates and HSTS'),
      lead: _('Certifikát vydáváme a obnovujeme sami, zdarma, pro každou domenu i subdoménu. Zajímavá je až ta část, kterou nelze vzít zpět.', 'We issue and renew the certificate ourselves, free, for every domain and subdomain. The interesting part is the one you cannot undo.'),
      blocks: [
        { k: 'p', t: _('Po namíření domény na náš server se certifikát Let\u2019s Encrypt vydá do dvou minut a obnovuje se 30 dní před vypršením. Nemusíte nic dělat ani hlídat.', 'Once the domain points at our server, a Let\u2019s Encrypt certificate is issued within two minutes and renewed 30 days before expiry. You do not have to do or watch anything.') },
        { k: 'h', t: _('Vlastní certifikát', 'Your own certificate') },
        { k: 'steps', items: [
          _('Ve službě → Certifikáty vyberte Nahrát vlastní.', 'Under the service → Certificates pick Upload your own.'),
          _('Vložte certifikát, privátní klíč a celý řetěz mezilehlých certifikátů.', 'Paste the certificate, the private key and the full intermediate chain.'),
          _('Zkontrolujte otisk proti tomu, co vám poslala certifikační autorita.', 'Check the fingerprint against what the certificate authority sent you.'),
          _('Nastavte upozornění 30 dní před vypršením — u vlastního certifikátu neobnovujeme.', 'Set a reminder 30 days before expiry — we do not renew a certificate you supplied.')
        ] },
        { k: 'code', lang: 'bash', t: 'openssl x509 -in cert.pem -noout -dates -subject\nopenssl s_client -connect example.cz:443 -servername example.cz < /dev/null 2>/dev/null \\\n  | openssl x509 -noout -issuer' },
        { k: 'note', tone: 'danger', title: _('HSTS se vypíná rok', 'HSTS takes a year to turn off'),
          t: _('Hlavička HSTS řekne prohlížeči, aby na doménu už nikdy nešel bez HTTPS — a on si to pamatuje po celou dobu max-age. Než ji zapnete, musí mít certifikát každá subdoména, kterou používáte. Začněte na max-age 300 a zvyšujte až po týdnu bez problémů.', 'The HSTS header tells the browser never to reach the domain without HTTPS again — and it remembers for the whole max-age. Before enabling it, every subdomain you use must have a certificate. Start at max-age 300 and raise it only after a clean week.') },
        { k: 'code', lang: 'http', t: 'Strict-Transport-Security: max-age=31536000; includeSubDomains' },
        { k: 'h', t: _('Přesměrování na HTTPS', 'Redirecting to HTTPS') },
        { k: 'p', t: _('Přepínač v panelu vloží trvalé přesměrování 301 na úrovni webserveru — o řád rychlejší než totéž v PHP a nezávislé na aplikaci. Ve WordPressu zároveň přepište adresu webu, jinak se přesměrování zacyklí.', 'The toggle in the panel adds a permanent 301 redirect at the web-server level — an order of magnitude faster than the same thing in PHP and independent of the application. In WordPress also rewrite the site URL, otherwise the redirect loops.') }
      ]
    },

    // ── VPS ──────────────────────────────────────────────────────────────
    {
      id: 'ssh', sec: 'vps', read: '6 min', updated: _('15. srpna 2026', '15 August 2026'),
      title: _('První přihlášení na VPS', 'First sign-in to a VPS'),
      lead: _('Server je hotový do 55 sekund. Prvních deset minut rozhoduje o tom, jestli za měsíc najdete v logu tisíc pokusů o přihlášení, nebo nulu.', 'Your server is ready in 55 seconds. The first ten minutes decide whether in a month you find a thousand login attempts in the log, or none.'),
      blocks: [
        { k: 'code', lang: 'bash', t: 'ssh root@185.66.14.21\n# nebo přes IPv6\nssh root@2a14:7c0:4a00::21' },
        { k: 'p', t: _('Když jste při objednávce nahráli klíč, heslo se vůbec nenastavilo a přihlášení heslem je vypnuté. To je správný stav — nechte ho tak.', 'If you uploaded a key when ordering, no password was ever set and password login is disabled. That is the right state — leave it there.') },
        { k: 'h', t: _('Uživatel místo roota', 'A user instead of root') },
        { k: 'code', lang: 'bash', t: 'adduser petr\nusermod -aG sudo petr\nrsync --archive --chown=petr:petr ~/.ssh /home/petr\n\n# a teprve teď zamknout root\nsed -i \'s/^#*PermitRootLogin.*/PermitRootLogin no/\' /etc/ssh/sshd_config\nsystemctl reload ssh' },
        { k: 'note', tone: 'warn', title: _('Neodhlašujte se, dokud to nevyzkoušíte', 'Do not log out before you test it') },
        { k: 'p', t: _('Otevřete druhé okno terminálu a přihlaste se novým uživatelem. Až když projde sudo, zavřete to první. Jinak vás čeká sériová konzole — funguje, ale je to zbytečné drama.', 'Open a second terminal window and sign in as the new user. Only once sudo works, close the first one. Otherwise the serial console awaits — it works, but it is needless drama.') },
        { k: 'h', t: _('Sériová konzole', 'The serial console') },
        { k: 'p', t: _('V panelu u serveru → Konzole se dostanete na obrazovku mimo síť instance. Funguje, i když jste si zazdili firewall nebo rozbili sshd — je to jediná cesta, která na síti serveru nezávisí.', 'In the panel under the server → Console you reach a screen outside the instance network. It works even if you have walled yourself out with the firewall or broken sshd — it is the one path that does not depend on the server network.') },
        { k: 'table', head: [_('Obraz', 'Image'), _('Uživatel', 'User'), _('Balíčky', 'Packages')], rows: [
          ['Debian 13', 'root', 'apt'], ['Ubuntu 24.04 LTS', 'ubuntu', 'apt'],
          ['Rocky Linux 10', 'rocky', 'dnf'], ['Alpine 3.21', 'root', 'apk'],
          [_('Docker (Debian 13)', 'Docker (Debian 13)'), 'root', 'apt + docker']
        ] }
      ]
    },
    {
      id: 'firewall', sec: 'vps', read: '7 min', updated: _('22. srpna 2026', '22 August 2026'),
      title: _('Firewall a základní ochrana', 'Firewall and basic hardening'),
      lead: _('Náš síťový firewall běží před serverem, takže zahozený paket nikdy nesáhne na váš procesor. Systémový firewall na serveru je druhá vrstva, ne alternativa.', 'Our network firewall runs in front of the server, so a dropped packet never touches your CPU. The system firewall on the server is a second layer, not an alternative.'),
      blocks: [
        { k: 'h', t: _('Síťový firewall v panelu', 'Network firewall in the panel') },
        { k: 'p', t: _('Pravidla se aplikují do pěti sekund a lze je vázat na skupinu serverů, takže nový server ve skupině je chráněný od první sekundy běhu. Výchozí politika je: zahodit vše příchozí kromě toho, co povolíte.', 'Rules apply within five seconds and can be bound to a server group, so a new server in the group is protected from its first second. The default policy is drop everything inbound except what you allow.') },
        { k: 'table', head: [_('Port', 'Port'), _('Otevřít komu', 'Open to'), _('Poznámka', 'Note')], rows: [
          ['22 / SSH', _('jen vaše IP nebo VPN', 'your IP or VPN only'), _('nikdy 0.0.0.0/0', 'never 0.0.0.0/0')],
          ['80, 443', _('všem', 'everyone'), _('web', 'web')],
          ['3306 / 5432', _('jen privátní síť', 'private network only'), _('databáze nepatří na internet', 'a database does not belong on the internet')],
          ['6379 / Redis', _('jen privátní síť', 'private network only'), _('bez hesla je to otevřený trezor', 'with no password it is an open safe')],
          ['ICMP', _('všem', 'everyone'), _('bez ping se hůř hledají chyby', 'without ping, debugging is harder')]
        ] },
        { k: 'h', t: _('Systémový firewall', 'System firewall') },
        { k: 'code', tabs: [
          { name: 'ufw', t: 'ufw default deny incoming\nufw default allow outgoing\nufw allow from 89.24.0.0/16 to any port 22 proto tcp\nufw allow 80,443/tcp\nufw --force enable\nufw status numbered' },
          { name: 'nftables', t: 'table inet filter {\n  chain input {\n    type filter hook input priority 0; policy drop;\n    iif lo accept\n    ct state established,related accept\n    tcp dport { 80, 443 } accept\n    ip saddr 89.24.0.0/16 tcp dport 22 accept\n    icmp type echo-request accept\n  }\n}' }
        ] },
        { k: 'note', tone: 'tip', title: _('Pojistka proti zazdění', 'A lockout safety net'),
          t: _('Než pravidla zapnete, spusťte v druhém okně `sleep 600 && ufw disable`. Když se odříznete, za deset minut je firewall zpátky vypnutý a vy uvnitř.', 'Before enabling the rules, run `sleep 600 && ufw disable` in a second window. If you cut yourself off, in ten minutes the firewall is back off and you are back in.') },
        { k: 'h', t: _('Fail2ban a automatické aktualizace', 'Fail2ban and unattended upgrades') },
        { k: 'code', lang: 'bash', t: 'apt install -y fail2ban unattended-upgrades\nsystemctl enable --now fail2ban\ndpkg-reconfigure -plow unattended-upgrades\nfail2ban-client status sshd' },
        { k: 'p', t: _('Automatické aktualizace zapínejte jen pro bezpečnostní repozitář. Plné nekontrolované updaty na produkci jednou za rok restartují službu v nejhorší možnou chvíli.', 'Enable unattended upgrades for the security repository only. Full unchecked updates on production will, once a year, restart a service at the worst possible moment.') }
      ]
    },
    {
      id: 'zalohy', sec: 'vps', read: '6 min', updated: _('28. srpna 2026', '28 August 2026'),
      title: _('Zálohy, snímky a obnova', 'Backups, snapshots and restore'),
      lead: _('Záloha, kterou jste nikdy neobnovili, není záloha. Tenhle článek je hlavně o tom druhém kroku.', 'A backup you have never restored is not a backup. This article is mostly about the second step.'),
      blocks: [
        { k: 'table', head: [_('Typ', 'Type'), _('Jak často', 'Frequency'), _('Držíme', 'Retention'), _('Obnova', 'Restore')], rows: [
          [_('Snímek disku', 'Disk snapshot'), _('na kliknutí', 'on demand'), _('dokud nesmažete', 'until you delete it'), _('2–4 minuty', '2–4 minutes')],
          [_('Denní záloha', 'Daily backup'), _('každou noc', 'every night'), _('14 dní', '14 days'), _('5–15 minut', '5–15 minutes')],
          [_('Týdenní', 'Weekly'), _('v pondělí', 'on Monday'), _('8 týdnů', '8 weeks'), _('15–40 minut', '15–40 minutes')],
          [_('Offsite kopie', 'Offsite copy'), _('denně, druhá lokalita', 'daily, second site'), _('30 dní', '30 days'), _('do 2 hodin', 'within 2 hours')]
        ] },
        { k: 'note', tone: 'warn', title: _('Snímek není záloha', 'A snapshot is not a backup'),
          t: _('Snímek leží na stejném úložišti jako server. Chrání před vaší chybou, ne před ztrátou pole. Proto máme obojí a proto je offsite kopie v jiném datacentru.', 'A snapshot sits on the same storage as the server. It protects you from your own mistake, not from losing the array. That is why we run both, and why the offsite copy lives in another datacentre.') },
        { k: 'h', t: _('Obnova jednoho souboru', 'Restoring a single file') },
        { k: 'p', t: _('Nejčastější případ není zničený server, ale smazaný soubor. V panelu ve službě → Zálohy zálohu procházíte jako adresář a obnovíte jen to, co potřebujete, do vedlejšího umístění — ne přes živá data.', 'The common case is not a destroyed server but a deleted file. In the panel under the service → Backups you browse the backup like a directory and restore just what you need, to a side location — not over live data.') },
        { k: 'h', t: _('Obnova celého serveru', 'Restoring a whole server') },
        { k: 'steps', items: [
          _('Vyberte bod obnovy a nechte ho postavit jako nový server, ne přes stávající.', 'Pick a restore point and have it built as a new server, not over the existing one.'),
          _('Ověřte data na nové instanci, dokud stará ještě žije.', 'Verify the data on the new instance while the old one is still alive.'),
          _('Přesuňte plovoucí IP — přepnutí je v jedné operaci a bez změny DNS.', 'Move the floating IP — the switch is one operation and needs no DNS change.'),
          _('Starou instanci držte 48 hodin, pak smažte.', 'Keep the old instance for 48 hours, then delete it.')
        ] },
        { k: 'code', lang: 'bash', t: 'onhost backups list --service vps-prg1-8842\nonhost backups restore --id bk_2f81c9 --as-new-server \\\n  --name vps-obnova --wait' },
        { k: 'note', tone: 'tip', title: _('Zkoušku obnovy jednou za čtvrtletí', 'Test a restore once a quarter'),
          t: _('Napište si do kalendáře patnáct minut a obnovte zálohu do testovacího serveru. Buď zjistíte, že to funguje, nebo to zjistíte teď — a ne v den, kdy na tom závisí firma.', 'Put fifteen minutes in your calendar and restore a backup to a test server. Either you learn it works, or you learn it now — not on the day the company depends on it.') }
      ]
    },

    // ── Herní servery ────────────────────────────────────────────────────
    {
      id: 'mc', sec: 'game', read: '7 min', updated: _('24. srpna 2026', '24 August 2026'),
      title: _('Minecraft server od nuly', 'A Minecraft server from scratch'),
      lead: _('Od objednávky k prvnímu hráči za dvě minuty. Zbytek článku je o tom, aby server běžel i s třiceti lidmi.', 'From order to first player in two minutes. The rest of this article is about keeping it smooth with thirty people online.'),
      blocks: [
        { k: 'steps', items: [
          _('Vyberte jádro: Paper pro pluginy, Fabric pro mody, Vanilla pro čistou hru.', 'Pick the core: Paper for plugins, Fabric for mods, Vanilla for pure play.'),
          _('Nastavte verzi hry — jádro i pluginy se musí shodovat na hlavní verzi.', 'Set the game version — core and plugins must agree on the major version.'),
          _('Přidejte doménu, kterou hráči zadají do klienta (hra.example.cz).', 'Add the domain players will type into their client (play.example.cz).'),
          _('Spusťte a sledujte konzoli. První start staví svět, trvá 30–90 sekund.', 'Start it and watch the console. The first boot generates the world, 30–90 seconds.')
        ] },
        { k: 'h', t: _('Paměť a počet hráčů', 'Memory and player count') },
        { k: 'table', head: [_('Hráčů', 'Players'), 'RAM', _('vCPU', 'vCPU'), _('Poznámka', 'Note')], rows: [
          ['1–10', '4 GB', '2', _('vanilla nebo pár pluginů', 'vanilla or a few plugins')],
          ['10–30', '8 GB', '4', _('Paper, do 25 pluginů', 'Paper, up to 25 plugins')],
          ['30–60', '12 GB', '6', _('nutná pre-generace světa', 'world pre-generation required')],
          [_('modpack, 10–20', 'modpack, 10–20'), '16 GB', '8', _('Fabric/Forge, hlídat chunky', 'Fabric/Forge, watch chunk load')]
        ] },
        { k: 'note', tone: 'warn', title: _('Víc jader nepomůže tak, jak čekáte', 'More cores help less than you expect'),
          t: _('Hlavní vlákno hry je jednovláknové. Server se 32 jádry nebude mít lepší TPS než ten s osmi — rozhoduje takt jednoho jádra a rychlost disku. Proto máme herní stroje na vysokém taktu, ne na počtu jader.', 'The game main loop is single-threaded. A 32-core server will not have better TPS than an eight-core one — single-core clock and disk speed decide. That is why our game machines run high clock, not high core count.') },
        { k: 'h', t: _('Co nastavit v server.properties', 'What to set in server.properties') },
        { k: 'code', lang: 'properties', t: 'view-distance=8            # 10 stojí o 40 % víc výkonu\nsimulation-distance=6\nmax-players=40\nnetwork-compression-threshold=256\nenable-command-block=false\nonline-mode=true           # vypnutím zvete cheatery' },
        { k: 'h', t: _('Konzole a RCON', 'Console and RCON') },
        { k: 'p', t: _('Konzole v panelu je plnohodnotná: vidíte výstup serveru v reálném času a můžete posílat příkazy. Řádky, které do konzole napsal náš inženýr, jsou označené jménem a důvodem — na cizí zásah do vašeho serveru máte právo vědět.', 'The console in the panel is the real thing: you see server output live and can send commands. Lines typed by our engineer are labelled with their name and reason — you have a right to know about outside intervention on your server.') },
        { k: 'code', lang: 'console', t: 'whitelist add petr\nop petr\nsave-all\nstop        # nikdy kill; kill neuloží svět' },
        { k: 'note', tone: 'danger', title: _('Server vypínejte příkazem stop', 'Stop the server with the stop command'),
          t: _('Vynucené ukončení nechá svět v polovině zápisu. Chunky se poškodí a ztratíte to, co hráči postavili od posledního uložení.', 'A forced kill leaves the world half-written. Chunks corrupt and you lose whatever players built since the last save.') }
      ]
    },
    {
      id: 'pluginy', sec: 'game', read: '5 min', updated: _('19. srpna 2026', '19 August 2026'),
      title: _('Pluginy, mody a whitelist', 'Plugins, mods and the whitelist'),
      lead: _('Devět z deseti výkonových problémů herního serveru je jeden konkrétní plugin. Tady je postup, jak ho najít za deset minut.', 'Nine out of ten game-server performance problems are one specific plugin. Here is how to find it in ten minutes.'),
      blocks: [
        { k: 'p', t: _('Pluginy nahrajete přes správce souborů do adresáře plugins nebo je najdete v katalogu v panelu, kde se nainstalují ve verzi odpovídající vašemu jádru. Po nahrání restartujte — reload u většiny pluginů nechává v paměti nepořádek.', 'Upload plugins through the file manager into the plugins directory, or find them in the panel catalogue, which installs the build matching your core. Restart after uploading — reload leaves most plugins in a messy state.') },
        { k: 'h', t: _('Hledání viníka', 'Finding the culprit') },
        { k: 'steps', items: [
          _('Nainstalujte Spark a spusťte profiler na 5 minut při běžném provozu.', 'Install Spark and run the profiler for 5 minutes under normal load.'),
          _('V reportu hledejte nejtěžší volání — obvykle je to jeden plugin nebo jeden úkol na hlavním vlákně.', 'In the report look for the heaviest call — usually one plugin or one task on the main thread.'),
          _('Vypněte ho a porovnejte TPS ve stejném čase následujícího dne, ne o dvě minuty později.', 'Disable it and compare TPS at the same time next day, not two minutes later.'),
          _('Když viník není zjevný, půlte: vypněte polovinu pluginů a opakujte.', 'If the culprit is not obvious, bisect: disable half the plugins and repeat.')
        ] },
        { k: 'table', head: [_('Symptom', 'Symptom'), _('Obvyklá příčina', 'Usual cause')], rows: [
          [_('TPS padá s počtem hráčů', 'TPS drops with player count'), _('view-distance, entity limity', 'view-distance, entity limits')],
          [_('Krátké záseky každých pár minut', 'Short freezes every few minutes'), _('automatické ukládání nebo cron pluginu', 'auto-save or a plugin cron')],
          [_('Ping vysoký, TPS 20', 'High ping, TPS 20'), _('síť nebo klient, ne server', 'network or client, not the server')],
          [_('Pád po pár hodinách', 'Crash after a few hours'), _('únik paměti v pluginu, málo RAM', 'plugin memory leak, too little RAM')]
        ] },
        { k: 'h', t: _('Whitelist a moderace', 'Whitelist and moderation') },
        { k: 'code', lang: 'console', t: 'whitelist on\nwhitelist add petr\nban-ip 203.0.113.9\npardon petr' },
        { k: 'note', tone: 'tip', title: _('Whitelist zapněte hned při stavbě', 'Turn the whitelist on while building'),
          t: _('Server bez whitelistu najdou skenery do hodiny od prvního startu. Vypnout ho můžete ve chvíli, kdy máte pravidla, moderátory a zálohu světa.', 'Scanners find a server with no whitelist within an hour of first boot. Turn it off once you have rules, moderators and a world backup.') }
      ]
    },

    // ── API ──────────────────────────────────────────────────────────────
    {
      id: 'api-start', sec: 'api', read: '6 min', updated: _('27. srpna 2026', '27 August 2026'),
      title: _('Autentizace a první požadavek', 'Authentication and your first request'),
      lead: _('Všechno, co jde udělat v panelu, jde udělat přes API. Panel sám žádné jiné rozhraní nemá — mluví se stejným API jako vy.', 'Everything you can do in the panel you can do over the API. The panel has no other interface — it speaks the same API you do.'),
      blocks: [
        { k: 'p', t: _('Token vytvoříte v Účet → API klíče. Vždy mu dejte jen ta práva, která opravdu potřebuje, a omezte ho na IP, ze které voláte. Token vidíte jednou; ztracený se neobnovuje, jen zneplatní a vydá nový.', 'Create a token under Account → API keys. Give it only the scopes it actually needs and restrict it to the IP you call from. You see the token once; a lost one is not recovered, only revoked and reissued.') },
        { k: 'code', tabs: [
          { name: 'curl', t: 'curl -s https://api.onhost.cz/v1/services \\\n  -H "Authorization: Bearer $ONHOST_TOKEN" \\\n  -H "Accept: application/json"' },
          { name: 'php', t: '$ch = curl_init(\'https://api.onhost.cz/v1/services\');\ncurl_setopt_array($ch, [\n  CURLOPT_RETURNTRANSFER => true,\n  CURLOPT_HTTPHEADER => [\n    \'Authorization: Bearer \' . getenv(\'ONHOST_TOKEN\'),\n    \'Accept: application/json\',\n  ],\n]);\n$services = json_decode(curl_exec($ch), true);' },
          { name: 'node', t: 'const res = await fetch(\'https://api.onhost.cz/v1/services\', {\n  headers: {\n    Authorization: `Bearer ${process.env.ONHOST_TOKEN}`,\n    Accept: \'application/json\'\n  }\n});\nconst { data } = await res.json();' },
          { name: 'cli', t: 'onhost auth login --token-stdin < token.txt\nonhost services list --output json' }
        ] },
        { k: 'h', t: _('Tvar odpovědi', 'Response shape') },
        { k: 'code', lang: 'json', t: '{\n  "data": [\n    {\n      "id": "vps-prg1-8842",\n      "kind": "vps",\n      "name": "app-prod",\n      "state": "running",\n      "region": "prg1",\n      "created_at": "2026-03-14T09:12:05+01:00"\n    }\n  ],\n  "meta": { "page": 1, "per_page": 25, "total": 12 }\n}' },
        { k: 'p', t: _('Kolekce jsou vždy pod data a stránkované, jedna položka je pod data jako objekt. Neznámá pole ignorujte — přidáváme je bez zvýšení verze. Odebrání pole nebo změna významu znamená nové v2 a starou verzi držíme 12 měsíců.', 'Collections are always under data and paginated; a single item is under data as an object. Ignore unknown fields — we add them without a version bump. Removing a field or changing its meaning means a new v2, and we keep the old version for 12 months.') },
        { k: 'note', tone: 'warn', title: _('Zápisy posílejte s idempotency klíčem', 'Send an idempotency key with writes'),
          t: _('Když se odpověď ztratí v síti, opakování bez klíče vytvoří druhý server. S klíčem dostanete tu samou odpověď jako poprvé.', 'If the response is lost in transit, a retry without the key creates a second server. With the key you get the same response as the first time.') },
        { k: 'code', lang: 'bash', t: 'curl -X POST https://api.onhost.cz/v1/services \\\n  -H "Authorization: Bearer $ONHOST_TOKEN" \\\n  -H "Idempotency-Key: 6f2a91c4-7d1e-4c33-9b2f-0a5e" \\\n  -d \'{"kind":"vps","plan":"vps-4-8","region":"prg1","name":"app-2"}\'' }
      ]
    },
    {
      id: 'api-limity', sec: 'api', read: '5 min', updated: _('27. srpna 2026', '27 August 2026'),
      title: _('Chyby, limity a webhooky', 'Errors, limits and webhooks'),
      lead: _('Rozhraní, které při zátěži tiše mlčí, je horší než to, které řekne, kdy to zkusit znovu. Proto posíláme limity v každé odpovědi.', 'An interface that goes quiet under load is worse than one that tells you when to retry. That is why we send limits with every response.'),
      blocks: [
        { k: 'h', t: _('Chybová odpověď', 'Error response') },
        { k: 'code', lang: 'json', t: '{\n  "error": {\n    "code": "plan_not_available_in_region",\n    "message": "Plán vps-16-64 není v prg2 dostupný.",\n    "field": "plan",\n    "request_id": "req_8f21ca",\n    "docs": "https://docs.onhost.cz/api/errors#plan_not_available"\n  }\n}' },
        { k: 'p', t: _('Vždy uvádějte request_id, když se ptáte podpory — najdeme přesně váš požadavek včetně toho, co dělal backend. Text zprávy neparsujte, ten se mění; parsujte code.', 'Always quote the request_id when asking support — we find your exact request including what the backend did. Do not parse the message text, it changes; parse the code.') },
        { k: 'table', head: ['HTTP', _('Kdy', 'When'), _('Co udělat', 'What to do')], rows: [
          ['400', _('chybí nebo je špatné pole', 'missing or invalid field'), _('opravit požadavek, neopakovat', 'fix the request, do not retry')],
          ['401', _('token chybí nebo propadl', 'token missing or expired'), _('vydat nový', 'issue a new one')],
          ['403', _('token nemá právo', 'token lacks the scope'), _('doplnit oprávnění', 'add the scope')],
          ['409', _('konflikt stavu služby', 'service state conflict'), _('počkat na dokončení úlohy', 'wait for the running task')],
          ['422', _('platný tvar, neproveditelné', 'valid shape, not doable'), _('přečíst code', 'read the code')],
          ['429', _('vyčerpaný limit', 'rate limit reached'), _('čekat podle Retry-After', 'wait per Retry-After')],
          ['5xx', _('naše chyba', 'our fault'), _('opakovat s odstupem, pak tiket', 'retry with backoff, then a ticket')]
        ] },
        { k: 'h', t: _('Limity', 'Rate limits') },
        { k: 'code', lang: 'http', t: 'X-RateLimit-Limit: 600\nX-RateLimit-Remaining: 587\nX-RateLimit-Reset: 41\nRetry-After: 41' },
        { k: 'p', t: _('Základ je 600 požadavků za minutu na token, u zápisů 60. Když potřebujete víc, napište nám — limit zvedáme, není to obchodní páka.', 'The baseline is 600 requests per minute per token, 60 for writes. If you need more, tell us — we raise it, it is not a commercial lever.') },
        { k: 'h', t: _('Webhooky místo dotazování', 'Webhooks instead of polling') },
        { k: 'p', t: _('Dlouhé úlohy (vytvoření serveru, obnova, migrace) nedokončí požadavek. Místo dotazování každou sekundu si nechte poslat webhook. Podpis ověřte, jinak vám kdokoli může tvrdit, že server běží.', 'Long tasks (creating a server, restoring, migrating) do not finish within the request. Instead of polling every second, have a webhook sent. Verify the signature, otherwise anyone can claim your server is up.') },
        { k: 'code', lang: 'php', t: '$sig = $_SERVER[\'HTTP_X_ONHOST_SIGNATURE\'] ?? \'\';\n$body = file_get_contents(\'php://input\');\n$calc = hash_hmac(\'sha256\', $body, getenv(\'ONHOST_WEBHOOK_SECRET\'));\n\nif (!hash_equals($calc, $sig)) {\n    http_response_code(400);\n    exit;\n}\n\n$event = json_decode($body, true);   // service.ready, backup.failed, invoice.paid' },
        { k: 'note', tone: 'tip', title: _('Odpovězte 200 hned', 'Return 200 immediately'),
          t: _('Zpracování dejte do fronty a odpovězte do dvou sekund. Jinak vám při každé události pošleme čtyři opakování a váš server se zahltí sám sebou.', 'Queue the processing and answer within two seconds. Otherwise every event brings four retries and your server floods itself.') }
      ]
    }
  ];
}
