<?php

declare(strict_types=1);

namespace App\Domains\Ai\Services;

use App\Domains\Ai\Contracts\AiProviderInterface;
use App\Domains\Ai\Models\ChatAnswer;
use App\Domains\Ai\Providers\ClaudeProvider;
use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Integrations\Models\IntegrationSetting;
use App\Domains\Customer\Models\Customer;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Shared\Support\MoneyFormatter;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Deterministic, intent-routing chatbot behind the floating panel widget.
 *
 * Unlike the old widget (which always drafted a support reply and so
 * repeated itself), this classifies the customer's message into a hosting
 * topic, answers it with real, technical content, grounds the answer in the
 * customer's own data where useful, and always offers a categorised set of
 * follow-up questions so the customer can narrow things down.
 *
 * It works fully offline (no API key needed). When a real provider is
 * enabled it is used to phrase the free-text answer, but the routing,
 * grounding, quick-replies and deep links are produced here either way.
 *
 * @phpstan-type Suggestion array{label: string, message: string}
 * @phpstan-type Link array{label: string, url: string}
 * @phpstan-type Reply array{reply: string, suggestions: list<Suggestion>, links: list<Link>, category: string|null}
 */
final class AiChatbotService
{
    /** Top-level categories offered as the opening menu. */
    public const CATEGORIES = [
        ['key' => 'objednavka', 'label' => 'Objednávka a tarify', 'icon' => 'shopping-bag'],
        ['key' => 'fakturace',  'label' => 'Fakturace a platby',  'icon' => 'file-text'],
        ['key' => 'domeny',     'label' => 'Domény a DNS',        'icon' => 'globe'],
        ['key' => 'technicka',  'label' => 'Technická podpora',   'icon' => 'server'],
        ['key' => 'ucet',       'label' => 'Účet a zabezpečení',  'icon' => 'shield'],
        ['key' => 'podpora',    'label' => 'Spojit s podporou',   'icon' => 'life-buoy'],
    ];

    public function __construct(private readonly CreditLedger $ledger) {}

    /**
     * @return Reply
     */
    public function reply(User $user, string $message, ?string $category = null): array
    {
        $customer = $user->customer;
        $message  = trim($message);

        // Quick-reply chips send "kategorie:<key>" — treat as a category pick.
        if (str_starts_with($message, 'kategorie:')) {
            $category = substr($message, strlen('kategorie:'));
            $message  = '';
        }

        // A category chip with no free text → show that category's overview.
        if ($category !== null && $this->isCategory($category) && $message === '') {
            return $this->category($category, $customer);
        }

        // Greeting / empty → the opening menu.
        if ($message === '' || $this->looksLikeGreeting($message)) {
            return $this->menu();
        }

        // Match the message against the FAQ knowledge base.
        $entry = $this->bestMatch($message);

        if ($entry === null) {
            // No confident match — route to the most likely category, or the menu.
            $cat = $category !== null && $this->isCategory($category)
                ? $category
                : $this->guessCategory($message);

            $result = $cat !== null
                ? $this->category($cat, $customer)
                : $this->fallback();
        } else {
            $result = $this->answer($entry, $customer);
        }

        // Optionally let a real AI provider phrase the answer (keeping the KB
        // routing, quick-replies and deep links). Off unless a provider is
        // enabled + configured — deterministic KB otherwise.
        return $this->maybePhraseWithProvider($message, $result);
    }

    /**
     * @param  Reply  $result
     * @return Reply
     */
    private function maybePhraseWithProvider(string $message, array $result): array
    {
        $provider = $this->realProvider();

        if ($provider === null) {
            return $result;
        }

        try {
            $response = $provider->chat($message, [
                'topic'   => $result['category'] ?? 'general',
                'kb_hint' => $result['reply'],
            ]);

            $content = trim($response->content);

            if ($content !== '') {
                $result['reply'] = $content;
            }
        } catch (\Throwable $e) {
            report($e); // fall back to the deterministic KB answer
        }

        return $result;
    }

