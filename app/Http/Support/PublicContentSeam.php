<?php

declare(strict_types=1);

namespace App\Http\Support;

/**
 * The public site's content seams of the owner's decisions of 2026-10-08 (TASK-0149). The prototype files stay byte-identical;
 * `SurfaceRenderer::transform()` applies these pairs to the public template outside demo mode, and `SurfaceController` applies
 * `contentModule()` to the served `onhost-content.js`.
 *
 *  - I-R16 GPU: H100 / L40S are planned, not sold. Every place that offered the cards — the mega menu card with "7 of 24 cards
 *    left", the AI band, the service list with hourly prices, the menus and the search, the stock list, the price comparison and the
 *    operations page's delivery promise — says "připravujeme" with no price, stock count, delivery time or order.
 *  - I-R19 team: the support page (`#/lide`) named eight invented people with tenures, bios, a 12-person roster, an 18-minute median
 *    and night shifts. It now shows the real team from `ONHOST_DATA.team` (initials, neutral roles), the reaction times the SLA and
 *    the terms give, and the operator's real contacts from `ONHOST_DATA.contact`.
 *  - I-R17 blog: the content module's posts are the ones staff publish (`ONHOST_DATA.blog`); the prototype's articles are not sent.
 */
final class PublicContentSeam
{
    /** @return array<string, string> anchor => replacement, each anchor once in Onhost.dc.html */
    public static function gpu(): array
    {
        return [
            // the mega menu card (static Czech markup)
            "</span>Early access\n          </div>\n          <h4 style=\"margin:0 0 8px;font-size:19px;color:#f3f2f2\">GPU H100 v Praze</h4>\n          <p style=\"font-size:13px;color:rgba(243,242,242,.7);margin:0 0 16px\">Zbývá 7 z 24 karet. Účtování po hodinách, data zůstávají v ČR.</p>" => "</span>Připravujeme\n          </div>\n          <h4 style=\"margin:0 0 8px;font-size:19px;color:#f3f2f2\">GPU H100 a L40S</h4>\n          <p style=\"font-size:13px;color:rgba(243,242,242,.7);margin:0 0 16px\">GPU H100 a L40S připravujeme. Zatím je neobjednáte a cenu ani termín neslibujeme.</p>",
            // hero and catalogue lines
            "heroSub: 'Webhosting, gamehosting, VPS, Git repo hosting i GPU pro AI — na jedné české platformě. Z pushnutého commitu do produkce za 8,4 sekundy.'," => "heroSub: 'Webhosting, gamehosting, VPS, Git repo hosting a domény na jedné české platformě. GPU pro AI připravujeme.',",
            "heroSub: 'Web hosting, game hosting, VPS, Git repo hosting and GPUs for AI — on one Czech platform. From push to production in 8.4 seconds.'," => "heroSub: 'Web hosting, game hosting, VPS, Git repo hosting and domains on one Czech platform. GPUs for AI are in preparation.',",
            'Od statického webu po GPU klastr.' => 'Od statického webu po vlastní server.',
            'From a static site to a GPU cluster.' => 'From a static site to your own server.',
            // the AI band
            "aiTitle: 'Výkon pro AI, který nemusíte hledat v zahraničí'," => "aiTitle: 'GPU pro AI připravujeme',",
            "aiSub: 'Karty H100 a L40S stojí v Praze, takže data ani latence neopouštějí zemi. Inference endpointy, vektorovou databázi i autoscaling podle predikce trafficu zapnete v panelu — a AI asistent vám v logu ukáže, kde to spadlo.'," => "aiSub: 'Karty H100 a L40S připravujeme. Zatím je neprodáváme, a proto u nich neuvádíme cenu, sklad ani termín dodání. Až budou, najdete je v ceníku a v katalogu.',",
            "aiCta: 'Získat early access', aiPanel: 'GPU pool · Praha'," => "aiCta: 'Vytvořit účet', aiPanel: 'AI a data',",
            "aiFine: 'Early access: kapacita se přiděluje v pořadí objednávek.'," => "aiFine: 'GPU zatím neprodáváme; spuštění oznámíme na blogu.',",
            "aiTitle: 'AI compute you do not have to look for abroad'," => "aiTitle: 'GPUs for AI are in preparation',",
            "aiSub: 'H100 and L40S cards sit in Prague, so neither your data nor your latency leaves the country. Inference endpoints, a vector database and traffic-prediction autoscaling are one toggle away — and the AI assistant points at the line in the log that broke.'," => "aiSub: 'We are preparing H100 and L40S cards. They are not on sale yet, so there is no price, stock or delivery time for them. Once they are, you will find them in the price list and the catalogue.',",
            "aiCta: 'Get early access', aiPanel: 'GPU pool · Prague'," => "aiCta: 'Create an account', aiPanel: 'AI and data',",
            "aiFine: 'Early access: capacity is allocated in order of sign-up.'," => "aiFine: 'GPUs are not on sale yet; the launch will be announced on the blog.',",
            "        ['GPU instance', 'H100 80 GB a L40S, účtované po hodinách.'],\n" => "        ['GPU instance', 'H100 a L40S připravujeme — zatím bez ceny a bez objednávky.'],\n",
            "        ['GPU instances', 'H100 80 GB and L40S, billed hourly.'],\n" => "        ['GPU instances', 'H100 and L40S in preparation — no price and no orders yet.'],\n",
            "        { k: 'H100 80 GB', v: '7 / 24 volných', s: '39 Kč/h' },\n        { k: 'L40S 48 GB', v: '15 / 32 volných', s: '19 Kč/h' },\n" => "        { k: 'H100 80 GB', v: 'připravujeme', s: '—' },\n        { k: 'L40S 48 GB', v: 'připravujeme', s: '—' },\n",
            "        { k: 'H100 80 GB', v: '7 / 24 free', s: '39 CZK/h' },\n        { k: 'L40S 48 GB', v: '15 / 32 free', s: '19 CZK/h' },\n" => "        { k: 'H100 80 GB', v: 'coming soon', s: '—' },\n        { k: 'L40S 48 GB', v: 'coming soon', s: '—' },\n",
            // the service list with prices: a text instead of an hourly price
            "['GPU H100 (hodina)', 39], ['GPU L40S (hodina)', 19]" => "['GPU H100', 'připravujeme'], ['GPU L40S', 'připravujeme']",
            "['GPU H100 (hour)', 39], ['GPU L40S (hour)', 19]" => "['GPU H100', 'coming soon'], ['GPU L40S', 'coming soon']",
            "price: i[1] ? this.czk(i[1]) : (cs ? 'v ceně' : 'included')" => "price: typeof i[1] === 'string' ? i[1] : (i[1] ? this.czk(i[1]) : (cs ? 'v ceně' : 'included'))",
            // menus and the site search
            "['GPU instance', 'H100 a L40S v Praze']" => "['GPU instance', 'Připravujeme']",
            "['GPU instances', 'H100 and L40S in Prague']" => "['GPU instances', 'Coming soon']",
            "['GPU a inference', 'H100 a L40S v Praze', 'pricing']" => "['GPU a inference', 'Připravujeme', 'pricing']",
            "['GPU and inference', 'H100 and L40S in Prague', 'pricing']" => "['GPU and inference', 'Coming soon', 'pricing']",
            "'GPU instance H100 a L40S', 'Inference v Praze, účtování po hodinách'" => "'GPU instance H100 a L40S', 'Připravujeme — zatím bez ceny a bez objednávky'",
            "'GPU instances H100 and L40S', 'Inference in Prague, billed hourly'" => "'GPU instances H100 and L40S', 'Coming soon — no price and no orders yet'",
            // the hardware stock list (#/technika)
            "        ['L40S', '48 GB VRAM · vLLM předinstalované', '12 900 Kč', 'nájem / měsíc', '6 karet skladem', 'warn'],\n        ['H100', '80 GB VRAM · rezervace od 3 měsíců', '38 900 Kč', 'nájem / měsíc', 'dodání 5 dnů', 'off'],\n" => "        ['L40S', '48 GB VRAM', 'připravujeme', '', 'zatím nenabízíme', 'off'],\n        ['H100', '80 GB VRAM', 'připravujeme', '', 'zatím nenabízíme', 'off'],\n",
            "        ['L40S', '48 GB VRAM · vLLM preinstalled', '12,900 CZK', 'rental / month', '6 cards in stock', 'warn'],\n        ['H100', '80 GB VRAM · reservations from 3 months', '38,900 CZK', 'rental / month', '5-day delivery', 'off'],\n" => "        ['L40S', '48 GB VRAM', 'coming soon', '', 'not on offer yet', 'off'],\n        ['H100', '80 GB VRAM', 'coming soon', '', 'not on offer yet', 'off'],\n",
            // the comparison tables and the operations page
            "['GPU a AI', 'v ceně platformy', 'nenabízí']" => "['GPU a AI', 'připravujeme', 'nenabízí']",
            "['GPU and AI', 'part of the platform', 'not offered']" => "['GPU and AI', 'coming soon', 'not offered']",
            "        ['GPU po minutách', 'levnější', '84 Kč/h', 'jinde obvykle po hodinách', 1, 'Účtujeme započatou minutu, ne hodinu. Hodinové zaokrouhlení bychom vydělali, a proto ho nemáme — účtovat hodinu za devítiminutový výpočet je jen tichý příplatek.'],\n" => '',
            "        ['GPU by the minute', 'cheaper', 'CZK 84/h', 'usually CZK 96/h', 1, 'We bill the started minute, not the hour. Hourly rounding would earn us over eighteen thousand a month, which is why we do not do it — billing an hour for a nine-minute job is a quiet surcharge.'],\n" => '',
            'Kde jsme levnější, jsme levnější schválně: GPU účtujeme po započaté minutě, migraci neúčtujeme vůbec. A retenční slevu' => 'Retenční slevu',
            'Where we are cheaper, we are cheaper on purpose: GPU billed by the started minute, migration not billed at all. And we never offer' => 'We never offer',
            "        ['GPU podle dostupnosti karet', 'jeden zdroj', 'U H100 máme jediného dodavatele a je to riziko, které si píšeme. Proto vám u nich říkáme pět dnů, ne čtyřiadvacet hodin.'],\n" => "        ['GPU', 'připravujeme', 'Karty H100 a L40S zatím nenabízíme, proto u nich neuvádíme cenu ani termín dodání.'],\n",
            "        ['GPUs by card availability', 'single source', 'For H100 we have one supplier and we write that risk down. That is why we quote five days on them, not twenty-four hours.'],\n" => "        ['GPU', 'coming soon', 'H100 and L40S cards are not on offer yet, so there is no price or delivery time for them.'],\n",
        ];
    }

