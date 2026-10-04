<?php

declare(strict_types=1);

/*
 * TASK-0043 (program oprávnění S1-03, D4): slova uzavřené matice rodina × úroveň (CapabilityMatrix) a zaškrtávátek sdílení
 * jedné služby (ServiceAccessService::CAPABILITIES) — co bude člověk moci, co ne a co vědět, než mu to dáte.
 * CapabilityMatrixTest hlídá větu u každé nabízené buňky a důvod u každé zavřené.
 */

return [
    'families' => ['web' => 'Webhosting', 'mail' => 'E-mail', 'dns' => 'Domény a DNS', 'compute' => 'Virtuální servery', 'game' => 'Herní servery', 'database' => 'Databáze', 'apps' => 'Aplikace'],
    'levels' => ['view' => 'Zobrazení', 'operate' => 'Provoz', 'manage' => 'Správa', 'console' => 'Konzole', 'data_delete' => 'Mazání dat'],

    'cells' => [
        'web' => [
            'view' => ['can' => 'Vidí web: stav, metriky, logy a seznam záloh.', 'cannot' => 'Cokoli měnit.'],
            'operate' => ['can' => 'Udržuje web v chodu: restart, přepnutí verze PHP, vyprázdnění cache a CDN, vystavení certifikátu, vynucení HTTPS.', 'cannot' => 'Upravovat soubory, přidávat cron, zakládat databáze a FTP přístupy, nasazovat, otevřít terminál ani cokoli mazat.'],
            'manage' => ['can' => 'Vše na webu: soubory, cron, databáze, FTP, nasazení, WordPress, schránky a nastavení.', 'cannot' => 'Mazat zálohy ani otevřít terminál.', 'warning' => 'Spouští na webu kód a přečte jeho hesla; zahrnuje i mazání webů, databází, souborů a schránek.'],
            'console' => ['can' => 'Správa a navíc terminál, SSH účty a klíče.', 'warning' => 'Plná vláda nad hostingovým účtem: přečte na něm každé heslo a může si nechat vlastní cestu dovnitř.'],
            'data_delete' => ['can' => 'Maže data uvnitř webu: weby, databáze, soubory, schránky, stagingové kopie.', 'cannot' => 'Mazat zálohy, zrušit službu ani nic jiného měnit.', 'warning' => 'Smazaná data z webu zmizí hned; vrátit je umí jen záloha.'],
        ],
        'mail' => [
            'view' => ['can' => 'Vidí poštovní doménu, její schránky a jejich zaplnění.', 'cannot' => 'Cokoli měnit ani číst poštu.'],
            'operate' => ['can' => 'Udržuje poštu v chodu: restart, kde to tarif umí, a vystavení certifikátů.', 'cannot' => 'Zakládat, měnit ani mazat schránky, aliasy nebo přeposílání.'],
            'manage' => ['can' => 'Zakládá a mění schránky, aliasy, přeposílání, filtry, pravidla spamu a konference a maže je.', 'warning' => 'Heslo schránky nebo přeposílání mu dovolí číst poštu; zahrnuje i mazání schránek.'],
            'data_delete' => ['can' => 'Maže schránky.', 'cannot' => 'Zakládat ani měnit schránky.', 'warning' => 'Smazaná schránka vezme poštu s sebou.'],
        ],
        'dns' => [
            'view' => ['can' => 'Vidí domény a DNS zóny a zbytek organizace jen pro čtení: služby, faktury a tikety.', 'warning' => 'Domény patří celé organizaci: je to role čtenáře organizace, ne pohled na jednu doménu.'],
            'operate' => ['can' => 'Upravuje DNS záznamy a DNSSEC všech domén organizace; organizaci vidí jen pro čtení.', 'cannot' => 'Registrovat, převádět ani prodlužovat domény.', 'warning' => 'Špatný záznam nebo klíč DNSSEC odřízne web i poštu domény od internetu.'],
            'manage' => ['can' => 'Registruje, prodlužuje a převádí domény, mění kontakty a držitele, upravuje DNS záznamy.', 'cannot' => 'Zapnout ani vypnout DNSSEC.', 'warning' => 'Doménu převedenou pryč nebo s novým držitelem už sami nevrátíme.'],
        ],
        'compute' => [
            'view' => ['can' => 'Vidí server: stav, metriky a seznam záloh a snapshotů.', 'cannot' => 'Cokoli měnit.'],
            'operate' => ['can' => 'Zapne, vypne a restartuje server.', 'cannot' => 'Měnit velikost, přeinstalovat, měnit firewall, otevřít konzoli ani cokoli mazat.'],
            'manage' => ['can' => 'Vše na serveru kromě konzole: změna velikosti, přeinstalace, firewall, reverzní DNS, snapshoty a zálohy.', 'warning' => 'Přeinstalace server vymaže (chce čerstvé ověření totožnosti).'],
            'console' => ['can' => 'Správa a navíc konzole VNC, root heslo a SSH klíče a záchranný režim.', 'warning' => 'Plná vláda nad serverem: přečte na něm každé heslo a může si nechat vlastní cestu dovnitř.'],
        ],
        'game' => [
            'view' => ['can' => 'Vidí herní server: stav, metriky a seznam záloh.', 'cannot' => 'Cokoli měnit.'],
            'operate' => ['can' => 'Zapne, vypne a restartuje herní server.', 'cannot' => 'Měnit soubory, módy, spouštěcí proměnné ani plány, otevřít konzoli ani cokoli mazat.'],
            'manage' => ['can' => 'Soubory, módy, spouštěcí proměnné, plány, databáze a porty, i jejich mazání.', 'cannot' => 'Otevřít konzoli ani přidat podúčty panelu.', 'warning' => 'Módy a soubory spouštějí na herním serveru kód.'],
            'console' => ['can' => 'Správa a navíc herní konzole, její podúčty a plány s příkazy konzole.', 'warning' => 'Plná vláda nad herním serverem; podúčet si drží vlastní přístup, dokud ho někdo neodebere.'],
            'data_delete' => ['can' => 'Maže herní databáze a herní soubory.', 'cannot' => 'Mazat herní zálohy ani nic jiného měnit.', 'warning' => 'Smazaná herní data zmizí hned; vrátit je umí jen záloha.'],
        ],
        'database' => [
            'view' => ['can' => 'Vidí databázovou službu: stav, metriky a zálohy.', 'cannot' => 'Číst ani měnit data.'],
            'operate' => ['can' => 'Zapne, vypne a restartuje databázovou službu.', 'cannot' => 'Zakládat databáze a uživatele, exportovat a importovat data ani cokoli mazat.'],
            'manage' => ['can' => 'Zakládá databáze a uživatele, exportuje a importuje data, mění nastavení, maže databáze.', 'warning' => 'Čte a mění každý řádek každé databáze.'],
            'data_delete' => ['can' => 'Maže databáze.', 'cannot' => 'Zakládat databáze ani číst jejich data.', 'warning' => 'Smazaná databáze zmizí hned; vrátit ji umí jen záloha.'],
        ],
        'apps' => [
            'view' => ['can' => 'Vidí aplikaci: stav, metriky a nasazení.', 'cannot' => 'Cokoli měnit.'],
            'operate' => ['can' => 'Restartuje aplikaci a vyprázdní její cache.', 'cannot' => 'Nasazovat, vracet nasazení ani měnit konfiguraci.'],
            'manage' => ['can' => 'Nasazuje, vrací a nastavuje aplikaci.', 'warning' => 'Nasazení spouští nový kód s tajemstvími aplikace.'],
        ],
    ],

    'reasons' => [
        'no_console' => 'Tento druh služby žádnou konzoli k předání nemá.',
        'no_data_objects' => 'Uvnitř tohoto druhu služby se nic samostatně nemaže; její kopie zůstávají vlastníkovi.',
        'domain_no_delete' => 'Domény se rolí nemažou; převod domény pryč je součástí Správy, s varováním.',
    ],

    // zaškrtávátka sdílení JEDNÉ služby (panel → služba → Přístupy)
    'capabilities' => [
        'view' => ['label' => 'zobrazení', 'can' => 'Vidí službu: stav, metriky, logy a seznam záloh.', 'cannot' => 'Cokoli měnit.'],
        'operate' => ['label' => 'provoz (restart, PHP, cache, certifikáty)', 'can' => 'Udržuje službu v chodu: restart, verze PHP, cache, certifikáty, HTTPS.', 'cannot' => 'Upravovat soubory, přidávat cron, zakládat databáze a přístupy, otevřít terminál ani cokoli mazat.'],
        'manage' => ['label' => 'správa a nastavení', 'can' => 'Mění službu: soubory, cron, databáze, přístupy, nasazení, schránky a nastavení a maže je.', 'cannot' => 'Mazat zálohy ani otevřít terminál.', 'warning' => 'Spouští na službě kód a přečte její hesla.'],
        'console' => ['label' => 'konzole a terminál', 'can' => 'Terminál, SSH klíče a root přístup, záchranný režim, VNC, herní konzole a její podúčty.', 'warning' => 'Plná vláda nad serverem.'],
        'data_delete' => ['label' => 'mazání dat', 'can' => 'Maže weby, databáze, soubory, schránky a herní data uvnitř služby.', 'cannot' => 'Mazat zálohy ani zrušit službu.', 'warning' => 'Smazaná data zmizí hned; vrátit je umí jen záloha.'],
        'backups' => ['label' => 'stahování záloh', 'can' => 'Stahuje archivy záloh.', 'warning' => 'Archiv obsahuje soubory i s hesly.'],
        'restore' => ['label' => 'obnova ze zálohy', 'can' => 'Obnoví službu ze zálohy.', 'warning' => 'Přepíše současný stav služby.'],
        'assistant' => ['label' => 'AI asistent', 'can' => 'Používá AI asistenta k této službě.'],
    ],
];