    private function realProvider(): ?AiProviderInterface
    {
        if (! config('ai.allow_real_calls', false)) {
            return null;
        }

        $integration = IntegrationSetting::where('provider', 'claude')
            ->where('is_active', true)
            ->first();

        return ClaudeProvider::fromIntegration($integration);
    }

    // ── Opening menu ────────────────────────────────────────────────────────────

    /** @return Reply */
    private function menu(): array
    {
        $suggestions = array_map(
            static fn (array $c): array => ['label' => $c['label'], 'message' => 'kategorie:' . $c['key']],
            self::CATEGORIES,
        );

        return [
            'reply' => "Dobrý den! Jsem AI asistent OnHost. Poradím s objednávkami, fakturací, doménami, "
                . "DNS i technikou. Vyberte téma níže, nebo mi rovnou napište svůj dotaz.",
            'suggestions' => $suggestions,
            'links'       => [],
            'category'    => null,
        ];
    }

    /** @return Reply */
    private function fallback(): array
    {
        return [
            'reply' => "Nejsem si jistý, jak přesně poradit. Zkuste to prosím upřesnit, vyberte téma níže, "
                . "nebo se spojte s naší podporou.",
            'suggestions' => [
                ['label' => 'Objednávka a tarify', 'message' => 'kategorie:objednavka'],
                ['label' => 'Fakturace a platby',  'message' => 'kategorie:fakturace'],
                ['label' => 'Domény a DNS',        'message' => 'kategorie:domeny'],
                ['label' => 'Spojit s podporou',   'message' => 'kategorie:podpora'],
            ],
            'links' => [['label' => 'Otevřít podporu', 'url' => route('panel.support.index')]],
            'category' => null,
        ];
    }

    // ── Category overviews ────────────────────────────────────────────────────────