    /**
     * J-R1 (owner, 2026-10-11): the invented figures come off the public site until the owner supplies real, documented ones — the
     * eco band on the home page (heat into the grid, solar power, "the figures are placeholders"), the PUE row of the hardware cards
     * and the "what we cover" lead of #/technika that promised waste heat and stock.
     *
     * @return array<string, string> anchor => replacement, each anchor once in Onhost.dc.html
     */
    public static function technika(): array
    {
        return [
            // the home page's eco band (the change log beside it stays)
            "      ecoKicker: 'Eko a komunita', ecoTitle: 'Servery, které topí sousedům a platí komunitní projekty'," => "      ecoKicker: 'Provoz', ecoTitle: 'Co o provozu tvrdíme, doložíme',",
            "      ecoSub: 'Odpadní teplo z pražské serverovny jde do sítě, elektřinu bereme z fotovoltaiky a komunitních zdrojů. Část z každého tarifu se vrací do projektů, které si volí komunita.'," => "      ecoSub: 'Čísla o energii a chlazení zveřejníme, až budou změřená a doložená. Do té doby je na webu neuvádíme.',",
            "      ecoNote: 'Čísla jsou zatím orientační — dodejte reálná a doplníme je.'," => "      ecoNote: '',",
            "      ecoCommunity: 'Komunita', ecoCta: 'Přidat se na Discord', ecoCta2: 'Free tier pro open-source'," => "      ecoCommunity: 'Komunita', ecoCta: 'Prohlédnout služby', ecoCta2: 'Vytvořit účet',",
            "      ecoKicker: 'Green and community', ecoTitle: 'Servers that heat the neighbours and fund community projects'," => "      ecoKicker: 'Operations', ecoTitle: 'What we claim about operations, we document',",
            "      ecoSub: 'Waste heat from our Prague facility goes into the district network, our power comes from solar and community sources. A share of every plan flows back into projects the community picks.'," => "      ecoSub: 'We will publish energy and cooling figures once they are measured and documented. Until then the site does not state them.',",
            "      ecoNote: 'These figures are placeholders — send us the real ones and we will drop them in.'," => "      ecoNote: '',",
            "      ecoCommunity: 'Community', ecoCta: 'Join the Discord', ecoCta2: 'Free tier for open source'," => "      ecoCommunity: 'Community', ecoCta: 'Browse services', ecoCta2: 'Create an account',",
            // #/technika: the lead no longer promises waste heat and stock, the hardware cards lose the PUE row
            ', lokality, odpadní teplo a to, co máme právě teď skladem.\'' => ' a lokality.\'',
            ', locations, waste heat and what is actually in stock right now.\'' => ' and locations.\'',
            "        ['Chlazení dřív než výkon', 'PUE 1,18', 'Úzkým místem naší GPU haly není počet karet, ale chlazení. Kdo staví na výkon a ne na teplo, dojde na hranici o rok dřív.'],\n" => '',
            "        ['Cooling before compute', 'PUE 1.18', 'The bottleneck in our GPU hall is cooling, not card count. Build for compute rather than heat and you hit the wall a year early.'],\n" => '',
        ];
    }

