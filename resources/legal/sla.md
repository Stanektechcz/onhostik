# Smlouva o úrovni služeb (SLA)

**Poskytovatel:** {{entity_name}}, IČO {{entity_ico}}.
**Verze:** {{version}}, účinná od {{effective_from}}.

## 1. Rozsah

SLA se vztahuje na služby provozované poskytovatelem: webhosting, virtuální a herní servery, e-mail, DNS, aplikační platformu a zákaznický panel. Nevztahuje se na služby třetích stran (registry domén, platební brány) ani na výpadky způsobené zákazníkem.

## 2. Dostupnost

| Třída | Měsíční dostupnost | Reakce na incident | Kompenzace |
| --- | --- | --- | --- |
| Standard | 99,9 % | do 4 hodin | 5 % měsíční ceny za každou započatou hodinu nad limit, max. 50 % |
| Business | 99,95 % | do 1 hodiny | 10 % měsíční ceny za každou započatou hodinu nad limit, max. 100 % |
| Enterprise | 99,99 % | do 30 minut, telefonicky | podle individuální smlouvy |

Dostupnost se měří z nejméně tří nezávislých míst mimo síť poskytovatele a vyhodnocuje se za kalendářní měsíc. Do nedostupnosti se nepočítá plánovaná údržba oznámená 48 hodin předem (max. 4 hodiny měsíčně mimo pracovní dobu), výpadky způsobené zákazníkem a okolnosti vylučující odpovědnost.

## 3. Incidenty a komunikace

1. Stav služeb je zveřejněn na stavové stránce; závažné incidenty jsou hlášeny průběžně nejméně každých 60 minut.
2. Incident lze nahlásit v panelu (sekce Podpora) nebo na telefonní lince uvedené v panelu. Priorita se určí podle dopadu: výpadek celé služby, omezení, dotaz.
3. Po incidentu s dopadem na dostupnost poskytovatel zveřejní do 5 pracovních dnů shrnutí příčin a nápravných opatření.

## 4. Kompenzace

Nárok na kompenzaci uplatní zákazník v panelu do 30 dnů od konce měsíce, ve kterém k nedodržení dostupnosti došlo. Kompenzace se připíše jako kredit na účet zákazníka. Kompenzace je jediným nárokem z nedodržení dostupnosti, nejde-li o úmysl nebo hrubou nedbalost poskytovatele.

## 5. Zálohy a obnova

Zálohy probíhají podle tarifu (denně u standardních služeb) a jsou pravidelně ověřovány. Cíl obnovy provozu (RTO) je 4 hodiny, cíl bodu obnovy (RPO) 24 hodin, u tarifů s hodinovým zálohováním 1 hodina.

## 6. Bezpečnost

Poskytovatel provozuje ochranu proti volumetrickým útokům, izolaci zákaznických prostředí, dvoufázové ověření administrátorských účtů a auditní protokol citlivých akcí. Bezpečnostní incident s dopadem na zákazníka je oznámen bez zbytečného odkladu.