    /** @return Reply */
    private function category(string $key, ?Customer $customer): array
    {
        return match ($key) {
            'objednavka' => [
                'reply' => "Novou službu objednáte v sekci Objednávky → Nová objednávka. Tarify můžete "
                    . "filtrovat, porovnat i vložit více služeb do košíku a objednat naráz. "
                    . "Platit lze kartou (Comgate), kreditem nebo bankovním převodem.",
                'suggestions' => [
                    ['label' => 'Jaký tarif pro WordPress?', 'message' => 'Jaký tarif je nejlepší pro WordPress?'],
                    ['label' => 'Jak funguje košík?',        'message' => 'Jak funguje košík a objednání více služeb?'],
                    ['label' => 'Jak porovnat tarify?',      'message' => 'Jak porovnám tarify mezi sebou?'],
                ],
                'links' => [
                    ['label' => 'Nová objednávka', 'url' => route('panel.orders.create')],
                    ['label' => 'Můj košík',       'url' => route('panel.cart.index')],
                ],
                'category' => 'objednavka',
            ],
            'fakturace' => [
                'reply' => "Faktury najdete v sekci Fakturace. Zálohovou fakturu (proforma) uhradíte kartou, "
                    . "kreditem nebo převodem; daňový doklad se vystaví automaticky po zaplacení. "
                    . $this->billingGrounding($customer) . ' ' . $this->creditLine($customer),
                'suggestions' => [
                    ['label' => 'Jak zaplatím fakturu?',     'message' => 'Jak zaplatím zálohovou fakturu?'],
                    ['label' => 'Proforma vs. daňový doklad', 'message' => 'Jaký je rozdíl mezi proformou a daňovým dokladem?'],
                    ['label' => 'Kde dobiju kredit?',        'message' => 'Jak si dobiju kredit na účtu?'],
                ],
                'links' => [
                    ['label' => 'Moje faktury', 'url' => route('panel.billing.invoices')],
                    ['label' => 'Dobít kredit', 'url' => route('panel.billing.credits')],
                ],
                'category' => 'fakturace',
            ],
            'domeny' => [
                'reply' => "Domény spravujete v sekci Domény — nastavíte nameservery, DNS záznamy i "
                    . "automatickou obnovu. Pro hosting u nás nasměrujte nameservery na "
                    . "ns1.onhost.cz a ns2.onhost.cz.",
                'suggestions' => [
                    ['label' => 'Co je A / CNAME / MX záznam?', 'message' => 'Co znamenají DNS záznamy A, CNAME, MX a TXT?'],
                    ['label' => 'Jak nasměruji doménu?',        'message' => 'Jak nasměruji doménu na váš hosting?'],
                    ['label' => 'Automatická obnova domény',    'message' => 'Jak funguje automatická obnova domény?'],
                ],
                'links' => [['label' => 'Moje domény', 'url' => route('panel.domains.index')]],
                'category' => 'domeny',
            ],
            'technicka' => [
                'reply' => "S technikou poradím: stav služeb, verze PHP, zálohy, SSL, instalace WordPressu i "
                    . "restart VPS. " . $this->serviceGrounding($customer),
                'suggestions' => [
                    ['label' => 'Jak změním verzi PHP?',     'message' => 'Jak změním verzi PHP u webhostingu?'],
                    ['label' => 'Jak nainstaluji WordPress?', 'message' => 'Jak nainstaluji WordPress?'],
                    ['label' => 'Jak zálohovat web?',        'message' => 'Jak funguje zálohování a jak obnovím zálohu?'],
                    ['label' => 'Restart VPS',               'message' => 'Jak restartuji svůj VPS server?'],
                ],
                'links' => [['label' => 'Moje služby', 'url' => route('panel.services.index')]],
                'category' => 'technicka',
            ],
            'ucet' => [
                'reply' => "Účet a zabezpečení: v Můj účet nastavíte profil, dvoufázové ověření (2FA), "
                    . "hesla, API tokeny i předvolby oznámení.",
                'suggestions' => [
                    ['label' => 'Jak zapnu 2FA?',        'message' => 'Jak zapnu dvoufázové ověření?'],
                    ['label' => 'Jak vytvořím API token?', 'message' => 'Jak si vytvořím API token?'],
                    ['label' => 'Nastavení oznámení',    'message' => 'Kde nastavím e-mailová oznámení?'],
                ],
                'links' => [['label' => 'Můj účet', 'url' => route('panel.account.profile')]],
                'category' => 'ucet',
            ],
            'podpora' => [
                'reply' => "Rád vás propojím s lidskou podporou. Založte ticket a náš tým se ozve — u dotazu "
                    . "prosím uveďte službu, přesný čas a chybovou hlášku, ať to vyřešíme rychle.",
                'suggestions' => [
                    ['label' => 'Založit ticket',   'message' => 'Jak založím ticket na podporu?'],
                    ['label' => 'Nahlásit výpadek',  'message' => 'Moje služba nefunguje, co mám dělat?'],
                ],
                'links' => [['label' => 'Podpora', 'url' => route('panel.support.index')]],
                'category' => 'podpora',
            ],
            default => $this->menu(),
        };
    }

    // ── Knowledge base ────────────────────────────────────────────────────────────

