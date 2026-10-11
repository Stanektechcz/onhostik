<?php

declare(strict_types=1);

namespace Onhost\Domain\Content;

use Onhost\Domain\Content\Models\Post;

/**
 * The blog's first honest posts (I-R17, owner 2026-10-08) and the prototype posts they replace. The prototype's articles name people
 * who do not work here and figures nobody measured (PUE 1,18, 42 Gbps, a battery in Prague, an H100 hour for 39 Kč); they are
 * archived, never deleted, so a member of staff can rewrite one and publish it again. The starter posts state only what the
 * platform, the confirmed prices (H-R7) and the terms in force say — no statistics, no customers, no names beyond the team.
 */
final class StarterPosts
{
    public const AUTHOR = 'Tým ONhost';

    public const SLUGS = ['penpot-ve-webhostingu', 'odstoupeni-a-kredit', 'nez-zmenite-web'];

    /** Slugs of the prototype's posts (database/seeders/data/prototype-content.json), "cены-energie" with its Cyrillic letters as written. */
    public const PROTOTYPE_SLUGS = [
        'pue-118', 'ddos-300', 'postgres-tuning', 'gpu-cena', 'bess-praha', 'migrace-z-cloudu', 'nvme-zivotnost', 'cены-energie', 'ipv6-only',
        'inference-eu', 'onboarding-noc', 'ceny-transparentne',
    ];

    /** Archive every prototype post that is still published; returns how many were archived. */
    public static function retirePrototypePosts(): int
    {
        return Post::query()->whereIn('slug', self::PROTOTYPE_SLUGS)->where('state', 'published')->update(['state' => 'archived']);
    }

    /** Insert the starter posts that do not exist yet (an existing slug, edited by staff or not, is left alone); returns how many were added. */
    public static function publishMissing(): int
    {
        $added = 0;
        foreach (self::all() as $post) {
            if (Post::query()->where('slug', $post['slug'])->exists()) {
                continue;
            }
            Post::query()->create($post + ['kind' => 'blog', 'author' => self::AUTHOR, 'read_minutes' => 3, 'state' => 'published', 'published_on' => '2026-10-08', 'revision' => 1]);
            $added++;
        }

        return $added;
    }

