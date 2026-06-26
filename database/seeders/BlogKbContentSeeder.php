<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BlogPost;
use App\Models\KbArticle;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds starter blog posts and knowledge-base articles.
 * Idempotent — skips if content already exists.
 */
class BlogKbContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedKb();
        $this->seedBlog();
    }

    private function seedKb(): void
    {
        $articles = [
            [
                'title'        => 'Jak nastavit DNS záznamy pro vaši doménu',
                'slug'         => 'jak-nastavit-dns-zaznamy',
                'category'     => 'DNS a domény',
                'sort_order'   => 1,
                'excerpt'      => 'Průvodce nastavením A, CNAME, MX a TXT záznamů krok za krokem.',
                'body'         => '<h2>Základní DNS záznamy</h2>
<p>DNS (Domain Name System) překládá doménová jména na IP adresy. Pro správnou funkci webu a e-mailu potřebujete nastavit tyto záznamy:</p>
<h3>A záznam</h3>
<p>Propojuje doménu s IP adresou serveru. Příklad:</p>
<pre>onhost.cz.  IN  A  185.1.2.3</pre>
<h3>CNAME záznam</h3>
<p>Alias — přesměrovává jednu doménu na jinou. Typicky se používá pro <code>www</code>:</p>
<pre>www.onhost.cz.  IN  CNAME  onhost.cz.</pre>
<h3>MX záznamy</h3>
<p>Definují poštovní server pro přijímání e-mailů. Mají prioritu (nižší číslo = vyšší priorita):</p>
<pre>onhost.cz.  IN  MX  10  mail.onhost.cz.</pre>
<h3>TXT záznamy</h3>
<p>Slouží pro SPF, DKIM a DMARC ověření e-mailů, a také pro ověření vlastnictví domény (Google Search Console, apod.).</p>
<h2>Kde změnit DNS?</h2>
<p>DNS záznamy upravujete u svého registrátora domén (např. WEDOS) nebo v zákaznické zóně Onhost.cz v sekci <strong>Domény → Záznamy DNS</strong>.</p>
<p><strong>Pozor:</strong> Změna DNS se projeví za 15–60 minut (TTL), ale může trvat až 24 hodin.</p>',
            ],
            [
                'title'        => 'Jak aktivovat SSL certifikát na hostingu',
                'slug'         => 'jak-aktivovat-ssl-certifikat',
                'category'     => 'SSL a bezpečnost',
                'sort_order'   => 1,
                'excerpt'      => 'Let\'s Encrypt SSL certifikát je v ceně hostingu a aktivuje se automaticky.',
                'body'         => '<h2>SSL certifikát na Onhost.cz</h2>
<p>Každý webhosting zahrnuje bezplatný Let\'s Encrypt SSL certifikát, který se aktivuje automaticky po nastavení DNS na náš server.</p>
<h3>Automatická aktivace</h3>
<ol>
<li>Nastavte A záznam domény na IP adresu vašeho hostingového serveru.</li>
<li>Počkejte na propagaci DNS (15–60 minut).</li>
<li>SSL certifikát se aktivuje automaticky do hodiny.</li>
</ol>
<h3>Přesměrování HTTP → HTTPS</h3>
<p>Pro automatické přesměrování přidejte do souboru <code>.htaccess</code> ve složce <code>public_html</code>:</p>
<pre>RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]</pre>
<h3>Obnovení certifikátu</h3>
<p>Let\'s Encrypt certifikát platí 90 dní a obnovuje se automaticky. Nemusíte nic dělat.</p>
<h3>Prémiový SSL</h3>
<p>Pokud potřebujete rozšířenou validaci (OV/EV) nebo wildcard certifikát, kontaktujte nás na <a href="/kontakt">kontaktním formuláři</a>.</p>',
            ],
            [
                'title'        => 'Jak nahrát web přes FTP nebo SFTP',
                'slug'         => 'jak-nahrat-web-pres-ftp-sftp',
                'category'     => 'Správa souborů',
                'sort_order'   => 1,
                'excerpt'      => 'Přístupové údaje FTP/SFTP najdete v zákaznickém panelu ve správě služby.',
                'body'         => '<h2>Nahrávání souborů přes FTP/SFTP</h2>
<p>K nahrání souborů na hosting potřebujete FTP nebo SFTP klienta. Doporučujeme <strong>FileZilla</strong> (zdarma, Windows/Mac/Linux).</p>
<h3>Kde najdu přihlašovací údaje?</h3>
<p>Přihlašovací údaje FTP/SFTP najdete v zákaznickém panelu:</p>
<ol>
<li>Přihlaste se do <a href="/panel">zákaznické zóny</a>.</li>
<li>Přejděte do sekce <strong>Moje služby</strong>.</li>
<li>Klikněte na název vaší služby.</li>
<li>V sekci <strong>Přístupové údaje</strong> najdete FTP host, uživatelské jméno a heslo.</li>
</ol>
<h3>Nastavení FileZilla</h3>
<table>
<tr><td>Host:</td><td>ftp.onhost.cz (nebo IP serveru)</td></tr>
<tr><td>Uživatel:</td><td>váš FTP login</td></tr>
<tr><td>Heslo:</td><td>vaše FTP heslo</td></tr>
<tr><td>Port:</td><td>21 (FTP) nebo 22 (SFTP)</td></tr>
</table>
<h3>Kam nahrát soubory?</h3>
<p>Webové soubory patří do složky <code>public_html</code> nebo <code>httpdocs</code>. Soubor <code>index.php</code> nebo <code>index.html</code> bude zobrazován jako hlavní stránka.</p>',
            ],
            [
                'title'        => 'Jak změnit verzi PHP na hostingu',
                'slug'         => 'jak-zmenit-verzi-php',
                'category'     => 'Webhosting',
                'sort_order'   => 1,
                'excerpt'      => 'Verzi PHP lze změnit v zákaznickém panelu bez restartu serveru.',
                'body'         => '<h2>Změna verze PHP</h2>
<p>Všechny plány Onhost.cz podporují PHP 7.4, 8.0, 8.1, 8.2 a 8.3. Výchozí verze je PHP 8.3.</p>
<h3>Jak změnit verzi?</h3>
<ol>
<li>Přihlaste se do zákaznické zóny.</li>
<li>Přejděte do sekce <strong>Moje služby → název služby</strong>.</li>
<li>V záložce <strong>Nastavení PHP</strong> vyberte požadovanou verzi.</li>
<li>Změna se projeví okamžitě bez výpadku.</li>
</ol>
<h3>Zjistit aktuální verzi PHP</h3>
<p>Vytvořte soubor <code>phpinfo.php</code> v <code>public_html</code> s obsahem:</p>
<pre>&lt;?php phpinfo(); ?&gt;</pre>
<p>Po otevření v prohlížeči (https://vasedomena.cz/phpinfo.php) uvidíte detailní informace o PHP konfiguraci. <strong>Soubor po ověření smažte!</strong></p>
<h3>Doporučená verze</h3>
<p>Pro nové projekty doporučujeme PHP 8.3. WordPress 6.x vyžaduje minimálně PHP 7.4, ideálně 8.1+.</p>',
            ],
            [
                'title'        => 'Jak vytvořit e-mailovou schránku',
                'slug'         => 'jak-vytvorit-emailovou-schranku',
                'category'     => 'E-mail',
                'sort_order'   => 1,
                'excerpt'      => 'Vytvořte e-mailovou schránku na vlastní doméně za pár sekund.',
                'body'         => '<h2>Vytvoření e-mailové schránky</h2>
<p>S hostingem Onhost.cz si snadno vytvoříte schránky jako info@vasefirma.cz, podpora@vasefirma.cz atd.</p>
<h3>Postup vytvoření schránky</h3>
<ol>
<li>Přihlaste se do zákaznické zóny.</li>
<li>Přejděte na <strong>Moje služby → vyberte hosting</strong>.</li>
<li>Klikněte na <strong>E-mailové schránky → Přidat schránku</strong>.</li>
<li>Zadejte název schránky, doménu a heslo.</li>
<li>Klikněte na <strong>Vytvořit</strong>.</li>
</ol>
<h3>Přístupy k e-mailu</h3>
<ul>
<li><strong>Webmail:</strong> https://mail.vasedomena.cz (přístupný odkudkoli)</li>
<li><strong>IMAP:</strong> imap.vasedomena.cz, port 993 (SSL)</li>
<li><strong>POP3:</strong> pop.vasedomena.cz, port 995 (SSL)</li>
<li><strong>SMTP odesílání:</strong> smtp.vasedomena.cz, port 587 (STARTTLS)</li>
</ul>
<h3>MX záznamy pro příjem</h3>
<p>Příjem e-mailů funguje automaticky, pokud MX záznamy domény ukazují na naše servery. Podrobnosti viz článek <a href="/znalostni-baze/jak-nastavit-dns-zaznamy">Jak nastavit DNS záznamy</a>.</p>',
            ],
        ];

        foreach ($articles as $article) {
            KbArticle::firstOrCreate(
                ['slug' => $article['slug']],
                array_merge($article, ['is_published' => true]),
            );
        }
    }

    private function seedBlog(): void
    {
        $adminId = User::where('email', 'admin@onhost.local')->value('id');

        $posts = [
            [
                'title'        => 'Jak vybrat správný hosting pro firemní web',
                'slug'         => 'jak-vybrat-hosting-pro-firemni-web',
                'category'     => 'Průvodce',
                'excerpt'      => 'Sdílený hosting, VPS nebo dedikovaný server? Pomůžeme vám vybrat správnou volbu pro váš projekt.',
                'body'         => '<p>Výběr správného hostingu je klíčové rozhodnutí pro každý firemní web. Špatná volba může znamenat pomalé načítání, výpadky nebo zbytečně vysoké náklady.</p>
<h2>Sdílený webhosting — pro většinu firem</h2>
<p>Sdílený hosting je ideální volbou pro firemní weby, e-shopy do 10 000 návštěvníků měsíčně a WordPress stránky. Je cenově dostupný (od 49 Kč/měsíc) a správa serveru je plně v naší režii.</p>
<p><strong>Kdy zvolit webhosting:</strong></p>
<ul>
<li>Prezentační web nebo firemní blog</li>
<li>Menší e-shop (WooCommerce, Shoptet)</li>
<li>WordPress, Joomla nebo jiný CMS</li>
<li>Potřebujete e-mailové schránky na vlastní doméně</li>
</ul>
<h2>VPS server — pro náročnější projekty</h2>
<p>VPS (Virtual Private Server) je virtualizovaný server s vyhrazenými zdroji. Máte root přístup a plnou kontrolu nad konfiguracím.</p>
<p><strong>Kdy zvolit VPS:</strong></p>
<ul>
<li>Potřebujete specifické verze softwaru</li>
<li>Provozujete vlastní aplikaci nebo API</li>
<li>Sdílený hosting začíná být pomalý</li>
<li>Potřebujete izolaci od ostatních zákazníků</li>
</ul>
<h2>Jak se rozhodnout?</h2>
<p>Pokud si nejste jisti, začněte s webhosting plánem Business — pokryje drtivou většinu projektů. Kdykoli lze přejít na VPS bez ztráty dat.</p>
<p>Máte konkrétní projekt? <a href="/kontakt">Napište nám</a> a doporučíme nejvhodnější řešení.</p>',
                'is_published' => true,
                'published_at' => now()->subDays(7),
            ],
            [
                'title'        => 'Proč je rychlost hostingu klíčová pro SEO a konverze',
                'slug'         => 'proc-je-rychlost-hostingu-dulezita-pro-seo',
                'category'     => 'SEO a výkon',
                'excerpt'      => 'Každá sekunda prodlení snižuje konverzní poměr o 7 %. Jak vybrat hosting, který váš web nezbrzdí?',
                'body'         => '<p>Google od roku 2021 oficiálně zahrnuje rychlost stránky (Core Web Vitals) do hodnocení ve výsledcích vyhledávání. Pomalý hosting vás stojí pozice i zákazníky.</p>
<h2>Jak rychlost hostingu ovlivňuje SEO</h2>
<p>Google měří <strong>TTFB (Time to First Byte)</strong> — dobu, za jakou server odpoví na první požadavek. Ideál je pod 200 ms. Na sdíleném hostingu s přetíženými servery bývá TTFB 500–2 000 ms, což Google penalizuje.</p>
<h2>NVMe SSD vs. klasický HDD</h2>
<p>Všechny servery Onhost.cz používají NVMe SSD úložiště, které je až 10× rychlejší než tradiční HDD a 3–5× rychlejší než standardní SATA SSD. Načítání databázových dotazů je tak výrazně svižnější.</p>
<h2>České datacentrum = nízká latence</h2>
<p>Pokud váš cílový trh je ČR a SR, hraje roli fyzická vzdálenost serveru. Hosting v českém datacentru zajistí ping pod 10 ms, zatímco zahraniční server může mít latenci 30–80 ms.</p>
<h2>Tipy pro rychlý web</h2>
<ul>
<li>Používejte cache (W3 Total Cache, LiteSpeed Cache pro WordPress)</li>
<li>Optimalizujte obrázky (WebP formát, lazy loading)</li>
<li>Aktivujte PHP OPcache</li>
<li>Minimalizujte CSS/JS soubory</li>
<li>Používejte CDN pro statické soubory</li>
</ul>
<p>Potřebujete pomoc s optimalizací webu? Podívejte se na naše plány <a href="/managed-hosting">Managed hostingu</a>.</p>',
                'is_published' => true,
                'published_at' => now()->subDays(2),
            ],
        ];

        foreach ($posts as $post) {
            BlogPost::firstOrCreate(
                ['slug' => $post['slug']],
                array_merge($post, ['author_id' => $adminId]),
            );
        }
    }
}