    /**
     * @return list<array{id: string, cat: string, kw: list<string>, a: string, links: list<Link>, follow: list<Suggestion>}>
     */
    private function knowledgeBase(): array
    {
        return [
            [
                'id' => 'plan_wordpress', 'cat' => 'objednavka',
                'kw' => ['wordpress', 'wp', 'tarif pro', 'jaký tarif', 'doporuč'],
                'a' => "Pro WordPress doporučujeme tarif s dostatkem výkonu a NVMe úložištěm. Pro menší web "
                    . "stačí Start, pro firemní web s návštěvností volte Business, pro e-shop nebo více webů Pro. "
                    . "WordPress lze na webhostingu nainstalovat jedním klikem.",
                'links' => [['label' => 'Vybrat tarif', 'url' => route('panel.orders.create')]],
                'follow' => [
                    ['label' => 'Porovnat tarify', 'message' => 'Jak porovnám tarify mezi sebou?'],
                    ['label' => 'Jak funguje košík?', 'message' => 'Jak funguje košík a objednání více služeb?'],
                ],
            ],
            [
                'id' => 'cart', 'cat' => 'objednavka',
                'kw' => ['košík', 'kosik', 'více služeb', 'vice sluzeb', 'najednou', 'naráz'],
                'a' => "Do košíku přidáte libovolný počet tarifů tlačítkem „Do košíku“ u každého tarifu. "
                    . "V košíku upravíte množství, zadáte doménu k webhostingu a vše objednáte jednou "
                    . "objednávkou i jednou platbou.",
                'links' => [['label' => 'Otevřít košík', 'url' => route('panel.cart.index')]],
                'follow' => [
                    ['label' => 'Jak zaplatím?', 'message' => 'Jak zaplatím zálohovou fakturu?'],
                ],
            ],
            [
                'id' => 'compare', 'cat' => 'objednavka',
                'kw' => ['porovn', 'compare', 'rozdíl mezi tarify', 'srovnat'],
                'a' => "Na stránce Nová objednávka zaškrtněte u tarifů „Přidat k porovnání“ (max. 4) a klikněte "
                    . "na „Porovnat“. Zobrazí se tabulka parametrů vedle sebe a u každého tarifu tlačítko Objednat.",
                'links' => [['label' => 'Nová objednávka', 'url' => route('panel.orders.create')]],
                'follow' => [
                    ['label' => 'Jaký tarif pro WordPress?', 'message' => 'Jaký tarif je nejlepší pro WordPress?'],
                ],
            ],
            [
                'id' => 'pay_invoice', 'cat' => 'fakturace',
                'kw' => ['zaplat', 'uhrad', 'platba', 'zaplacení', 'jak platit'],
                'a' => "Otevřete fakturu v sekci Fakturace a zvolte způsob úhrady: kartou přes Comgate, "
                    . "z kreditu (uhradí se okamžitě), nebo bankovním převodem podle údajů na faktuře. "
                    . "Po zaplacení se služba automaticky zřídí.",
                'links' => [['label' => 'Moje faktury', 'url' => route('panel.billing.invoices')]],
                'follow' => [
                    ['label' => 'Kde dobiju kredit?', 'message' => 'Jak si dobiju kredit na účtu?'],
                ],
            ],
            [
                'id' => 'proforma', 'cat' => 'fakturace',
                'kw' => ['proforma', 'zálohov', 'zalohov', 'daňový doklad', 'danovy doklad', 'rozdíl mezi'],
                'a' => "Zálohová faktura (proforma) není daňový doklad — slouží pouze k úhradě. Jakmile ji "
                    . "zaplatíte, systém automaticky vystaví daňový doklad, který najdete u dané faktury "
                    . "a je podkladem pro účetnictví.",
                'links' => [['label' => 'Moje faktury', 'url' => route('panel.billing.invoices')]],
                'follow' => [],
            ],
            [
                'id' => 'credit', 'cat' => 'fakturace',
                'kw' => ['kredit', 'dobít', 'dobit', 'zůstatek', 'zustatek', 'peněženk'],
                'a' => "Kredit je předplacený zůstatek, ze kterého lze okamžitě hradit faktury. Dobijete ho "
                    . "v sekci Fakturace → Kredit. " . $this->creditGrounding(),
                'links' => [['label' => 'Dobít kredit', 'url' => route('panel.billing.credits')]],
                'follow' => [],
            ],
            [
                'id' => 'dns_records', 'cat' => 'domeny',
                'kw' => ['dns', 'záznam', 'zaznam', 'a záznam', 'cname', 'mx', 'txt', 'spf', 'dkim'],
                'a' => "DNS záznamy směrují vaši doménu: A míří na IPv4 serveru, AAAA na IPv6, CNAME je alias "
                    . "na jinou doménu, MX směruje e-maily, a TXT slouží k ověření (SPF, DKIM, DMARC). "
                    . "Záznamy upravíte u domény v sekci Domény → DNS.",
                'links' => [['label' => 'Moje domény', 'url' => route('panel.domains.index')]],
                'follow' => [
                    ['label' => 'Jak nasměruji doménu?', 'message' => 'Jak nasměruji doménu na váš hosting?'],
                ],
            ],
            [
                'id' => 'point_domain', 'cat' => 'domeny',
                'kw' => ['nasměr', 'nasmer', 'nameserver', 'ns1', 'ns2', 'převést doménu', 'delegov'],
                'a' => "Doménu na náš hosting nasměrujete změnou nameserverů u registrátora na "
                    . "ns1.onhost.cz a ns2.onhost.cz. Změna se projeví obvykle do několika hodin "
                    . "(propagace DNS až 24 h). Alternativně můžete u domény nastavit jen A záznam na IP webu.",
                'links' => [['label' => 'Moje domény', 'url' => route('panel.domains.index')]],
                'follow' => [
                    ['label' => 'Automatická obnova', 'message' => 'Jak funguje automatická obnova domény?'],
                ],
            ],
            [
                'id' => 'domain_renew', 'cat' => 'domeny',
                'kw' => ['obnov', 'auto-obnova', 'automatická obnova', 'expirace', 'prodloužit doménu'],
                'a' => "U každé domény lze zapnout automatickou obnovu — před expirací vystavíme fakturu a po "
                    . "úhradě doménu prodloužíme. Stav a přepínač najdete v detailu domény.",
                'links' => [['label' => 'Moje domény', 'url' => route('panel.domains.index')]],
                'follow' => [],
            ],
            [
                'id' => 'php_version', 'cat' => 'technicka',
                'kw' => ['php', 'verze php', 'php verze', 'php version'],
                'a' => "Verzi PHP změníte v detailu webhostingové služby v kartě „Správa webhostingu“ — vyberete "
                    . "verzi a potvrdíte. Změna proběhne během chvíle bez výpadku webu.",
                'links' => [['label' => 'Moje služby', 'url' => route('panel.services.index')]],
                'follow' => [
                    ['label' => 'Jak nainstaluji WordPress?', 'message' => 'Jak nainstaluji WordPress?'],
                ],
            ],
            [
                'id' => 'wordpress_install', 'cat' => 'technicka',
                'kw' => ['nainstal', 'instalace wordpress', 'install wordpress', 'wordpress'],
                'a' => "WordPress nainstalujete z detailu webhostingu tlačítkem pro instalaci WordPressu — "
                    . "systém založí databázi i administrátorský účet a web bude připraven k přihlášení.",
                'links' => [['label' => 'Moje služby', 'url' => route('panel.services.index')]],
                'follow' => [
                    ['label' => 'Jak změním verzi PHP?', 'message' => 'Jak změním verzi PHP u webhostingu?'],
                ],
            ],
            [
                'id' => 'backups', 'cat' => 'technicka',
                'kw' => ['zálob', 'zálo', 'zaloh', 'backup', 'obnov zálohu', 'obnovit web'],
                'a' => "Zálohy běží automaticky podle nastaveného plánu (výchozí denně, uchování 14 dní). "
                    . "Plán i ruční zálohu a obnovu spravujete v detailu služby v kartě záloh.",
                'links' => [['label' => 'Moje služby', 'url' => route('panel.services.index')]],
                'follow' => [],
            ],
            [
                'id' => 'vps_restart', 'cat' => 'technicka',
                'kw' => ['restart', 'vps', 'server neběží', 'zapnout server', 'vypnout server', 'power'],
                'a' => "VPS ovládáte v detailu služby v kartě „Správa VPS“ — start, stop i restart. Stav "
                    . "serveru se načítá živě. U produkčního serveru volte restart až mimo špičku.",
                'links' => [['label' => 'Moje služby', 'url' => route('panel.services.index')]],
                'follow' => [],
            ],
            [
                'id' => 'ssl', 'cat' => 'technicka',
                'kw' => ['ssl', 'certifik', 'https', 'zabezpečení webu', 'lets encrypt', 'zámeček'],
                'a' => "K webhostingu vydáváme SSL certifikát (Let's Encrypt) automaticky a obnovujeme ho. "
                    . "Po nasměrování domény na hosting se HTTPS aktivuje během chvíle.",
                'links' => [['label' => 'Moje služby', 'url' => route('panel.services.index')]],
                'follow' => [],
            ],
            [
                'id' => 'two_factor', 'cat' => 'ucet',
                'kw' => ['2fa', 'dvoufáz', 'dvoufaz', 'two factor', 'ověření', 'authenticator', 'totp'],
                'a' => "Dvoufázové ověření (2FA) zapnete v Můj účet → Zabezpečení. Naskenujete QR kód do "
                    . "aplikace (Google/Microsoft Authenticator) a potvrdíte kódem. Doporučujeme uložit "
                    . "i záložní kódy.",
                'links' => [['label' => 'Zabezpečení účtu', 'url' => route('panel.account.security')]],
                'follow' => [],
            ],
            [
                'id' => 'api_token', 'cat' => 'ucet',
                'kw' => ['api token', 'api klíč', 'api klic', 'token', 'api'],
                'a' => "API token vytvoříte v Můj účet (sekce API) — token se zobrazí jen jednou, uložte si ho. "
                    . "Tokenům lze omezit oprávnění (abilities) a kdykoli je odvolat.",
                'links' => [['label' => 'Můj účet', 'url' => route('panel.account.profile')]],
                'follow' => [],
            ],
            [
                'id' => 'notifications', 'cat' => 'ucet',
                'kw' => ['oznámení', 'oznameni', 'notifikace', 'e-mail', 'email notif', 'předvolby'],
                'a' => "Předvolby oznámení nastavíte v Můj účet → Oznámení. Zapnete/vypnete e-maily podle typu "
                    . "(faktury, provoz služeb, bezpečnost). Bezpečnostní upozornění vidíte i ve zvonku v hlavičce.",
                'links' => [['label' => 'Můj účet', 'url' => route('panel.account.profile')]],
                'follow' => [],
            ],
            [
                'id' => 'open_ticket', 'cat' => 'podpora',
                'kw' => ['ticket', 'podpor', 'kontakt', 'nefunguje', 'výpadek', 'vypadek', 'pomoc', 'člověk', 'clovek', 'operátor'],
                'a' => "Ticket založíte v sekci Podpora tlačítkem pro nový požadavek. Uveďte prosím službu, "
                    . "přesný čas problému a chybovou hlášku — tým se ozve co nejdříve. U kritického výpadku "
                    . "označte prioritu jako vysokou.",
                'links' => [['label' => 'Otevřít podporu', 'url' => route('panel.support.index')]],
                'follow' => [],
            ],
        ];
    }