    /** @return list<array{slug:string, featured:bool, category:array<string,string>, title:array<string,string>, excerpt:array<string,string>, body:array<string, list<array{0:string,1:string}>>}> */
    public static function all(): array
    {
        return [
            [
                'slug' => 'penpot-ve-webhostingu', 'featured' => true,
                'category' => ['cs' => 'Novinky', 'en' => 'News'],
                'title' => ['cs' => 'Penpot ve webhostingu: návrhy webu vedle webu samotného', 'en' => 'Penpot in web hosting: your site designs next to the site'],
                'excerpt' => [
                    'cs' => 'Připravujeme Penpot, otevřený nástroj pro návrh webů a aplikací. U webhostingu bude v ceně, k ostatním službám jako doplněk.',
                    'en' => 'We are preparing Penpot, an open-source design tool for websites and apps. Included with web hosting, an add-on elsewhere.',
                ],
                'body' => [
                    'cs' => [
                        ['', 'Penpot je otevřený nástroj pro návrh webů a aplikací. Připravujeme ho jako součást služeb ONhost, aby návrhy vašeho webu mohly ležet na stejném místě jako web samotný a nemusely odcházet do cizího cloudu.'],
                        ['Co bude v ceně', 'U tarifů webhostingu a spravovaného hostingu bude Penpot v ceně. K ostatním službám si ho bude možné přiobjednat jako doplněk za 29 Kč měsíčně. Penpot se vždy objednává ke konkrétní službě a končí spolu s ní.'],
                        ['Kdy ho spustíme', 'Penpot spustíme, až bude připravený a ověřený server, na kterém poběží. Do té doby objednávku Penpotu systém odmítne; kdyby už byla zaplacená, položku vrátíme a dáme vám vědět. Nechceme prodávat službu, kterou ještě neumíme dodat.'],
                        ['Kde se dozvíte víc', 'Spuštění oznámíme tady na blogu a v klientském panelu u služeb, ke kterým Penpot patří.'],
                    ],
                    'en' => [
                        ['', 'Penpot is an open-source tool for designing websites and apps. We are preparing it as part of the ONhost services, so the designs of your site can live in the same place as the site itself instead of someone else\'s cloud.'],
                        ['What will be included', 'Penpot will be included with the web hosting and managed hosting plans. With other services it will be available as an add-on for 29 CZK a month. Penpot is always ordered for a particular service and ends together with it.'],
                        ['When it starts', 'Penpot starts once the server it runs on is ready and verified. Until then the system refuses a Penpot order; should one already be paid, we return the item and let you know. We do not want to sell a service we cannot deliver yet.'],
                        ['Where to learn more', 'We will announce the launch here on the blog and in the customer panel next to the services Penpot belongs to.'],
                    ],
                ],
            ],
            [
                'slug' => 'odstoupeni-a-kredit', 'featured' => false,
                'category' => ['cs' => 'Fakturace', 'en' => 'Billing'],
                'title' => ['cs' => 'Odstoupení od smlouvy a kredit: jak to u nás funguje', 'en' => 'Withdrawal and credit: how it works here'],
                'excerpt' => [
                    'cs' => 'Kdy můžete od smlouvy odstoupit, co zaplatíte za dny, kdy služba běžela, a jak funguje předplacený kredit.',
                    'en' => 'When you may withdraw, what you pay for the days the service ran, and how prepaid credit works.',
                ],
                'body' => [
                    'cs' => [
                        ['', 'Shrnujeme, jak u ONhostu funguje odstoupení od smlouvy a předplacený kredit. Závazné je vždy znění obchodních podmínek; tento článek je jen jejich srozumitelné shrnutí.'],
                        ['Odstoupení do 14 dnů', 'Jste-li spotřebitel, můžete od smlouvy odstoupit do 14 dnů od jejího uzavření. Zaplatíte jen poměrnou část za dobu, kdy služba běžela. Registrovanou doménu vrátit nelze, protože registraci u správce domény nejde vzít zpět.'],
                        ['Kredit', 'Kredit si můžete dobít předem a platit z něj služby ONhost. Kredit se nevyplácí zpět na účet a od dobití kreditu nelze odstoupit.'],
                        ['Kam napsat', 'Odstoupení i reklamaci pošlete na adresu reklamace@onhost.cz. Obchodní podmínky a ostatní dokumenty najdete v patičce webu.'],
                    ],
                    'en' => [
                        ['', 'A summary of how withdrawal and prepaid credit work at ONhost. The terms of service are always what binds; this article only puts them in plain words.'],
                        ['Withdrawal within 14 days', 'As a consumer you may withdraw from the contract within 14 days of concluding it. You pay only for the days the service ran. A registered domain cannot be returned, because a registration with the registry cannot be undone.'],
                        ['Credit', 'You can top up credit in advance and pay ONhost services from it. Credit is not paid out to a bank account and a top-up cannot be withdrawn from.'],
                        ['Where to write', 'Send a withdrawal or a complaint to reklamace@onhost.cz. The terms of service and the other documents are linked in the footer of the website.'],
                    ],
                ],
            ],
            [
                'slug' => 'nez-zmenite-web', 'featured' => false,
                'category' => ['cs' => 'Návody', 'en' => 'Guides'],
                'title' => ['cs' => 'Než změníte web: staging, záloha a bezpečný přístup', 'en' => 'Before you change your site: staging, a backup and safe access'],
                'excerpt' => [
                    'cs' => 'Pár návyků, díky kterým se nepovedená aktualizace nebo úprava nedostane k návštěvníkům webu.',
                    'en' => 'A few habits that keep a failed update or change away from the visitors of your site.',
                ],
                'body' => [
                    'cs' => [
                        ['', 'Aktualizace pluginu, nová verze PHP nebo úprava šablony se občas nepovede. Těchto pár návyků pomůže, aby se taková chyba nedostala k návštěvníkům.'],
                        ['Zkoušejte na kopii', 'Změnu si nejdřív vyzkoušejte na kopii webu (stagingu), ne přímo na ostrém webu. Teprve když kopie funguje, přeneste změnu na web.'],
                        ['Záloha před změnou', 'Před větší změnou si udělejte zálohu souborů i databáze a ověřte, že ji umíte obnovit. Záloha, kterou nikdo nikdy neobnovil, je jen naděje.'],
                        ['Přístup jen pro toho, kdo ho potřebuje', 'Zapněte si k účtu dvoufázové ověření. Členům týmu dejte jen takovou roli, jakou pro svou práci potřebují, a přístup odeberte, jakmile ho nepotřebují.'],
                        ['SSH klíče místo hesel', 'Pro přístup přes SSH používejte klíče, ne hesla, a každý člověk ať má svůj vlastní klíč. Když někdo odejde, odeberete jen jeho klíč.'],
                        ['Aktualizace', 'Redakční systém, šablony a pluginy udržujte aktuální a ty, které nepoužíváte, odinstalujte.'],
                    ],
                    'en' => [
                        ['', 'A plugin update, a new PHP version or a template change sometimes goes wrong. These few habits help keep such a mistake away from your visitors.'],
                        ['Try it on a copy', 'Try a change on a copy of the site (staging) first, not on the live site. Move the change to the site only once the copy works.'],
                        ['A backup before the change', 'Before a bigger change, back up both the files and the database, and make sure you can restore them. A backup nobody ever restored is only a hope.'],
                        ['Access only for those who need it', 'Turn on two-factor authentication for your account. Give team members only the role their work needs, and remove access once they no longer need it.'],
                        ['SSH keys instead of passwords', 'Use keys rather than passwords for SSH, one key per person. When someone leaves, you remove just their key.'],
                        ['Updates', 'Keep the CMS, themes and plugins up to date, and uninstall the ones you do not use.'],
                    ],
                ],
            ],
        ];
    }
}