    /**
     * J-R1: whole blocks replaced, each `[from, to, replacement]` (from unique, cut up to the first `to` after it) — the "first year
     * with a customer" story on #/ceny-a-sla and the "in stock right now" section of #/technika lose their markup and their data
     * (so the story and the EPYC counts are not even in the page source), and the unused eco figures of the data object are emptied.
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public static function technikaBlocks(): array
    {
        $section = "    <div style=\"max-width:1360px;margin:0 auto;padding:52px 32px 58px\">\n";

        return [
            // markup: the story section (up to the sales section) and the stock section (up to the "engineer's day" section)
            ["  <section style=\"border-bottom:2px solid color-mix(in srgb,var(--fg,#201e1d) 40%,transparent)\">\n".$section.'      <h2 style="font-size:40px;margin:0 0 10px;letter-spacing:-.022em;text-wrap:balance">{{ yearTitle }}</h2>', "  <section>\n", ''],
            ["  <section style=\"background:var(--ink,#1a1918);color:#f3f2f2;border-bottom:2px solid color-mix(in srgb,var(--fg,#201e1d) 40%,transparent)\">\n".$section.'      <h6 style="color:var(--neon,#b8ff2e);margin:0 0 10px">{{ stockKicker }}</h6>', '  <section style="border-bottom:2px solid', ''],
            // data: the story, the stock rows (the GPU rows were already rewritten by gpu()) and the eco figures
            ["      yearTitle: cs ? 'První rok u nás, doložený'", '      glosTitle:', "      yearTitle: '', yearLead: '', yearRows: [], yearFoot: '',\n"],
            ["      stockKicker: cs ? 'Skladem právě teď'", "      ]).map(x => ({\n        name: x[0], spec: x[1], price: x[2]", "      stockKicker: '', stockTitle: '', stockLead: '', stockFoot: '',\n      stock: ([\n"],
            ["      ecoStats: [\n", '      ecoCommunityItems:', "      ecoStats: [],\n"],
        ];
    }

    /** The team page block, from its comment to the next key (`isMissingView`), replaced as a whole. */
    public const TEAM_FROM = "      /* ---------- Lidé v podpoře (#/lide) ---------- */\n      ppKicker:";