    // ── Matching + answers ────────────────────────────────────────────────────────

    /**
     * @return array{id: string, cat: string, kw: list<string>, a: string, links: list<Link>, follow: list<Suggestion>}|null
     */
    private function bestMatch(string $message): ?array
    {
        $haystack = $this->normalize($message);
        $best     = null;
        $bestScore = 0.0;

        foreach ($this->allEntries() as $entry) {
            $score = 0.0;

            foreach ($entry['kw'] as $keyword) {
                $needle = $this->normalize($keyword);

                if ($needle === '' || ! str_contains($haystack, $needle)) {
                    continue;
                }

                // Longer keywords are more specific, so they weigh more than a
                // stray two-letter hit; curated answers add their priority.
                $score += 1 + (mb_strlen($needle) / 20);
            }

            if ($score <= 0.0) {
                continue;
            }

            $score += ($entry['priority'] ?? 0) / 10;

            if ($score > $bestScore) {
                $bestScore = $score;
                $best      = $entry;
            }
        }

        if ($best !== null && isset($best['db_id'])) {
            // Cheap popularity signal for the admin list; never block a reply.
            try {
                ChatAnswer::whereKey($best['db_id'])->increment('hits');
            } catch (\Throwable) {
                // ignore
            }
        }

        return $bestScore > 0.0 ? $best : null;
    }

