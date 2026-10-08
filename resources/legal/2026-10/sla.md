# Smlouva o úrovni služeb (SLA)

**Poskytovatel:** {{entity_name}}, IČO {{entity_ico}}.
**Verze:** {{version}}, účinná od {{effective_from}}.

## 1. Rozsah

1. SLA se vztahuje na služby provozované poskytovatelem (webhosting, virtuální a herní servery, e-mail, DNS, aplikace a zákaznický panel) v třídě dostupnosti, kterou uvádí tarif nebo objednávka. Nevztahuje se na služby třetích stran, které poskytovatel neovládá (registry domén, platební brány), ani na výpadky způsobené zákazníkem nebo jeho obsahem.
2. Kompenzace náleží podle tabulky v čl. 2. Úrovně kompenzace tříd Standard a Business jsou stejné jako ve verzi 2026-09 těchto podmínek; tato verze je jen popisuje přesněji.

## 2. Třídy dostupnosti a kompenzace

| Třída | Cílová dostupnost | Smluvní dostupnost | Kompenzace z měsíční ceny služby |
| --- | --- | --- | --- |
| Standard | 99,9 % | 99,9 % | 5 % měsíční ceny za každou započatou hodinu nedostupnosti nad měsíční limit (nejvýše 50 %) |
| Business | 99,95 % | 99,95 % | 10 % měsíční ceny za každou započatou hodinu nedostupnosti nad měsíční limit (nejvýše 100 %) |
| HA | 99,995 % | 99,99 % | pod 99,99 %: 10 %, pod 99,9 %: 25 %, pod 99,0 %: 50 % (nejvýše 50 %) |
| Critical | 99,999 % | 99,99 % | pod 99,99 %: 25 %, pod 99,9 %: 50 %, pod 99,0 %: 100 % (nejvýše 100 %) |

Měsíčním limitem nedostupnosti u tříd Standard a Business je část kalendářního měsíce odpovídající rozdílu mezi 100 % a smluvní dostupností (0,1 % u třídy Standard, 0,05 % u třídy Business); za každou započatou hodinu nedostupnosti nad tento limit náleží uvedené procento měsíční ceny. Nedostupnosti všech incidentů téhož kalendářního měsíce se sčítají a tatáž nedostupnost se nehradí dvakrát. Třídy HA a Critical platí jen u tarifů, které je v katalogu výslovně nabízejí. Měsíční cenou služby se rozumí cena za kalendářní měsíc; u ročního předplatného jedna dvanáctina roční ceny.

## 3. Měření dostupnosti

1. Dostupnost se měří automatickými kontrolami z nejméně tří měřicích míst; služba je nedostupná, selže-li kontrola na většině z nich. Vyhodnocuje se za kalendářní měsíc jako podíl doby dostupnosti a celkové doby měsíce.
2. Do nedostupnosti se nezapočítává plánovaná údržba, kterou poskytovatel oznámil na stavové stránce a v panelu nejméně 48 hodin před jejím začátkem, provádí ji mimo pracovní dobu (pondělí až pátek 8:00–17:00 pražského času) a která dohromady nepřesáhne 4 hodiny v kalendářním měsíci; údržba nad tento rámec, údržba v pracovní době a mimořádná (neohlášená) údržba se započítává. Nezapočítávají se ani výpadky způsobené zákazníkem, jeho obsahem nebo konfigurací a okolnosti vylučující odpovědnost.

## 4. Incidenty a komunikace

1. Stav služeb je zveřejněn na stavové stránce; o průběhu závažných incidentů poskytovatel informuje průběžně.
2. Incident lze nahlásit v panelu v sekci Podpora. Priorita se určí podle dopadu (výpadek celé služby, omezení, dotaz).
3. Po incidentu s dopadem na smluvní dostupnost poskytovatel zveřejní shrnutí příčin a nápravných opatření.

## 5. Kompenzace

1. Po uzavření incidentu, který se dotkl služby se smluvní dostupností, poskytovatel kompenzaci vypočítá a připíše ji jako kredit na účet zákazníka. Přehled kompenzací vidí zákazník v panelu. Nebyla-li kompenzace připsána, může ji zákazník uplatnit požadavkem v sekci Podpora do 30 dnů od konce měsíce, v němž k nedodržení dostupnosti došlo.
2. Pro podnikatele je kompenzace jediným nárokem z nedodržení dostupnosti, nejde-li o škodu způsobenou úmyslně nebo z hrubé nedbalosti.
3. Pro spotřebitele kompenzace nenahrazuje práva z vadného plnění; spotřebitel může místo ní uplatnit slevu z ceny podle Reklamačního řádu, která se vrací stejným platebním prostředkem, jakým platil. Tatáž nedostupnost se nehradí dvakrát.

## 6. Zálohy a obnova

Zálohy probíhají jen v rozsahu, který uvádí tarif nebo objednaný doplněk, a jejich stav vidí zákazník v panelu. Cílové doby obnovy provozu a stáří obnovených dat jsou provozní cíle, nikoli smluvně kompenzované parametry, ledaže je tarif výslovně uvádí jako smluvní.

## 7. Bezpečnost

Poskytovatel izoluje zákaznická prostředí, vyžaduje dvoufázové ověření administrátorských účtů a vede auditní záznam citlivých akcí. Bezpečnostní incident s dopadem na zákazníka oznámí bez zbytečného odkladu.
