# Smlouva o úrovni služeb (SLA)

**Poskytovatel:** {{entity_name}}, IČO {{entity_ico}}.
**Verze:** {{version}}, účinná od {{effective_from}}.

## 1. Rozsah

1. SLA se vztahuje na služby provozované poskytovatelem (webhosting, virtuální a herní servery, e-mail, DNS, aplikace a zákaznický panel) v třídě dostupnosti, kterou uvádí tarif nebo objednávka. Nevztahuje se na služby třetích stran, které poskytovatel neovládá (registry domén, platební brány), ani na výpadky způsobené zákazníkem nebo jeho obsahem.
2. Smluvní kompenzace náleží jen ve třídách, které mají v tabulce smluvní dostupnost. Třída Standard má cílovou dostupnost bez smluvní kompenzace.

## 2. Třídy dostupnosti a kompenzace

| Třída | Cílová dostupnost | Smluvní dostupnost | Kompenzace z měsíční ceny služby |
| --- | --- | --- | --- |
| Standard | 99,9 % | – | – |
| Business | 99,95 % | 99,9 % | pod 99,9 %: 10 %, pod 99,0 %: 25 %, pod 95,0 %: 50 % (nejvýše 50 %) |
| HA | 99,995 % | 99,99 % | pod 99,99 %: 10 %, pod 99,9 %: 25 %, pod 99,0 %: 50 % (nejvýše 50 %) |
| Critical | 99,999 % | 99,99 % | pod 99,99 %: 25 %, pod 99,9 %: 50 %, pod 99,0 %: 100 % (nejvýše 100 %) |

Třídy HA a Critical platí jen u tarifů, které je v katalogu výslovně nabízejí. Měsíční cenou služby se rozumí cena za kalendářní měsíc; u ročního předplatného jedna dvanáctina roční ceny.

## 3. Měření dostupnosti

1. Dostupnost se měří automatickými kontrolami z nejméně tří měřicích míst; služba je nedostupná, selže-li kontrola na většině z nich. Vyhodnocuje se za kalendářní měsíc jako podíl doby dostupnosti a celkové doby měsíce.
2. Do nedostupnosti se nezapočítává plánovaná údržba, kterou poskytovatel oznámil na stavové stránce a v panelu nejméně 48 hodin před jejím začátkem; mimořádná (neohlášená) údržba se započítává. Nezapočítávají se ani výpadky způsobené zákazníkem, jeho obsahem nebo konfigurací a okolnosti vylučující odpovědnost.

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
