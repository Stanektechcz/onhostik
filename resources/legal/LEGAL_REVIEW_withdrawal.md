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
* Registrovanou doménu nelze odstoupit (plnění dokončeno zápisem do registru); upozornění je v košíku u objednávky
  domény a v poučení i VOP. Zaplacenou objednávku, z níž se zatím nic nedodalo, lze odstoupit celou (zruší se a všechny
  řádky se dobropisují) — včetně domény, která ještě registrována nebyla.

## Otázky pro právníka

1. Stačí výslovný souhlas zaškrtnutím v panelu k vrácení na (nevyplatitelný) kredit místo původního platebního
   prostředku? Kredit z odstoupení je dnes veden jako nevyplatitelný (`refundable = false`); má být vyplatitelný?
2. Počítání lhůty od dne objednávky (uzavření smlouvy) a poměrné vyúčtování po dnech včetně dne odeslání.
3. Jednorázové poplatky (zřízení, řádky bez období) se nevracejí — je to v pořádku?
4. Výjimka pro registraci domény: je formulace v košíku, v poučení (čl. 1 bod 4) a ve VOP (čl. 8 bod 1) dostatečná?
   Platí totéž pro SSL certifikáty a další zcela poskytnutá plnění?
5. Doplňky se odstupují spolu se službou; doplněk koupený později samostatně nyní samostatně odstoupit nelze.
6. Upravené texty `withdrawal_waiver.md` (čl. 1 body 2–4) a `terms.md` (čl. 8 bod 1) byly doplněny bez změny verze
   `2026-09`; rozhodněte, zda vydat novou verzi dokumentu (LegalEntitySeeder) a jak naložit se souhlasy ke staré verzi.
7. Potvrzení o přijetí odstoupení (e-mail `withdrawal-accepted`, povinný, nelze vypnout) — obsah a trvalý nosič.
