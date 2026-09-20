<?php

declare(strict_types=1);

namespace Onhost\Domain\Notifications;

/**
 * In-app notifications in the organization's language (audit §5q-7). The router composes Czech titles and bodies
 * from fixed phrases and live values (names, amounts, dates); for an English organization the fixed phrases are
 * swapped by this table — longest phrase first, values untouched — before the row is written. Mail templates already
 * carry their own `en` versions (NotificationTemplateSeeder); this covers the feed and the mobile inbox. A phrase
 * missing here stays Czech (never garbled), and `untranslated()` lists what a test render left behind.
 */
final class Lexicon
{
    /** @var array<string,string> Czech phrase → English */
    public const EN = [
        // orders and provisioning
        'Zaplacená objednávka prochází krátkou kontrolou; služby zřídíme hned po jejím dokončení, obvykle do pár hodin.' => 'Your paid order is going through a short check; we will provision the services right after it, usually within a few hours.',
        'Služby se právě zřizují.' => 'Your services are being provisioned.',
        'Platba byla vrácena, kredit uvolněn. Napište podpoře, pokud jde o omyl.' => 'The payment was returned and the credit released. Contact support if this is a mistake.',
        'Objednávka přijata' => 'Order received', 'Objednávka zaplacena' => 'Order paid', 'Objednávka je hotová' => 'Order completed',
        'objednávka zrušena, nic k úhradě' => 'order cancelled, nothing to pay', ' · služby se zřizují' => ' · services are being provisioned', ' · splatnost 14 dní' => ' · due in 14 days',
        'Platba přijata, vše v pořádku' => 'Payment received, all good', 'Platba přijata' => 'Payment received',
        'Služba je aktivní' => 'Your service is active',
        'Zřizování služby se nezdařilo — řešíme' => 'Provisioning failed — we are on it', 'Zřizování služby selhalo' => 'Provisioning failed', 'Naši technici byli upozorněni, ozveme se v tiketu.' => 'Our engineers have been alerted; we will follow up in a ticket.',
        'Služba byla pozastavena pro neplacení' => 'Service suspended for non-payment', 'Služba pozastavena pro neplacení' => 'Service suspended for non-payment', 'Po uhrazení se služba automaticky obnoví.' => 'The service resumes automatically once paid.',
        'Zálohu máme hotovou. Obnovit službu můžete do ' => 'The backup is complete. You can restore the service within ', ' dnů. Data uchováme dalších ' => ' days. The data is kept for another ',
        ': spolupracovníky se nepodařilo přenést' => ': collaborators could not be carried over', 'Na nový server se nepřenesli spolupracovníci: ' => 'Collaborators not carried to the new server: ', '. Jejich práva by se při přenosu změnila, proto jsme je nepřidali. Přidejte je znovu v nastavení serveru.' => '. Their permissions would have changed on the way, so we did not add them. Add them again in the server settings.',
        'Dočasný přístup skončil' => 'A temporary access has ended', 'Přístup skončil k datu, které jste nastavili: ' => 'The access ended on the date you set: ', ' · projekt ' => ' · project ', '. Účty spolupracovníka a SSH klíče této osoby rušíme.' => '. We are removing this person\'s collaborator accounts and SSH keys.',
        'Rušíme SSH klíče odebraného člena' => 'We are removing the SSH keys of a removed member', 'Odebraný člen měl na vašich webech SSH klíče, které teď rušíme: ' => 'The removed member had SSH keys on your sites; we are removing them now: ',
        ' Panel zatím nepotvrdil: ' => ' Not confirmed by the panel yet: ', ' Pokud znal heslo shell nebo FTP účtu, změňte ho.' => ' If they knew the password of a shell or FTP account, change it.',
        'Zkontrolujte přístupy spolupracovníků' => 'Review your collaborators\' access', 'Na vašich herních serverech mají přístup lidé, kteří už nejsou členy organizace: ' => 'People who are no longer members of your organization still have access to your game servers: ',
        'Delegované přístupy ke zrušené službě byly odvolány' => 'Delegated access to the cancelled service was revoked', 'FTP, SSH a účty spolupracovníků jsme odstranili. Po případné obnově služby je založte znovu.' => 'FTP, SSH and collaborator accounts were removed. Create them again if you restore the service.',
        'Služba byla zrušena a deaktivována' => 'Service cancelled and deactivated', 'Zrušení služby jsme odvolali' => 'Service cancellation withdrawn', 'Služba běží dál, plánované odstranění jsme zrušili.' => 'The service keeps running; the planned removal is cancelled.',
        'Služba byla pozastavena' => 'Service suspended', 'Služba byla obnovena' => 'Service resumed', 'Služba byla ukončena' => 'Service terminated', 'Zálohy držíme po dobu retenční lhůty.' => 'Backups are kept for the retention period.',
        'Naplánováno zrušení služby' => 'Service cancellation scheduled', 'Služba bude zrušena' => 'Service will be cancelled', 'Datum zrušení: ' => 'Cancellation date: ', '. Uhraďte doklad, zrušení se odvolá.' => '. Pay the invoice and the cancellation is withdrawn.',
        'Služba prodloužena' => 'Service renewed', 'Prodloužení služby se nezdařilo' => 'Service renewal failed', 'Nedostatek kreditu · potřeba ' => 'Insufficient credit · needed ', 'platí do ' => 'valid until ',
        'Platbu vracíme na kredit, podpora vás kontaktuje.' => 'We are refunding the payment to your credit; support will contact you.',
        'Období platby služby ' => 'Billing period of ', ' změněno na ' => ' changed to ', 'roční' => 'yearly', 'měsíční' => 'monthly', 'Nové období běží od teď' => 'The new period starts now', '; nevyužitý zbytek původního období jsme odečetli.' => '; the unused rest of the previous period was credited.',
        'Tarif služby ' => 'Plan of ', 'Nové limity platí do minuty; nová cena se účtuje od ' => 'The new limits apply within a minute; the new price is charged from ', 'teď (nové období začalo dnes)' => 'now (a new period started today)', 'příštího období' => 'the next period',
        'Stěhování serveru ' => 'Migration of server ', ' je naplánované' => ' is scheduled', 'Začne ' => 'It starts ', '; termín můžete posunout v okně ' => '; you can move the date within the window ',
        'Server ' => 'Server ', ' byl přestěhován' => ' has been migrated', 'Nová adresa: ' => 'New address: ', '. Data, nastavení i plány zůstaly.' => '. Data, settings and schedules were kept.',
        // web tools
        ' neběží' => ' is not running', ' opět běží' => ' is running again', 'Mimo provoz byl ' => 'It was down for ',
        'Server je vypnutý, aniž jste ho u nás vypínali. Zapnete ho v panelu; pokud jste ho vypnuli sami zevnitř, nic se neděje.' => 'The server is off although you did not switch it off here. Start it in the panel; if you shut it down from the inside yourself, all is well.',
        'Web neodpovídá' => 'Website is down', 'Web opět běží' => 'Website is up again', ' · výpadek ' => ' · outage ', 'Deploy dokončen' => 'Deploy finished', 'Deploy selhal' => 'Deploy failed',
        'Staging přenesen do produkce' => 'Staging pushed to production', 'Staging obnoven z produkce' => 'Staging refreshed from production', 'Staging: operace selhala' => 'Staging: operation failed',
        'Import webu dokončen' => 'Website import finished', 'Import webu selhal' => 'Website import failed', ' souborů, ' => ' files, ', ' souborů · ' => ' files · ', ' databází' => ' databases',
        'Certifikát vystaven' => 'Certificate issued', 'Certifikát se nepodařilo vystavit' => 'Certificate could not be issued', ' · platí do ' => ' · valid until ',
        'Discord účet propojen' => 'Discord account linked', ' může přes /onhost zobrazit stav služeb a spouštět zálohy, restarty a deploy (po potvrzení)' => ' can show service status and start backups, restarts and deploys via /onhost (after confirmation)',
        'Stránka stavu běží na ' => 'Status page is live at ', 'Doména je ověřená; certifikát vystaví edge při první návštěvě.' => 'The domain is verified; the edge issues the certificate on the first visit.',
        'Záznamy A pro @ a www míří na server; certifikát vystavíme, jakmile se změna rozšíří.' => 'The A records for @ and www point at the server; we issue the certificate once the change propagates.',
        'Nastavte u svého DNS záznamy A pro @ a www na adresu serveru; certifikát vystavíme poté.' => 'Point the A records for @ and www at the server address in your DNS; we issue the certificate afterwards.',
        'Alias na serveru i záznamy, které párování přidalo, jsou pryč.' => 'The server alias and the records the pairing added are gone.',
        // chargebacks and loyalty
        'Žádost o vrácení kreditu přijata' => 'Credit refund request received', 'Žádost o vrácení kreditu: ' => 'Credit refund request: ',
        'Technická podpora ji posoudí; po schválení službu zrušíte v panelu a ' => 'Support will review it; once approved you cancel the service in the panel and ', ' % nevyužitého období se vrátí jako kredit.' => ' % of the unused period comes back as credit.',
        'Vrácení kreditu za ' => 'Credit refund for ', ' schváleno' => ' approved', 'Zrušte službu v panelu; vrátíme ' => 'Cancel the service in the panel; we refund ', ' % nevyužitého období) jako kredit.' => ' % of the unused period) as credit.',
        ' jsme nemohli schválit' => ' could not be approved', 'Napište podpoře, pokud chcete rozhodnutí probrat.' => 'Contact support if you want to discuss the decision.',
        'Kredit za zrušenou službu připsán' => 'Credit for the cancelled service added', 'Kredit vrácen: ' => 'Credit refunded: ', ' % nevyužitého období) je na vašem účtu.' => ' % of the unused period) is on your account.',
        'Nová úroveň věrnostního programu: ' => 'New loyalty level: ', 'Odměna ' => 'Reward ', ' je na vašem promo kreditu.' => ' is on your promo credit.', 'Díky, že jste s námi.' => 'Thank you for staying with us.',
        'Nový odznak: ' => 'New badge: ', 'Najdete ho v nastavení účtu.' => 'You will find it in your account settings.',
        'Na vaše doporučení se registroval nový zákazník' => 'A new customer signed up on your referral', 'Odměnu připíšeme po jeho první zaplacené platbě.' => 'The reward is added after their first paid payment.',
        'Uvítací odměna za doporučení' => 'Referral welcome reward', 'Odměna za doporučení' => 'Referral reward', ' bodů a ' => ' points and ', ' promo kreditu za ' => ' promo credit for ', ' promo kreditu.' => ' promo credit.',
        'Splněné mise za ' => 'Missions completed for ', ' · všechny mise měsíce, odznak je váš' => ' · every mission of the month, the badge is yours',
        'Věrnostní série dosažena: ' => 'Loyalty streak reached: ', ' měsíců plateb včas' => ' months of on-time payments', 'Děkujeme. Finance posoudí trvalou věrnostní slevu na vaše další objednávky.' => 'Thank you. Finance will consider a permanent loyalty discount on your next orders.',
        'Trvalá věrnostní sleva ' => 'Permanent loyalty discount ', 'Platí na každou další objednávku.' => 'It applies to every further order.', 'Věrnostní sleva ukončena' => 'Loyalty discount ended', 'Nové objednávky jsou za ceníkové ceny.' => 'New orders are at list prices.',
        'Nová kampaň: ' => 'New campaign: ', 'Kampaň splněna: ' => 'Campaign completed: ', 'Odznak ' => 'Badge ', ' je váš.' => ' is yours.',
        // wallet and billing
        'Kredit vystačí ještě ' => 'Credit lasts another ', ' dní' => ' days', ' · na obnovy chybí ' => ' · renewals short by ',
        'Kredit dobit automaticky o ' => 'Credit topped up automatically by ', 'Obnovy do ' => 'Renewals until ', ' by kredit nepokryly; podle vašeho nastavení jsme kredit dobili z uložené platební metody.' => ' would not be covered; per your settings we topped up from your saved payment method.',
        'Na obnovy příštího týdne chybí ' => "Next week's renewals are short by ",
        'Karta •••• ' => 'Card •••• ', ' uložena pro automatické dobití' => ' saved for auto top-up',
        'Automatické dobití kreditu ji použije, když kredit nepokryje obnovy příštího týdne. Odebrat ji můžete ve Fakturaci.' => "Auto top-up uses it when the credit does not cover next week's renewals. You can remove it under Billing.",
        'Zapněte automatické dobití kreditu ve Fakturaci a obnovy proběhnou bez vašeho zásahu. Kartu můžete kdykoli odebrat.' => 'Enable auto top-up under Billing and renewals run without your action. You can remove the card any time.',
        'Kredit dobit' => 'Credit topped up', 'Peněženka byla zmrazena' => 'Wallet has been frozen', 'Peněženka zmrazena' => 'Wallet frozen', 'Kontaktujte prosím podporu.' => 'Please contact support.',
        'Měsíční rozpočet je vyčerpaný · potřeba ' => 'The monthly budget is used up · needed ',
        'Rozpočet: ' => 'Budget: ', 'Útrata dosáhla nastaveného prahu.' => 'Spending reached the configured threshold.',
        'Upomínka — neuhrazený doklad' => 'Reminder — unpaid invoice', 'Po splatnosti ' => 'Overdue by ', ' dní.' => ' days.',
        'Automatické prodloužení je zapnuté.' => 'Auto-renewal is on.', 'Automatické prodloužení je vypnuté — prodlužte ručně.' => 'Auto-renewal is off — renew manually.', 'zapnuté' => 'on', 'vypnuté' => 'off',
        'Dobijte kredit nebo prodlužte ručně, doména jinak expiruje.' => 'Top up credit or renew manually, otherwise the domain expires.',
        'Registr prodloužení nepřijal. Řešíme to a zkoušíme to každý den znovu; pokud je potřeba něco od vás, ozveme se. Z kreditu jsme nic nestrhli.' => 'The registry did not accept the renewal. We are on it and try again every day; if we need anything from you, we will be in touch. Nothing was taken from your credit.',
        'SLA kredit připsán' => 'SLA credit added', 'Export dat je připraven' => 'Data export is ready', 'Ke stažení ' => 'Available for download for ',
        // domains
        ' domén · automatická synchronizace, upozornění na expirace a párování s hostingem' => ' domains · automatic sync, expiry alerts and pairing with hosting',
        'Obnovy domén v účtu ' => 'Domain renewals in account ', ' by nemusely projít; dobijte kredit u registrátora.' => ' may not go through; top up credit at the registrar.',
        ' zrcadlených domén bylo z panelu odebráno; u registrátora se nic nezměnilo.' => ' mirrored domains were removed from the panel; nothing changed at the registrar.',
        'Je registrována u ' => 'It is registered with ', 'registrátora' => 'registrar', ' (účet ' => ' (account ', '). Prodlužte ji tam, nebo ji převeďte k nám.' => '). Renew it there or transfer it to us.',
        ' byla u registru smazána' => ' was deleted at the registry', ' už není u našeho registrátora' => ' is no longer at our registrar', ' byla po expiraci smazána' => ' was deleted after it expired', ' už není v naší správě' => ' is no longer managed by us',
        'Prodlužování je zastaveno.' => 'Renewals are stopped.', ' u nás zůstává publikovaná — smažte ji, až ji zákazník nebude potřebovat.' => ' stays published with us — delete it once the customer no longer needs it.',
        'Registr ji po uplynutí ochranné lhůty smazal. Prodlužování jsme zastavili; jméno lze zaregistrovat znovu, jakmile je volné.' => 'The registry deleted it after the protective period. We stopped the renewals; the name can be registered again once it is free.',
        'Byla převedena k jinému registrátorovi. Prodlužování u nás jsme zastavili. Pokud jste o převod nežádali, kontaktujte ihned podporu.' => 'It was transferred to another registrar. We stopped the renewals with us. If you did not request the transfer, contact support immediately.',
        'Doména je zpět u registrátora: ' => 'The domain is back at the registrar: ', 'Byla uzavřená jako chybějící a registrátor ji znovu vypisuje; je opět aktivní, prodlužování jako dřív.' => 'It was closed as missing and the registrar lists it again; it is active again, renewals as before.',
        'Byla převedena nebo smazána u registrátora; v panelu zůstává označená.' => 'It was transferred or deleted at the registrar; it stays flagged in the panel.',
        'V ochranné lhůtě ji lze ještě obnovit.' => 'It can still be restored within the grace period.', 'Pokud jste o převod nežádali, kontaktujte ihned podporu.' => 'If you did not request the transfer, contact support immediately.', ' záznamů' => ' records',
        // security
        'Nové přihlášení' => 'New sign-in', 'Dvoufázové ověření změněno' => 'Two-factor authentication changed', 'Heslo bylo změněno' => 'Password changed', 'Účet dočasně uzamčen' => 'Account temporarily locked', 'Opakované neúspěšné přihlášení z ' => 'Repeated failed sign-ins from ',
        'Vytvořen API token „' => 'API token created „', 'rozsah: ' => 'scope: ',
        // support, incidents
        'Nabídka placeného zásahu · ' => 'Offer of paid work · ', 'Čeká na vaše rozhodnutí: ' => 'Waiting for your decision: ', ' bez DPH. Bez schválení nic neúčtujeme.' => ' excl. VAT. Nothing is billed without your approval.',
        'Ohodnoťte prosím řešení.' => 'Please rate the resolution.', 'Probíhá incident' => 'Incident in progress', 'Incident vyřešen' => 'Incident resolved', 'Plánovaná údržba' => 'Planned maintenance',
        // partners and marketplace
        'Partnerský účet schválen' => 'Partner account approved', 'Stupeň přepočítán: ' => 'Tier recalculated: ', 'Sazba ' => 'Rate ', 'Provize vyplacena' => 'Commission paid out',
        'Objednávka z marketplace: ' => 'Marketplace order: ', 'Partner dostal zadání; dodání do ' => 'The partner has the brief; delivery by ', '. Zaplaceno z kreditu (' => '. Paid from credit (',
        'Nová zakázka z marketplace: ' => 'New marketplace job: ', 'Zákazník ' => 'Customer ', ' · dodání do ' => ' · delivery by ',
        'Dodáno: ' => 'Delivered: ', ' · potvrďte převzetí, nebo do ' => ' · confirm acceptance, or file a complaint within ', ' dní reklamujte.' => ' days.',
        'Zakázka převzata: ' => 'Job accepted: ', 'Váš podíl ' => 'Your share ', ' je připraven k výplatě.' => ' is ready for payout.',
        'Reklamaci jsme přijali: ' => 'Complaint received: ', 'Reklamace z marketplace: ' => 'Marketplace complaint: ', 'Podpora ji posoudí a rozhodne o vrácení kreditu nebo potvrzení dodání.' => 'Support will review it and decide on a refund or confirm the delivery.',
        ' je zpět na vašem účtu · ' => ' is back on your account · ', 'Marketplace: ' => 'Marketplace: ', ' prodlouženo' => ' renewed', ' se nepodařilo prodloužit' => ' could not be renewed', 'Chybí kredit ' => 'Missing credit ', ', jinak služba skončí.' => ', otherwise the service ends.',
        ' skončilo' => ' ended', 'Kredit nestačil na další období.' => 'Credit did not cover the next period.', 'Předplatné skončilo s koncem zaplaceného období.' => 'The subscription ended with the paid period.',
        'Zakázka po termínu: ' => 'Job overdue: ', 'Termín byl ' => 'The deadline was ', ' dnech může zákazník žádat vrácení bez sporu.' => ' days the customer may ask for a refund without a dispute.',
        'Partner nedodal do ' => 'The partner did not deliver by ', ' dnech vám nabídneme vrácení kreditu bez sporu.' => ' days we offer you a refund without a dispute.',
        'Nedodáno v termínu: můžete si vzít kredit zpět' => 'Not delivered on time: you can take your credit back', ' dní po termínu. Zrušte zakázku v panelu a kredit se vrátí hned, bez sporu.' => ' days past the deadline. Cancel the job in the panel and the credit returns at once, no dispute.',
        'Kredit za pozdní dodání: ' => 'Credit for late delivery: ', ' dní zpoždění je na vašem účtu.' => ' days of delay is on your account.',
        'Změna modelu provize schválena' => 'Commission model change approved', 'Změna modelu provize zamítnuta' => 'Commission model change rejected', 'Model ' => 'Model ', ' platí od ' => ' applies from ',
        'Model provize se změnil na ' => 'Commission model changed to ', 'Nové provize se počítají podle nového modelu.' => 'New commissions follow the new model.',
        'Změna podmínek schválena: ' => 'Terms change approved: ', 'Změna podmínek zamítnuta: ' => 'Terms change rejected: ', 'Nová podmínka platí od dnešního dne.' => 'The new term applies from today.',
        'Výplata provize požádána automaticky' => 'Commission payout requested automatically', ' · podle vašich výplatních podmínek (' => ' · per your payout terms (', '); finance ji schválí a odešlou.' => '); finance will approve and send it.',
        'Měsíční plnění dodáno: ' => 'Monthly deliverable submitted: ', 'Měsíční plnění ještě není odevzdané: ' => 'Monthly deliverable not yet submitted: ', 'Období končí ' => 'The period ends ', '; bez odevzdání dostane zákazník kredit ' => '; without it the customer receives a credit of ', ' % z vašeho podílu.' => ' % of your share.',
        'Kredit za chybějící měsíční plnění: ' => 'Credit for a missed monthly deliverable: ', ' je na vašem účtu; období do ' => ' is on your account; the period until ', ' zůstalo bez dodávky.' => ' was left without a delivery.',
        'Období bez plnění: ' => 'Period without a deliverable: ', 'Zákazník dostal kredit ' => 'The customer received a credit of ', '; váš podíl za nové období je o něj nižší.' => '; your share for the new period is lower by it.',
        'Nabídka zveřejněna: ' => 'Listing published: ', 'Zákazníci ji vidí v marketplace.' => 'Customers see it in the marketplace.', 'Nabídka stažena: ' => 'Listing withdrawn: ',
        // sandbox
        'Účet je v režimu sandbox' => 'Account is in sandbox mode', 'Režim sandbox ukončen' => 'Sandbox mode ended', 'Služby se zřizují v laboratorním prostředí; kredit ' => 'Services are provisioned in the lab environment; the credit ', ' je určen k testování.' => ' is meant for testing.', 'Nové objednávky jdou do produkce.' => 'New orders go to production.',
        // composed titles and bodies found by the coverage test (audit §5r-7)
        'Služba sdílena: ' => 'Service shared: ', 'Sdílení služby ukončeno: ' => 'Service sharing ended: ', 'Sdílení služby vypršelo: ' => 'Service sharing expired: ', ' · čeká na přijetí pozvánky' => ' · waiting for the invitation to be accepted', ' · přístup je aktivní' => ' · access is active', ' · do ' => ' · until ',
        'Část objednávky ' => 'Part of order ', ' se nepodařilo zřídit' => ' could not be delivered', ' jsme vrátili na váš kredit' => ' went back to your credit', ' jsme odečetli z faktury' => ' was taken off the invoice',
        'Po nezdařeném zřízení zůstal zdroj na panelu: ' => 'A failed provisioning left a resource on the panel: ', ' · identitu se nepodařilo potvrdit (' => ' · its identity could not be confirmed (', ') — nic nebylo smazáno, zkontrolujte ručně' => ') — nothing was deleted, check it by hand',
        'DNS zóna znovu publikována: ' => 'A DNS zone was published again: ', 'zóna u poskytovatele byla vytvořena znovu · ' => 'the zone was created again at the provider · ', 'přidáno: ' => 'added: ', ' · odebráno: ' => ' · removed: ', ' · upraveno: ' => ' · updated: ', ' · po publikaci stále zbývá rozdílů: ' => ' · differences left after publishing: ', ' · zóna byla podepsaná: zapněte DNSSEC znovu a zveřejněte nový DS u registru' => ' · the zone was signed: enable DNSSEC again and publish the new DS at the registry',
        'DNS zóna se liší od poskytovatele: ' => 'A DNS zone differs from its provider: ', 'zóna u poskytovatele neexistuje' => 'the zone does not exist at the provider', 'chybí u poskytovatele: ' => 'missing at the provider: ', ' · navíc u poskytovatele: ' => ' · extra at the provider: ',
        'Neplacená služba stále běží: ' => 'An unpaid service still runs: ', ' se nedaří' => ' does not go through', ' · pokus ' => ' · attempt ', ' · operace zadána znovu' => ' · the operation was requested again', 'pozastavení' => 'the suspension', 'zrušení' => 'the cancellation',
        'Doména už expirovala. V ochranné lhůtě ji ještě prodloužíme za běžnou cenu — zbývá dní: ' => 'The domain has expired. In the protective period we still renew it at the ordinary price — days left: ', '. Dobijte kredit, prodloužíme ji hned.' => '. Top up your credit and we renew it at once.',
        ' byla zrušena' => ' was cancelled', ' · opravný doklad ' => ' · credit note ', 'nebyla zaplacena a už není platná; můžete zadat novou.' => 'was not paid and is no longer valid; you can place a new one.',
        'Objednávku ' => 'Order ', 'Objednávka ' => 'Order ', ' ještě kontrolujeme' => ' is still being checked', ' byla schválena' => ' was approved', ' jsme nemohli přijmout' => ' could not be accepted',
        ' · zřizujeme služby' => ' · provisioning the services', ' · všechny služby jsou aktivní' => ' · all services are active',
        'CDN je aktivní' => 'CDN is active', 'CDN zapnuto' => 'CDN enabled', 'CDN vypnuto' => 'CDN disabled', ' · nastavte nameservery u registrátora' => ' · set the nameservers at your registrar', ' změněn na ' => ' changed to ',
        'Služba ' => 'Service ', ' využívá ' => ' uses ', '(prostor)' => '(disk space)', '(přenos dat)' => '(traffic)', '(paměť)' => '(memory)', '(poštovní schránky)' => '(mailboxes)',
        'Blížíte se limitu tarifu. ' => 'You are close to the plan limit. ', 'Kapacita je téměř vyčerpaná. ' => 'Capacity is almost used up. ', 'Vyšší tarif pro tuto službu nenabízíme; napište podpoře.' => 'We offer no higher plan for this service; contact support.',
        'Vyšší tarif ' => 'The higher plan ', ' stojí ' => ' costs ', ' a přepnete ho jedním klikem v panelu (doplatek jen za zbytek období).' => ' and you switch to it with one click in the panel (you only pay the difference for the rest of the period).', ' / rok' => ' / year', ' / měsíc' => ' / month',
        'Nový doklad ' => 'New document ', 'Doklad ' => 'Document ', ' byl stornován' => ' was cancelled', ' je po splatnosti' => ' is overdue',
        'Do ' => 'Until ', ' se obnovují služby za ' => ' services renew for ', ', k dispozici je ' => ', available ', ' — chybí ' => ' — short by ', ' Automatické dobití se nepodařilo (' => ' The automatic top-up failed (', '); dobijte prosím kredit.' => '); please top up your credit.', ' Dobijte kredit, nebo si zapněte automatické dobití.' => ' Top up your credit or turn on the automatic top-up.',
        'Synchronizace účtu ' => 'Sync of account ', ' selhala' => ' failed', 'Účet ' => 'Account ', ' odpojen' => ' disconnected',
        'Přijali jsme váš požadavek ' => 'We received your request ', 'Tiket ' => 'Ticket ', ' vyřešen' => ' resolved',
        'Herní server ' => 'Game server ', ' potřebuje nastavení' => ' needs setting up', 'Doplňte v záložce Startup platnou hodnotu: ' => 'Enter a valid value in the Startup tab: ', ' Bez ní server nenastartuje.' => ' The server will not start without it.', // §5t-3
        'Za hodinu začíná vaše on-call směna' => 'Your on-call shift starts in an hour', ' držíte pager; alerty najdete v konzoli.' => ' you carry the pager; the alerts are in the console.', 'Od ' => 'From ', // §5t-4
        // small words that appear inside composed strings
        ' · vrácení kreditu' => ' · credit refund', ' dnů' => ' days', 'včera' => 'yesterday', 'dnes' => 'today', 'zítra' => 'tomorrow',
    ];

    public static function translate(?string $text, string $locale): ?string
    {
        if ($text === null || $text === '' || $locale === 'cs' || $locale === 'sk') {
            return $text;
        }
        $table = self::table($locale);
        if ($table === []) {
            return $text;
        }

        return strtr($text, $table); // strtr tries the longest keys first and never re-scans replaced text
    }

    /** Czech fragments (words with diacritics) a translated text still carries — the test's yardstick for coverage. @return list<string> */
    public static function untranslated(string $text): array
    {
        preg_match_all('/\p{L}*[ěščřžýáíéúůťďňĚŠČŘŽÝÁÍÉÚŮŤĎŇ]\p{L}*/u', $text, $m);

        return array_values(array_diff(array_unique($m[0]), ['Kč'])); // the currency symbol is a value, not a phrase
    }

    /** @return array<string,string> */
    private static function table(string $locale): array
    {
        return match ($locale) {
            'en' => self::EN,
            default => [],
        };
    }
}