    /**
     * Built-in knowledge base plus admin-curated answers (audit: chat without an
     * external LLM). Curated rows are appended so support can extend or override
     * coverage without a deploy; ties are broken by their priority.
     *
     * @return list<array<string, mixed>>
     */
    private function allEntries(): array
    {
        return array_merge($this->knowledgeBase(), $this->curatedEntries());
    }

    /** @return list<array<string, mixed>> */
    private function curatedEntries(): array
    {
        try {
            $rows = Cache::remember(
                'chat:curated-answers',
                300,
                fn () => ChatAnswer::query()->where('is_active', true)->orderByDesc('priority')->get(),
            );
        } catch (\Throwable) {
            return []; // table not migrated / DB down — built-in KB still answers
        }

        $out = [];

        foreach ($rows as $row) {
            $out[] = [
                'id'       => 'db_' . $row->id,
                'db_id'    => $row->id,
                'cat'      => $row->category,
                'question' => $row->question,
                'kw'       => $row->keywordList(),
                'a'        => $row->answer,
                'links'    => $row->links ?? [],
                'follow'   => $row->follow_ups ?? [],
                'priority' => $row->priority,
            ];
        }

        return $out;
    }

    /**
     * Predictive suggestions while the user types (audit: chat predictions).
     *
     * Matches the partial input against question phrasings and keywords and
     * returns the most relevant questions, so the user can pick a known-good
     * question instead of guessing wording the matcher understands.
     *
     * @return list<array{label: string, message: string, category: string|null}>
     */
    public function predict(string $partial, int $limit = 6): array
    {
        $needle = $this->normalize(trim($partial));

        if (mb_strlen($needle) < 2) {
            return [];
        }

        $scored = [];
        $seen   = [];

        foreach ($this->candidateQuestions() as $candidate) {
            $haystack = $this->normalize($candidate['label']);
            $score    = 0.0;

            if (str_contains($haystack, $needle)) {
                // A prefix match is most likely what the user is typing.
                $score += str_starts_with($haystack, $needle) ? 3.0 : 2.0;
            }

            foreach ($candidate['kw'] as $keyword) {
                $kw = $this->normalize($keyword);

                if ($kw !== '' && str_contains($kw, $needle)) {
                    $score += 1.0;
                    break;
                }
            }

            if ($score <= 0.0) {
                continue;
            }

            $key = mb_strtolower($candidate['label']);

            if (isset($seen[$key])) {
                continue; // the same question can appear as several follow-ups
            }
            $seen[$key] = true;

            $scored[] = [
                'score'    => $score + ($candidate['priority'] / 10),
                'label'    => $candidate['label'],
                'message'  => $candidate['label'],
                'category' => $candidate['cat'],
            ];
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_map(
            static fn (array $s): array => ['label' => $s['label'], 'message' => $s['message'], 'category' => $s['category']],
            array_slice($scored, 0, $limit),
        );
    }

    /**
     * Natural-language questions the bot can answer well.
     *
     * Curated rows supply their own `question`; the built-in KB contributes its
     * follow-up prompts, which are already phrased as real questions ("Jak
     * porovnám tarify?"). Suggesting wording the matcher understands is the
     * whole point — it stops users guessing phrasings that fall through.
     *
     * @return list<array{label: string, kw: list<string>, cat: string|null, priority: int}>
     */
    private function candidateQuestions(): array
    {
        $out = [];

        foreach ($this->allEntries() as $entry) {
            $cat      = isset($entry['cat']) ? (string) $entry['cat'] : null;
            $priority = (int) ($entry['priority'] ?? 0);
            /** @var list<string> $keywords */
            $keywords = $entry['kw'] ?? [];

            $question = isset($entry['question']) ? trim((string) $entry['question']) : '';

            if ($question !== '') {
                $out[] = ['label' => $question, 'kw' => $keywords, 'cat' => $cat, 'priority' => $priority];
            }

            /** @var list<array{label: string, message: string}> $follow */
            $follow = $entry['follow'] ?? [];

            foreach ($follow as $suggestion) {
                $message = trim($suggestion['message']);

                // Category chips ("kategorie:…") aren't questions.
                if ($message === '' || str_starts_with($message, 'kategorie:')) {
                    continue;
                }

                $out[] = ['label' => $message, 'kw' => $keywords, 'cat' => $cat, 'priority' => $priority];
            }
        }

        return $out;
    }

    /**
     * @param  array{id: string, cat: string, kw: list<string>, a: string, links: list<Link>, follow: list<Suggestion>}  $entry
     * @return Reply
     */
    private function answer(array $entry, ?Customer $customer): array
    {
        $suggestions = $entry['follow'];

        // Always give a way back to the category and to human support.
        $suggestions[] = ['label' => 'Další v tomto tématu', 'message' => 'kategorie:' . $entry['cat']];

        return [
            'reply'       => $entry['a'],
            'suggestions' => $suggestions,
            'links'       => $entry['links'],
            'category'    => $entry['cat'],
        ];
    }

    private function guessCategory(string $message): ?string
    {
        $haystack = $this->normalize($message);
        $scores   = [];

        foreach ($this->knowledgeBase() as $entry) {
            foreach ($entry['kw'] as $keyword) {
                if (str_contains($haystack, $this->normalize($keyword))) {
                    $scores[$entry['cat']] = ($scores[$entry['cat']] ?? 0) + 1;
                }
            }
        }

        if ($scores === []) {
            return null;
        }

        arsort($scores);

        return (string) array_key_first($scores);
    }

    // ── Grounding in the customer's own data ──────────────────────────────────────

    private function serviceGrounding(?Customer $customer): string
    {
        if ($customer === null) {
            return '';
        }

        $active = Service::where('customer_id', $customer->id)
            ->where('status', ServiceStatus::Active->value)
            ->count();

        return $active > 0
            ? "Aktuálně máte {$active} aktivní" . ($active >= 5 ? 'ch' : '') . " služeb/služby."
            : 'Zatím u nás nemáte žádnou aktivní službu.';
    }

    private function billingGrounding(?Customer $customer): string
    {
        if ($customer === null) {
            return '';
        }

        $open = Invoice::where('customer_id', $customer->id)
            ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::Overdue->value])
            ->count();

