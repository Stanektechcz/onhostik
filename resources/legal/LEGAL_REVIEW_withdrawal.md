# K právní revizi: odstoupení spotřebitele od smlouvy (TASK-0025)

Tento soubor není zveřejňovaný dokument (nemá záznam v `consent_documents` ani adresu v `LegalDocumentController::SLUGS`).
Shrnuje, co musí posoudit právník, než provozovatel zapne pravidlo `billing.withdrawal` a nastaví
`ONHOST_WITHDRAWAL_LEGAL_REVIEWED=true` (doctor do té doby hlásí varování). Kód na revizi nečeká; mechanismus je
vypnutý, dokud ho provozovatel nezapne.

## Jak mechanismus funguje

* Odstoupit může jen spotřebitel — rozhoduje třída zákazníka v okamžiku objednávky (`orders.meta.customer_class`; u
  starších objednávek existence souhlasu `withdrawal_waiver`). Podnikatel (IČO / DIČ při objednávce) je vyloučen.
* Lhůta: 14 dnů od uzavření smlouvy = dne objednávky (`orders.placed_at`, účetní den v Praze), do konce 14. dne.
  Rozhoduje den **odeslání** oznámení; dopis nebo e-mail zaznamená finanční tým s datem odeslání (čtyři oči).
* Pořadí: služba se nejdřív pozastaví, pak se nevyužitá část vrátí dobropisem k zaplaceným řádkům dokladu (poměrně podle
  dnů; den odeslání oznámení se počítá jako využitý), nakonec se služba zruší běžným postupem se závěrečnou zálohou.
  Obnovit ji zákazník sám nemůže.
* Vrácení jde na kredit účtu (`returnToCredit`), nikdy jako dobití a nikdy na kartu. Zákazník s tím v panelu výslovně
  souhlasí zaškrtnutím (záznam `consents.kind = withdrawal_refund_to_credit`). Bez souhlasu platí text poučení: vrácení
  původním způsobem na žádost podpoře (ruční postup).
* Kredit se v hotovosti nevrací nikdy (rozhodnutí vlastníka G-R4, 2026-10-05; VOP čl. 2 bod 3). Jedinou výjimkou je
  zákonné vrácení **platby** za odstoupenou smlouvu původním způsobem (§ 1831 OZ), když spotřebitel s vrácením na kredit
  nesouhlasí — viz `docs/audit/2026-10-full-readiness/ROZHODNUTI.md`, G-R4. Tato cesta zatím není v systému napojená
  (G6); panel i personál dnes bez souhlasu odstoupení nepřijmou.
* Registrovanou doménu nelze odstoupit (plnění dokončeno zápisem do registru); upozornění je v košíku u objednávky
  domény a v poučení i VOP. Zaplacenou objednávku, z níž se zatím nic nedodalo, lze odstoupit celou (zruší se a všechny
  řádky se dobropisují) — včetně domény, která ještě registrována nebyla.

## Stav revize (rozhodnutí vlastníka I-R4, 2026-10-08)

Vlastník předává tento soubor právníkovi spolu s `terms.md` a `withdrawal_waiver.md` (hlavně otázky 8 a 9). Do odpovědi se smí
spustit s dnešními texty verze `2026-09` (vědomě přijaté riziko); verze dokumentů se do odpovědi nemění. Po odpovědi vznikne nová
verze přes `consent_documents` a seam pro texty v prototypech (balík I5).

**TASK-0142 (2026-10-08):** nová verze `2026-10` je připravená předem jako **draft** (`resources/legal/2026-10/`, nezveřejněná,
nic ji nenabízí) včetně nového reklamačního řádu a zásad přijatelného užívání; úplná kontrola a seznam zjištění jsou v
`docs/legal/LEGAL_REVIEW_2026-10.md`. Po odpovědi advokáta se texty upraví a vlastník je zveřejní příkazem
`php artisan onhost:legal:publish 2026-10` (postup tamtéž, kap. 5). Texty `2026-09` v této složce se už nemění (hlídá test).

## Otázky pro právníka

1. Stačí výslovný souhlas zaškrtnutím v panelu k vrácení na (nevyplatitelný) kredit místo původního platebního
   prostředku? Kredit z odstoupení je veden jako nevyplatitelný (`refundable = false`) a podle G-R4 vyplatitelný nebude.
   Je v pořádku, že bez souhlasu nelze odstoupení v panelu odeslat (spotřebitel musí napsat podpoře)?
2. Počítání lhůty od dne objednávky (uzavření smlouvy) a poměrné vyúčtování po dnech včetně dne odeslání.
3. Jednorázové poplatky (zřízení, řádky bez období) se nevracejí — je to v pořádku?
4. Výjimka pro registraci domény: je formulace v košíku, v poučení (čl. 1 bod 4) a ve VOP (čl. 8 bod 1) dostatečná?
   Platí totéž pro SSL certifikáty a další zcela poskytnutá plnění?
5. Doplňky se odstupují spolu se službou; doplněk koupený později samostatně nyní samostatně odstoupit nelze.
6. Upravené texty `withdrawal_waiver.md` (čl. 1 body 2–4) a `terms.md` (čl. 8 bod 1) byly doplněny bez změny verze
   `2026-09`; rozhodněte, zda vydat novou verzi dokumentu (LegalEntitySeeder) a jak naložit se souhlasy ke staré verzi.
7. Potvrzení o přijetí odstoupení (e-mail `withdrawal-accepted`, povinný, nelze vypnout) — obsah a trvalý nosič.
8. Dobití kreditu: je to samostatná smlouva, od níž spotřebitel může do 14 dnů odstoupit s vrácením nevyčerpané části na
   kartu? Poučení čl. 2 dřív slibovalo „předplacený kredit nevyužitý v době odstoupení vracíme v plné výši“; v G4 je věta
   přeformulovaná neutrálně (platby podle čl. 8 VOP a zákona, kredit se v hotovosti nevyplácí). Potvrďte text a výklad
   § 1831/§ 1832 OZ, o který se opírá výjimka v `docs/audit/2026-10-full-readiness/ROZHODNUTI.md` (G-R4).
9. Zůstatek kreditu při zrušení účtu (výmaz): propadá, nebo musí být vrácen?

**Rozhodnutí vlastníka H-R5 (2026-10-06)** k otázkám 8 a 9 — provedeno v kódu, právník ověří text a výklad:

* Od dobití kreditu se neodstupuje (`WithdrawalPolicy::refuseTopUp`, `why: credit_topup`; i finanční tým při zápisu oznámení
  zaslaného e-mailem či dopisem). Odstupuje se od služeb zaplacených z kreditu.
* Při výmazu účtu kredit propadá: náhled `GET /v1/data-requests/deletion-preview` ukáže zůstatek a varování, žádost o výmaz
  se zůstatkem projde jen s `credit_forfeit_acknowledged: true`, a výmaz zaúčtuje propadnutí (`credit_forfeit`: koupený kredit
  do výnosu `revenue:forfeited_credit`, promo kredit zpět do `expense:promo`).
* Texty: VOP čl. 2 body 4 a 5, poučení čl. 1 bod 5 a čl. 2 — opět bez změny verze `2026-09` (viz otázka 6): rozhodněte, zda
  vydat novou verzi a jak naložit se souhlasy ke staré verzi. Účetní posoudí DPH u propadlé zálohy (až bude ONhost plátcem).