    public const TEAM_TO = "\n      isMissingView:";

    public static function team(): string
    {
        $team = '((window.ONHOST_DATA && window.ONHOST_DATA.team && window.ONHOST_DATA.team(cs)) || [])';

        return <<<JS
              /* ---------- Lidé v podpoře (#/lide) — I-R19: the real team (ONHOST_DATA.team), no invented people or figures ---------- */
              ppKicker: cs ? 'Tým ONhost' : 'The ONhost team',
              ppH: cs ? 'Lidé, kteří ONhost provozují' : 'The people who run ONhost',
              ppLead: cs
                ? 'ONhost provozuje malý tým. Na podpoře, provozu i vývoji platformy pracujeme společně a odpovídáme přes tikety v klientském panelu.'
                : 'ONhost is run by a small team. Support, operations and development of the platform are shared, and we answer through tickets in the customer panel.',
              ppHeads: [
                { v: String({$team}.length || '—'), k: cs ? 'Lidé v týmu' : 'People on the team', n: cs ? 'jména níže' : 'names below' },
                { v: '4 h', k: cs ? 'Reakce na incident' : 'Incident response', n: cs ? 'tarif Standard, podle SLA' : 'Standard plan, per the SLA' },
                { v: '1 h', k: cs ? 'Reakce na incident' : 'Incident response', n: cs ? 'tarif Business, podle SLA' : 'Business plan, per the SLA' },
                { v: cs ? '30 dní' : '30 days', k: cs ? 'Na vyřízení reklamace' : 'To decide a complaint', n: cs ? 'podle obchodních podmínek' : 'per the terms of service' }
              ],
              ppTeamTitle: cs ? 'Kdo jsme' : 'Who we are',
              ppTeamLead: cs ? 'Lidé, kteří ONhost provozují. Roli uvádíme jen tam, kde je daná.' : 'The people who run ONhost. A role is given only where there is one.',
              ppPeople: {$team}.map(p => ({ ini: p.ini, name: p.name, role: p.role, tenure: '', bio: '', tag: '', tagStyle: 'display:none' })),
              ppShiftTitle: cs ? 'Jak nás zastihnete' : 'How to reach us',
              ppShiftLead: cs ? 'Kontakty, které platí. Reakční doby stanoví smlouva o úrovni služeb (SLA), ne tahle stránka.' : 'Contacts that work. Response times are set by the service level agreement (SLA), not by this page.',
              ppShifts: (() => {
                const c = (window.ONHOST_DATA && window.ONHOST_DATA.contact && window.ONHOST_DATA.contact()) || {};
                return (cs ? [
                  ['Klientský panel', 'Tiket u služby', 'Požadavek má číslo a celou historii na jednom místě.', 'reakce podle SLA'],
                  ['Telefon', c.phone || '—', 'Pro naléhavé věci; vše ostatní raději tiketem.', ''],
                  ['Odstoupení a reklamace', c.email || '—', 'Písemně; reklamaci vyřídíme do 30 dnů.', '30 dní'],
                  ['Stav služeb', 'Stránka stavu', 'Výpadky a plánovanou údržbu zveřejňujeme na stránce stavu služeb.', '']
                ] : [
                  ['Customer panel', 'A ticket on the service', 'Every request has a number and its whole history in one place.', 'response per the SLA'],
                  ['Telephone', c.phone || '—', 'For urgent matters; anything else is better as a ticket.', ''],
                  ['Withdrawal and complaints', c.email || '—', 'In writing; a complaint is decided within 30 days.', '30 days'],
                  ['Service status', 'The status page', 'Outages and planned maintenance are published on the service status page.', '']
                ]).map(x => ({ when: x[0], who: x[1], note: x[2], sla: x[3] }));
              })(),
              ppRuleTitle: cs ? 'Na čem se můžete spolehnout' : 'What you can rely on',
              ppRules: (cs ? [
                ['Co slibujeme, je ve smlouvě', 'Dostupnost, reakční doby a kompenzace stanoví SLA; obchodní podmínky a další dokumenty najdete v patičce webu.'],
                ['Reklamace do 30 dnů', 'Reklamaci vyřídíme do 30 dnů od jejího uplatnění.'],
                ['Žádné vymyšlené reference', 'Na webu uvádíme jen reference, se kterými zákazník souhlasil.']
              ] : [
                ['What we promise is in the contract', 'Availability, response times and compensation are set by the SLA; the terms and the other documents are linked in the footer.'],
                ['Complaints within 30 days', 'A complaint is decided within 30 days of being made.'],
                ['No invented references', 'The site shows only references the customer agreed to.']
              ]).map(x => ({ t: x[0], d: x[1] })),

        JS;
    }