        return $open > 0
            ? "Máte {$open} neuhrazenou fakturu/faktury k zaplacení."
            : 'Aktuálně nemáte žádnou neuhrazenou fakturu.';
    }

    private function creditGrounding(): string
    {
        // Grounded via the reply() caller's customer; kept generic here to
        // avoid a second lookup — balance is shown on the credits page.
        return 'Aktuální zůstatek uvidíte na stránce kreditu.';
    }

    /** Balance line for a concrete customer (used by the fakturace overview). */
    public function creditLine(?Customer $customer): string
    {
        if ($customer === null) {
            return '';
        }

        return 'Váš zůstatek kreditu: ' . MoneyFormatter::format($this->ledger->getBalance($customer)) . '.';
    }

    // ── Helpers ───────────────────────────────────────────────────────────────────

    private function isCategory(string $key): bool
    {
        foreach (self::CATEGORIES as $c) {
            if ($c['key'] === $key) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeGreeting(string $message): bool
    {
        $h = $this->normalize($message);

        foreach (['ahoj', 'dobry den', 'zdravim', 'cau', 'hello', 'hi', 'menu', 'pomoc', 'help', 'zacit'] as $g) {
            if ($h === $g) {
                return true;
            }
        }

        return false;
    }

    /** Lower-cases and strips Czech diacritics so matching is accent-insensitive. */
    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $map  = [
            'á' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e', 'í' => 'i', 'ň' => 'n',
            'ó' => 'o', 'ř' => 'r', 'š' => 's', 'ť' => 't', 'ú' => 'u', 'ů' => 'u', 'ý' => 'y', 'ž' => 'z',
        ];

        return strtr($text, $map);
    }
}
