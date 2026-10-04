<?php

declare(strict_types=1);

/*
 * TASK-0043 (program oprávnění S1-03, rozhodnutí #20): co bude člověk s každým oprávněním z PermissionCatalog moci dělat, lidsky —
 * `can` (může), a kde na tom záleží `cannot` (nemůže) a `warning` (pozor). Čte se přes CapabilityMatrix::sentence();
 * CapabilityMatrixTest hlídá řádek pro každý klíč katalogu a varování u každého oprávnění HIGH/CRITICAL a u všeho, co spouští
 * kód nebo maže data. Anglicky: lang/en/permissions.php.
 */

return [
    // ── organizace a lidé ──
    'organization.read' => ['can' => 'Vidí organizaci, její členy a projekty.'],
    'organization.manage' => ['can' => 'Mění profil a nastavení organizace.', 'cannot' => 'Přidávat ani odebírat lidi.'],
    'organization.members.manage' => ['can' => 'Zve lidi, mění, co smějí, a odebírá je.', 'cannot' => 'Dát nikomu víc, než má sám.', 'warning' => 'Rozhoduje, kdo se do organizace dostane; každá změna chce čerstvé ověření totožnosti.'],
    'organization.close' => ['can' => 'Zruší organizaci a naplánuje smazání jejích dat.', 'warning' => 'Ukončí všechny služby a po ochranné lhůtě smaže data; má ho jen vlastník.'],
    'project.manage' => ['can' => 'Zakládá a přejmenovává projekty.'],
    'api_token.manage' => ['can' => 'Vytváří a ruší API klíče a servisní účty.', 'warning' => 'Klíč funguje i bez člověka u klávesnice; jedná v rozsahu, který dostal, dokud ho někdo nezruší nebo nevyprší.'],
    'security.settings.manage' => ['can' => 'Nastavuje pravidla dvoufázového ověření, povolené IP adresy a přihlášení.', 'warning' => 'Může lidi z organizace zamknout, nebo ji otevřít dalším místům.'],
    'audit.read' => ['can' => 'Čte auditní záznam organizace: kdo co a kdy udělal.'],
    'data_export.request' => ['can' => 'Požádá o přenositelný export dat organizace.'],

    // ── fakturace ──
    'billing.wallet.read' => ['can' => 'Vidí kredit účtu, blokace a čerpání.'],
    'billing.wallet.topup' => ['can' => 'Dobíjí kredit a nastavuje automatické dobíjení.'],
    'billing.invoice.read' => ['can' => 'Vidí a stahuje faktury, dobropisy a doklady a čte tikety o fakturaci.'],
    'billing.payment_method.manage' => ['can' => 'Přidává a odebírá uložené platební karty a metody.', 'warning' => 'Rozhoduje, z jaké karty organizace platí.'],
    'billing.budget.manage' => ['can' => 'Nastavuje rozpočty, limity útraty a upozornění.'],
    'catalog.order.create' => ['can' => 'Objednává služby a mění tarify.', 'cannot' => 'Platit z kreditu účtu bez vlastníka nebo správce fakturace.'],
    'billing.wallet.spend' => ['can' => 'Platí z kreditu účtu a schvaluje objednávky z kreditu ostatních.', 'warning' => 'Utrácí peníze organizace.'],

    // ── služby ──
    'service.read' => ['can' => 'Vidí službu, její stav, metriky, logy a aktivitu.', 'cannot' => 'Na ní cokoli měnit.'],
    'service.operate' => ['can' => 'Udržuje službu v chodu: zapne, vypne a restartuje ji, přepne verzi PHP, vyprázdní cache, vystaví certifikát a vynutí HTTPS.', 'cannot' => 'Upravovat soubory, přidávat cron, zakládat databáze ani přístupy, otevřít terminál ani cokoli mazat.'],
    'service.manage' => ['can' => 'Mění službu: soubory, cron, databáze, FTP a databázové přístupy, nasazení, aplikace, schránky a nastavení.', 'cannot' => 'Mazat zálohy ani otevřít terminál.', 'warning' => 'Spouští na službě kód: soubor nebo cron přečte hesla, která na ní jsou. Zahrnuje i mazání webů, databází, souborů a schránek.'],
    'service.data.delete' => ['can' => 'Maže data uvnitř služby: weby, databáze, soubory, schránky, herní databáze a soubory, stagingové kopie.', 'cannot' => 'Mazat zálohy ani zrušit službu.', 'warning' => 'Smazané ze služby zmizí hned; vrátit ho umí jen záloha.'],
    'service.delete' => ['can' => 'Zruší službu (po ochrannou lhůtu jde obnovit).', 'warning' => 'Služba přestane fungovat všem; po ochranné lhůtě se její data smažou.'],
    'service.console' => ['can' => 'Otevře terminál, SSH, konzoli VNC nebo hry, nastaví root přístup a SSH klíče, záchranný režim a herní podúčty.', 'warning' => 'Plná vláda nad serverem: kdo ji má, přečte na něm každé heslo a může si nechat vlastní cestu dovnitř.'],
    'service.credentials.rotate' => ['can' => 'Vymění hesla a klíče služby.', 'warning' => 'Vše, co používalo stará hesla, přestane fungovat, dokud nedostane nová.'],
    'service.panel_account.manage' => ['can' => 'Nastaví heslo k účtu herního panelu (jen vlastník).', 'warning' => 'Účet panelu otevírá všechny herní servery účtu, nejen tento.'],
    'compute.vm.manage' => ['can' => 'Ovládá virtuální servery: napájení, změnu velikosti, snapshoty, firewall.'],
    'compute.vm.delete' => ['can' => 'Maže virtuální servery a jejich snapshoty.', 'warning' => 'Smazaný snapshot už nejde vrátit.'],
    'apps.deploy' => ['can' => 'Nasazuje, vrací a nastavuje aplikace.'],
    'game.manage' => ['can' => 'Spravuje herní servery, módy, plány a herní zálohy.'],
    'mail.manage' => ['can' => 'Spravuje poštovní domény, schránky, aliasy a přeposílání.'],
    'database.manage' => ['can' => 'Spravuje spravované databáze a jejich uživatele.'],
    'backup.read' => ['can' => 'Vidí, jaké zálohy a body obnovy existují.', 'cannot' => 'Stáhnout je ani z nich obnovit.'],
    'backup.download' => ['can' => 'Stahuje archivy záloh a exporty dat.', 'warning' => 'Archiv obsahuje soubory webu i s hesly (wp-config.php, .env).'],
    'backup.restore' => ['can' => 'Obnoví službu ze zálohy.', 'warning' => 'Přepíše současný stav služby starším.'],
    'backup.delete' => ['can' => 'Maže generace záloh.', 'warning' => 'Smazaná záloha je pryč natrvalo; po chybě může být jedinou cestou zpět.'],

    // ── domény a DNS (pro celou organizaci, výchozí volba vlastníka O7) ──
    'domain.read' => ['can' => 'Vidí domény, jejich expiraci a stav u registrátora.'],
    'domain.manage' => ['can' => 'Registruje a prodlužuje domény, nastavuje automatické prodloužení a kontakty.'],
    'domain.transfer_out.execute' => ['can' => 'Zobrazí převodní kód (AUTH-ID) a převede doménu k jinému registrátorovi.', 'warning' => 'Doménu převedenou pryč už sami nevrátíme.'],
    'domain.registrant.change' => ['can' => 'Změní držitele domény.', 'warning' => 'Nový držitel se stane právním majitelem domény.'],
    'dns.zone.read' => ['can' => 'Vidí DNS zóny a jejich historii.'],
    'dns.zone.write' => ['can' => 'Upravuje DNS záznamy a publikuje změny.', 'cannot' => 'Zapnout ani vypnout DNSSEC.'],
    'dns.dnssec.manage' => ['can' => 'Zapíná a vypíná DNSSEC a mění jeho klíče.', 'warning' => 'Chyba udělá doménu nedostupnou, dokud registr nezapomene starý klíč.'],

    // ── podpora ──
    'support.ticket.read' => ['can' => 'Čte tikety podpory ke službám a projektům, o které se stará.', 'cannot' => 'Číst tikety o fakturaci bez práva vidět faktury.'],
    'support.ticket.write' => ['can' => 'Zakládá tikety podpory a odpovídá na ně.'],
    'support.chat.use' => ['can' => 'Používá AI asistenta a živý chat.'],

    // ── partnerský portál ──
    'partner.portal.read' => ['can' => 'Vidí partnerský portál: klienty, provize, výplaty a výplatní účet.'],
    'partner.payout_account.manage' => ['can' => 'Nastaví bankovní účet, na který chodí provize (jen vlastník).', 'warning' => 'Rozhoduje, kam jdou peníze; nový účet se použije až po ochranné lhůtě.'],

    // ── personál: zákazníci a provoz (jen zaměstnanci ONhost; žádná zákaznická role je nemá) ──
    'staff.customer.read' => ['can' => 'Personál: vidí přehled zákazníka — organizace, služby, fakturaci a tikety.'],
    'staff.customer.manage' => ['can' => 'Personál: upravuje zákaznické organizace a členství.', 'warning' => 'Mění, kdo se k zákaznickému účtu dostane.'],
    'staff.order.manage' => ['can' => 'Personál: posouvá objednávky a spouští zřízení.'],
    'staff.service.manage' => ['can' => 'Personál: pozastaví, obnoví a změní velikost kterékoli zákaznické služby.'],
    'staff.service.delete' => ['can' => 'Personál: zruší kteroukoli zákaznickou službu, i předčasně nebo bez závěrečného archivu.', 'warning' => 'Odstraní zákaznickou službu i s daty; potřebuje druhou osobu.'],
    'staff.console' => ['can' => 'Personál: otevře konzoli zákaznického serveru (nahrává se, váže se k tiketu).', 'warning' => 'Plná vláda nad zákaznickým serverem.'],
    'support.customer_impersonate' => ['can' => 'Personál: vidí portál tak, jak ho vidí zákazník.', 'warning' => 'Jedná v zákaznickém účtu; každý krok se zapisuje do auditu.'],
    'staff.inbox.read' => ['can' => 'Personál: čte vlastní schránku upozornění — upozornění určená jemu.', 'cannot' => 'Interní upozornění o zákaznících nevidí; ta vyžadují přehled zákazníka.'],
    'staff.chargeback.decide' => ['can' => 'Personál: rozhodne žádost zákazníka o vrácení kreditu za předčasně zrušenou službu.', 'cannot' => 'Výši vraceného podílu nemění; tu nastavuje finanční oddělení.'],

    // ── personál: zřizování a poskytovatelé ──
    'provisioning.operation.read' => ['can' => 'Personál: vidí operace, úlohy a volání poskytovatelů.'],
    'provisioning.operation.retry' => ['can' => 'Personál: zopakuje neúspěšné operace.'],
    'provisioning.operation.cancel' => ['can' => 'Personál: zruší běžící operace.', 'warning' => 'Zrušená operace může nechat napůl vytvořený zdroj k úklidu.'],
    'provisioning.drift.resolve' => ['can' => 'Personál: schválí nebo opraví odchylku konfigurace.', 'warning' => 'Přepíše stav panelu tím, co platforma očekává.'],
    'provisioning.freeze' => ['can' => 'Personál: zmrazí veškeré automatické zřizování (havarijní vypínač).', 'warning' => 'Zastaví každou objednávku a změnu všem zákazníkům, dokud se nezruší.'],
    'provider.instance.read' => ['can' => 'Personál: vidí instance poskytovatelů, jejich schopnosti a zdraví.'],
    'provider.instance.manage' => ['can' => 'Personál: registruje a upravuje instance poskytovatelů.', 'warning' => 'Nasměruje platformu na panel; špatný panel dosáhne na špatné servery.'],
    'provider.secret.view' => ['can' => 'Personál: zobrazí nebo vymění přístupové údaje poskytovatelů.', 'warning' => 'Klíče platformy k panelům; potřebuje druhou osobu.'],
    'capacity.read' => ['can' => 'Personál: vidí kapacitu, rezervy a inventář.'],
    'capacity.manage' => ['can' => 'Personál: schvaluje, objednává a uzavírá požadavky na kapacitu.', 'warning' => 'Utrácí peníze za hardware.'],
    'node.manage' => ['can' => 'Personál: dává uzly do údržby, vyprazdňuje je a uzavírá.', 'warning' => 'Přesouvá nebo zastavuje zákaznické služby na uzlu.'],
    'ipam.manage' => ['can' => 'Personál: spravuje rozsahy IP adres, přidělení a reverzní DNS.', 'warning' => 'Špatná adresa odpojí zákaznický server od sítě.'],
    'backup.policy.manage' => ['can' => 'Personál: upravuje pravidla a dobu uchování záloh.', 'warning' => 'Kratší doba uchování smaže starší zálohy.'],

    // ── personál: domény a DNS ──
    'domain.registrar.manage' => ['can' => 'Personál: obsluhuje frontu registrátora, kontakty, NSSETy a kredit.', 'warning' => 'Jedná u registru za zákaznické domény.'],
    'dns.global.write' => ['can' => 'Personál: mění DNS infrastruktury ONhost a jmenné servery.', 'warning' => 'Závisí na tom každá zákaznická zóna; potřebuje druhou osobu.'],
    'domain.critical.manage' => ['can' => 'Personál: mění kritické domény ve vlastnictví ONhost.', 'warning' => 'Adresy samotné platformy; potřebuje druhou osobu.'],

    // ── personál: finance ──
    'billing.invoice.manage' => ['can' => 'Personál: vystavuje, opravuje a znovu posílá faktury.'],
    'billing.refund.execute' => ['can' => 'Personál: posílá vratky peněz.', 'warning' => 'Peníze odcházejí z ONhost.'],
    'billing.refund.execute_large' => ['can' => 'Personál: posílá vratky nad limit schválení.', 'warning' => 'Peníze odcházejí z ONhost; potřebuje druhou osobu.'],
    'billing.credit.adjust' => ['can' => 'Personál: ručně upraví kredit zákazníka.', 'warning' => 'Přidává nebo bere peníze na účtu.'],
    'billing.credit.adjust_mass' => ['can' => 'Personál: upraví kredit mnoha zákazníkům naráz.', 'warning' => 'Hýbe penězi na mnoha účtech; potřebuje druhou osobu.'],
    'billing.tax_rule.manage' => ['can' => 'Personál: upravuje daňová pravidla a registrace.', 'warning' => 'Změní daň na každé další faktuře; potřebuje druhou osobu.'],
    'billing.reconcile' => ['can' => 'Personál: spouští a řeší párování plateb.'],
    'billing.dunning.manage' => ['can' => 'Personál: spouští upomínky, pozastavuje a obnovuje služby kvůli neplacení.', 'warning' => 'Zastaví nebo znovu spustí zákaznickou službu.'],
    'billing.credit_line.manage' => ['can' => 'Personál: schvaluje odložené platby (úvěrové rámce).', 'warning' => 'Dovolí zákazníkovi utrácet před zaplacením.'],
    'report.read' => ['can' => 'Personál: vidí finanční a provozní přehledy.'],
    'billing.limit_raise.waive' => ['can' => 'Personál: povolí navýšení limitu na jedno období zdarma.', 'warning' => 'Daruje to, co by se jinak účtovalo; potřebuje druhou osobu.'],
    'staff.billing.read' => ['can' => 'Personál: vidí faktury, odstoupení a fakturační záznamy všech zákazníků.'],

    // ── personál: podpora a incidenty ──
    'staff.support.ticket.read' => ['can' => 'Personál: čte frontu podpory a tikety všech zákazníků.'],
    'staff.backup.read' => ['can' => 'Personál: vidí zálohy a body obnovy zákaznických služeb.'],
    'support.ticket.assign' => ['can' => 'Personál: přiděluje a směruje tikety.'],
    'support.ticket.manage' => ['can' => 'Personál: odpovídá na tikety, eskaluje je a uzavírá.'],
    'support.queue.manage' => ['can' => 'Personál: spravuje fronty, pravidla SLA a makra.'],
    'support.kb.manage' => ['can' => 'Personál: upravuje články znalostní báze.'],
    'incident.manage' => ['can' => 'Personál: zakládá, aktualizuje a uzavírá incidenty.'],
    'incident.publish' => ['can' => 'Personál: zveřejňuje incidenty na veřejné stavové stránce.', 'warning' => 'Přečte si to kdokoli.'],
    'maintenance.manage' => ['can' => 'Personál: plánuje servisní okna.', 'warning' => 'Zákazníci dostanou oznámení a služby se v okně mohou zastavit.'],
    'sla.credit.manage' => ['can' => 'Personál: schvaluje kompenzace za SLA.', 'warning' => 'Vrací zákazníkům peníze jako kredit.'],
    'notification.template.manage' => ['can' => 'Personál: upravuje šablony oznámení.'],
    'notification.mass.send' => ['can' => 'Personál: posílá hromadná oznámení.', 'warning' => 'Dorazí všem zákazníkům naráz; nejde vzít zpět.'],

    // ── personál: bezpečnost, compliance, přístupy ──
    'security.incident.manage' => ['can' => 'Personál: řeší bezpečnostní incidenty, karanténu a forenzní šetření.', 'warning' => 'Může zákaznickou službu zastavit.'],
    'security.event.read' => ['can' => 'Personál: čte bezpečnostní události a anomálie.'],
    'abuse.case.manage' => ['can' => 'Personál: řeší případy zneužití a DSA.', 'warning' => 'Může zákaznický obsah stáhnout.'],
    'compliance.case.manage' => ['can' => 'Personál: vede regulatorní případy (GDPR, NIS2, Data Act).', 'warning' => 'Na těchto případech běží zákonné lhůty.'],
    'compliance.legal_hold.manage' => ['can' => 'Personál: nařizuje a ruší právní blokace (legal hold).', 'warning' => 'Zrušená blokace dovolí data smazat; potřebuje druhou osobu.'],
    'iam.user.manage' => ['can' => 'Personál: spravuje účty zaměstnanců.', 'warning' => 'Rozhoduje, kdo pracuje jako personál ONhost.'],
    'iam.role.manage' => ['can' => 'Personál: zakládá role personálu a mění, co role smějí.', 'warning' => 'Mění, co smí každý s danou rolí; potřebuje druhou osobu.'],
    'iam.mfa.reset' => ['can' => 'Personál: resetuje druhý faktor jiného člověka.', 'warning' => 'Klasická cesta k převzetí účtu; vlastníkům organizací jen přes týdenní obnovu.'],
    'iam.jit.request' => ['can' => 'Personál: požádá o dočasné navýšení vlastních práv.'],
    'iam.jit.approve' => ['can' => 'Personál: schvaluje dočasná navýšení práv.', 'warning' => 'Dá jinému člověku na čas víc moci.'],
    'iam.approval.decide' => ['can' => 'Personál: schvaluje nebo zamítá, co potřebuje druhou osobu.', 'warning' => 'Druhý zámek na každé kritické akci.'],
    'iam.access_review.manage' => ['can' => 'Personál: provádí čtvrtletní revize přístupů.'],
    'iam.break_glass' => ['can' => 'Personál: nouzový přístup ke všemu.', 'warning' => 'Všechno naráz; každé použití upozorní všechny správce.'],
    'secret.rotate' => ['can' => 'Personál: mění kritická tajemství.', 'warning' => 'Vše, co používá staré tajemství, se zastaví; potřebuje druhou osobu.'],
    'audit.read.global' => ['can' => 'Personál: čte auditní záznam celé platformy.'],
    'ai.policy.manage' => ['can' => 'Personál: určuje, co smějí AI nástroje, jejich zadání a hodnocení.', 'warning' => 'Mění, co smí asistent dělat v zákaznických účtech.'],
    'ai.ops.read' => ['can' => 'Personál: čte běhy a hodnocení AI.'],
    'content.manage' => ['can' => 'Personál: upravuje veřejný obsah, přehled změn a dokumentaci.'],
    'catalog.manage' => ['can' => 'Personál: upravuje produkty, tarify a ceny.', 'warning' => 'Mění, co se zákazníkům nabízí a účtuje; změna ceny potřebuje druhou osobu.'],
    'partner.manage' => ['can' => 'Personál: spravuje partnery, provize a výplaty.', 'warning' => 'Rozhoduje, kolik partneři dostanou.'],
    'feature_flag.manage' => ['can' => 'Personál: zapíná a vypíná funkce.', 'warning' => 'Změní platformu všem zákazníkům naráz.'],
];
