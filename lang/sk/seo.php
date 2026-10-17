<?php

declare(strict_types=1);

/*
 * W7-3: the Slovak head texts, used only under /sk/ while config('onhost.locale_sk') is on (PublicSeo::head; a missing line falls
 * back to lang/cs/seo.php). Návrh, čeká na schválení vlastníkem (W7-K4). Bez nových tvrdení a bez cien.
 */

return [
    'pages' => [
        'home' => ['title' => 'ONhost – webhosting, gamehosting a servery', 'description' => 'ONhost: webhosting, gamehosting a servery s cenníkom, dokumentáciou a stavom služieb na jednom mieste.', 'crumb' => 'ONhost', 'site_name' => 'ONhost'],
        'sluzby' => ['title' => 'Služby | ONhost', 'description' => 'Prehľad služieb ONhost.', 'crumb' => 'Služby'],
        'sluzba' => ['title' => 'Služba | ONhost', 'description' => 'Popis služby ONhost.', 'crumb' => 'Služba'],
        'ceny' => ['title' => 'Cenník | ONhost', 'description' => 'Cenník služieb ONhost.', 'crumb' => 'Cenník'],
        'ceny-a-sla' => ['title' => 'Ceny a SLA | ONhost', 'description' => 'Ceny a podmienky SLA služieb ONhost.', 'crumb' => 'Ceny a SLA'],
        'webhosting' => ['title' => 'Webhosting | ONhost', 'description' => 'Webhosting ONhost.', 'crumb' => 'Webhosting'],
        'gamehosting' => ['title' => 'Gamehosting | ONhost', 'description' => 'Gamehosting ONhost.', 'crumb' => 'Gamehosting'],
        'technika' => ['title' => 'Technika | ONhost', 'description' => 'Technika, na ktorej ONhost prevádzkuje svoje služby.', 'crumb' => 'Technika'],
        'jak-fungujeme' => ['title' => 'Ako fungujeme | ONhost', 'description' => 'Ako ONhost funguje.', 'crumb' => 'Ako fungujeme'],
        'blog' => ['title' => 'Blog | ONhost', 'description' => 'Články ONhost.', 'crumb' => 'Blog'],
        'znalostni-baze' => ['title' => 'Znalostná báza | ONhost', 'description' => 'Návody a odpovede k službám ONhost.', 'crumb' => 'Znalostná báza'],
        'napoveda' => ['title' => 'Pomocník | ONhost', 'description' => 'Pomocník k službám ONhost.', 'crumb' => 'Pomocník'],
        'dokumentace' => ['title' => 'Dokumentácia | ONhost', 'description' => 'Dokumentácia služieb ONhost.', 'crumb' => 'Dokumentácia'],
        'api' => ['title' => 'API | ONhost', 'description' => 'Dokumentácia API ONhost.', 'crumb' => 'API'],
        'stav' => ['title' => 'Stav služieb | ONhost', 'description' => 'Aktuálny stav služieb ONhost.', 'crumb' => 'Stav služieb'],
        'zmeny' => ['title' => 'Zmeny | ONhost', 'description' => 'Prehľad zmien ONhost.', 'crumb' => 'Zmeny'],
        'lide' => ['title' => 'Ľudia | ONhost', 'description' => 'Ľudia za ONhost.', 'crumb' => 'Ľudia'],
        'reseller' => ['title' => 'Reseller | ONhost', 'description' => 'Partnerský program ONhost pre resellerov.', 'crumb' => 'Reseller'],
        'verejne-zakazky' => ['title' => 'Verejné zákazky | ONhost', 'description' => 'ONhost pre verejné zákazky.', 'crumb' => 'Verejné zákazky'],
        'dokumenty' => ['title' => 'Dokumenty | ONhost', 'description' => 'Obchodné podmienky a ďalšie právne dokumenty ONhost.', 'crumb' => 'Dokumenty'], // the documents stay Czech only (no /sk/ twin); the key keeps lang/sk key-for-key
        'kosik' => ['title' => 'Košík | ONhost', 'description' => 'Košík ONhost.', 'crumb' => 'Košík'],
        'prihlaseni' => ['title' => 'Prihlásenie | ONhost', 'description' => 'Prihlásenie do ONhost.', 'crumb' => 'Prihlásenie'],
        'registrace' => ['title' => 'Registrácia | ONhost', 'description' => 'Registrácia do ONhost.', 'crumb' => 'Registrácia'],
        'obnova-hesla' => ['title' => 'Obnova hesla | ONhost', 'description' => 'Obnova hesla k účtu ONhost.', 'crumb' => 'Obnova hesla'],
    ],
];