    /** @return array<string, string> the knowledge base's help line promised an 18-minute median reply; the blog's a free migration */
    public static function articleHelp(): array
    {
        return [
            "helpLead: isKb ? (cs ? 'Napište podpoře, medián odpovědi je osmnáct minut a odpovídá vám člověk, který ten systém provozuje.' : 'Message support — the median reply is eighteen minutes, from somebody who runs the system.') : (cs ? 'Migraci děláme zdarma a konzultaci k architektuře také. Ozvěte se, projdeme to s vámi.' : 'Migration is free, and so is an architecture review. Get in touch and we will walk through it.')," => "helpLead: isKb ? (cs ? 'Napište podpoře tiketem v klientském panelu; odpovídá člověk z týmu ONhost.' : 'Message support with a ticket in the customer panel; someone from the ONhost team answers.') : (cs ? 'Chcete něco podobného u sebe? Ozvěte se a projdeme to s vámi.' : 'Want something similar? Get in touch and we will walk through it with you.'),",
        ];
    }

    /** I-R17: the content module serves the published posts (`ONHOST_DATA.blog`); the prototype's post list is not even sent. */
    public static function contentModule(string $js): string
    {
        $start = strpos($js, "  const blog = [\n");
        $end = $start === false ? false : strpos($js, "  const kb = [\n", $start);
        if ($start === false || $end === false) {
            return $js;
        }

        return substr($js, 0, $start)
            ."  // I-R17: the blog is what staff publish (window.ONHOST_DATA.blog, generated from the database); the prototype's posts are not served\n"
            ."  const blog = (window.ONHOST_DATA && window.ONHOST_DATA.blog && window.ONHOST_DATA.blog(cs)) || [];\n\n"
            .substr($js, $end);
    }
}
