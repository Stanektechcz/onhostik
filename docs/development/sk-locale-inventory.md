# Inventura locale větví pro SK (W3-6)

Stav: `development` @ 2f09c6a, 2026-10-15. Dokument jen popisuje, kód se tu nemění. SK texty a `lang/sk` přijdou
v navazující větvi (varianta A za `PublicSeo::LANGUAGES`), aby SK šlo spustit s Peppolem do 2027-01-01.
Schválení textů vlastníkem je řádek `SK` v `.ai/owner-queue.md`.

## Příkazy, které jdou zopakovat

Vzor 1 (binární větvení cs/en), spouští se z kořene repozitáře:

```sh
grep -rnE "=== 'cs'|!== 'en'|locale\(\) ?=== ?'cs'" app domains --include=*.php | wc -l
```

Naměřeno: **39** řádků. ZKOUMAL uváděl ~31; rozdíl dělají řádky přidané ve vlně 2.

Vzor 2 (další tvary: `in_array` s `'cs'`, `match` na locale, formát čísla nebo měny podle jazyka):

```sh
grep -rnE "in_array\([^)]*'cs'|match ?\(\\\$?[a-zA-Z>-]*locale|Intl\.NumberFormat|translatedFormat" app domains --include=*.php | wc -l
```

Naměřeno: **8** řádků. Šest z nich je v `SurfaceRenderer.php` a překrývá se se vzorem 1.

Legenda sloupce „sk dnes“: **cs** znamená, že `sk` spadne do češtiny (podmínka `!== 'en'`). **en** znamená, že
`sk` spadne do angličtiny (podmínka `=== 'cs'`).

## Přepočet nad 8817e21 (W5-5, commit 1)

Oba vzory výše spuštěné znovu nad `8817e21` (vlny 1 až 4 sloučené), 2026-10-15: vzor 1 **39** řádků, vzor 2 **8**
řádků, čísla řádků v tabulkách beze změny. Rozpad zůstává **20 řádků cs** a **19 řádků en**; návrh W5-5 uváděl
„19 větví“, což odpovídá jen sloupci en. Tabulka vzoru 2 (CatalogController:95, Lexicon:317 a šest `Intl.NumberFormat`)
se také nezměnila.

Referenční snapshoty pro `tests/Feature/SkLocalePhase2Test.php` patří do `tests/fixtures/sk-phase2/`. Uložený je
`surfaces.sha1.json` (sha1 `apps/surfaces/*.dc.html`). HTML stránek z `PublicSeo::paths()`, `GET /v1/catalog`
a `POST /v1/cart/quote` pro `?locale=sk` a `?locale=cs` (CSRF, nonce, UUID a časy normalizované) se nahrají tak, že se
test zkopíruje do checkoutu `8817e21` a spustí `SK2_RECORD=1 php artisan test --filter=SkLocalePhase2Test`; vzniklé
soubory se commitnou sem. V prostředí W5-5 nebyl nainstalovaný `vendor/`, takže nahrané zatím nejsou a test (a) se do té
doby přeskočí (viz „Stav po W5-5“).

## Vzor 1: výskyty

| soubor:řádek | co větví | sk dnes | navrhovaná oprava |
|---|---|---|---|
| app/Http/Controllers/Web/SurfaceDataController.php:97 | formát ceny (měny) | cs | `SurfacePricing::currencies` podle locale (pro sk EUR první) |
| app/Http/Controllers/Web/SurfaceDataController.php:133 | text | cs | klíče `__()` s `lang/sk` |
| app/Http/Controllers/Web/SurfaceDataController.php:291 | text | cs | klíče `__()` |
| app/Http/Controllers/Web/SurfaceDataController.php:353 | text | cs | klíče `__()` |
| app/Http/Controllers/Web/SurfaceDataController.php:441 | text (štítek tarifu) | en | klíč `__()` |
| app/Http/Controllers/Web/SurfaceDataController.php:649 | formát čísla (desetinná čárka) | en | formát podle locale (sk také čárka) |
| app/Http/Controllers/Web/SurfaceDataController.php:725 | text | en | klíče `__()` |
| app/Http/Controllers/Web/SurfaceDataController.php:841 | formát ceny (měny) | cs | stejně jako ř. 97 |
| app/Http/Support/CatalogPresentation.php:110 | text katalogu | cs | klíče `__()` |
| app/Http/Support/CatalogPresentation.php:135 | text katalogu | cs | klíče `__()` |
| app/Http/Support/CatalogPresentation.php:164 | text katalogu | cs | klíče `__()` |
| app/Http/Support/PricingPageSeam.php:66 | text a formát ceny | cs | `__()` a měna podle locale |
| app/Http/Support/SurfaceRenderer.php:549 | přepínač jazyka prototypu (JS seam) | en | seam musí znát `sk`, prototyp zůstává byte-identický |
| app/Http/Support/SurfaceRenderer.php:550 | přepínač jazyka prototypu (JS seam) | en | stejně jako ř. 549 |
| app/Http/Support/SurfaceRenderer.php:1236 | formát ceny (`Intl.NumberFormat`) | en | mapa jazyk → `cs-CZ` / `sk-SK` / `en-GB` |
| app/Http/Support/SurfaceRenderer.php:1246 | formát ceny | en | stejně jako ř. 1236 |
| app/Http/Support/SurfaceRenderer.php:1329 | formát ceny | en | stejně jako ř. 1236 |
| app/Http/Support/SurfaceRenderer.php:1330 | formát ceny | en | stejně jako ř. 1236 |
| app/Http/Support/SurfaceRenderer.php:1361 | formát ceny | en | stejně jako ř. 1236 |
| app/Http/Support/SurfaceRenderer.php:1362 | formát ceny | en | stejně jako ř. 1236 |
| domains/Identity/Notifications/VerifyEmailNotification.php:31 | e-mail | cs | `__()` nebo SK tabulka v Lexicon |
| domains/Identity/Notifications/PasswordResetNotification.php:31 | e-mail | cs | stejně jako výše |
| domains/Identity/Notifications/GuestAccountNotification.php:31 | e-mail | cs | stejně jako výše |
| domains/Notifications/Lexicon.php:295 | e-mail a notifikace (sk výslovně vrací český text) | cs | tabulka `SK` v `table()` |
| domains/Content/ContentService.php:120 | formát data | en | formát podle locale (`j. n.` i pro sk) |
| domains/Content/ContentService.php:136 | text (jednotka ceny) | en | klíč `__()` |
| domains/Content/ContentService.php:138 | text (sklad, skloňování) | en | `trans_choice` s `lang/sk` |
| domains/Content/ContentService.php:139 | text | en | klíč `__()` |
| domains/Content/ContentService.php:140 | text | en | klíč `__()` |
| domains/Support/Assistant/AssistantService.php:291 | text asistenta | cs | klíče `__()` |
| domains/Support/Assistant/AssistantService.php:341 | text asistenta | cs | klíče `__()` |
| domains/Support/Assistant/AssistantService.php:390 | text asistenta | cs | klíče `__()` |
| domains/Support/Assistant/AssistantService.php:512 | text asistenta | cs | klíče `__()` |
| domains/Support/Assistant/AssistantService.php:555 | text asistenta | cs | klíče `__()` |
| domains/Services/UsageWatch.php:315 | text (e-mail o limitech) | en | klíče `__()` |
| domains/Services/UsageWatch.php:316 | text (e-mail o limitech) | en | klíče `__()` |
| domains/Services/UsageWatch.php:317 | text (e-mail o limitech) | en | klíče `__()` |
| domains/Loyalty/LoyaltyRedemptions.php:178 | text | cs | klíče `__()` |
| domains/Organizations/OnboardingChecklist.php:27 | text | cs | klíče `__()` |

Shrnutí: pro `sk` padá 20 řádků do češtiny a 19 do angličtiny. SK zákazník tak dnes dostane směs obou jazyků.

## Vzor 2: výskyty mimo vzor 1

| soubor:řádek | co větví | sk dnes | navrhovaná oprava |
|---|---|---|---|
| app/Http/Controllers/Api/V1/CatalogController.php:95 | povolené locale z `config('onhost.locales')` (`cs`, `sk`, `en`) | sk projde | nic, jenže zbytek kódu `sk` nezná |
| domains/Notifications/Lexicon.php:317 | `match ($locale)`, překladová tabulka existuje jen pro `en` | cs | přidat `'sk' => self::SK` |

Zbylých šest výskytů vzoru 2 tvoří `Intl.NumberFormat` v `SurfaceRenderer.php` (ř. 1236 až 1362), které jsou uvedené výše.
Právní texty (VOP, souhlasy) se ve větvích kódu nenašly. Jsou v obsahu (`ContentSeeder`) a SK verze souhlasu E2 čeká na schválení vlastníkem.

## Další zjištění

* `lang/` obsahuje jen `cs` a `en`, `lang/sk` neexistuje.
* `app/Http/Support/PublicSeo.php:21`: `PublicSeo::LANGUAGES = ['cs']`, takže hreflang `sk` se nevydává, dokud
  vlastník neschválí SK texty (N15).
* Riziko duplicitního obsahu (rozhodnutí vlny 2 #18): kdyby URL `/sk` při fallbacku servírovaly český text,
  vyhledávače uvidí duplicitu cs/sk. Proto se `sk` přidá do `LANGUAGES` až s vlastními SK texty. Do té doby
  musí SK URL mít canonical na cs, nebo noindex.

## Stav po W4-4

* `lang/sk` obsahuje jen `auth.php`, `passwords.php` a `validation.php`: strojový překlad z `lang/cs`, stejné klíče
  i placeholdery (hlídá `tests/Unit/LangSkParityTest.php`), čeká na jedno čtení vlastníkem (owner-queue řádek 9).
  `access.php` a `permissions.php` v `lang/sk` záměrně nejsou a padají přes fallback sk→cs (`AppServiceProvider`).
  Právní texty (VOP, GDPR) se nepřekládaly.
* Přepínač `config('onhost.locale_sk')` (`ONHOST_LOCALE_SK`, výchozí `false`) řídí celou množinu jazyků
  v `SetLocale` (`?locale=` i Accept-Language). Vypnutý: chování beze změny, `sk` → `cs`, `?locale=sk` se ignoruje.
  Zapnutý: `sk` je podporované locale (`tests/Feature/SetLocaleSkTest.php`).
* Kontrola jiných míst, která nastavují locale (`setLocale(|HasLocalePreference|->locale =` přes `app`, `domains`,
  `platform`): jen `SetLocale.php:32` a fallback v `AppServiceProvider.php:48`; `DiscordService.php:165/314/355`
  locale jen porovnává. `HasLocalePreference` se nikde nepoužívá.
* Ceny na locale nezávisí: stejný košík pro `?locale=cs` a `?locale=sk` dává shodné subtotal, tax, total a currency.
* `PublicSeo::LANGUAGES` zůstává `['cs']`, hreflang `sk` se nevydává.

Fáze 2 (samostatný task, podmíněný schválením textů) a zapnutí `ONHOST_LOCALE_SK` mají čtyři podmínky:

1. oprava 19 řádků se sloupcem „en“ výše, kde `sk` dnes padá do angličtiny (samostatný follow-up task
   onhost-backend, mimo tuto větev);
2. přečtení textů `lang/sk` Adrianem (W4-K3);
3. rozhodnutí o `access.php` a `permissions.php` (přeložit, nebo ponechat fallback sk→cs);
4. přidání `sk` do `PublicSeo::LANGUAGES` (hreflang) ve stejném commitu, který přepínač zapíná pro veřejný web.

## Stav po W5-5

Varianta B1: větve opravené hned, za přepínačem `config('onhost.locale_sk')` (výchozí `false`). Rozhoduje
`Lexicon::slovak($locale)`: pravda jen pro `sk` se zapnutým přepínačem. Vypnutý přepínač vrací ve všech větvích výstup
z `8817e21` beze změny, i když `sk` přijde přímo z query (`CartController:84`, `CatalogController:95`).

| řádky | sk se zapnutým přepínačem |
|---|---|
| SurfaceDataController:441 (štítek tarifu) | `lang/sk/surface.php` `most_popular` |
| SurfaceDataController:649 (uptime) | desetinná čárka a ` %` jako cs |
| SurfaceDataController:725 (panel) | české texty panelu (žádná angličtina); vlastní SK texty panelu jsou další krok |
| SurfaceRenderer:1246, 1361, 1362 (`Intl.NumberFormat`) | seam `intlLocale()`: `cs-CZ` / `sk-SK` / `en-US`; vypnuto přesně původní výraz |
| SurfaceRenderer:549, 550 (přepínač jazyka) | beze změny: prototyp zná jen cs a en, `ONHOST.locale = sk` startuje v češtině |
| ContentService:120 (datum) | `j. n.` jako cs |
| ContentService:136 až 140 (sklad) | `lang/sk/surface.php`, skloňování přes `trans_choice` |
| UsageWatch:315 až 317 (metriky) | `lang/sk/surface.php` `metric.*` |
| 20 řádků se sloupcem „cs“ | beze změny: sk dostává češtinu, ne angličtinu |
| Lexicon:295 a :317 | beze změny: `translate()` vrací pro sk český text; tabulka `SK` se nepřidala, částečná tabulka by dala směs cs/sk |

Odchylky od zadání: mapa `Intl` drží pro angličtinu `en-US` (dnešní hodnota), ne `en-GB`, aby se anglický formát
neměnil; tabulka `SK` v `Lexicon` a explicitní SK texty pro 20 řádků „cs“ (asistent, katalog, e-maily Identity,
onboarding, věrnostní program, panel) zůstávají otevřené, protože vyžadují delší SK texty ke čtení vlastníkem.

Měna, její pořadí, ceny, DPH, `TaxRule` ani šablona faktury na locale nezávisí; návrh „pro sk EUR první“
(SurfaceDataController:97, :841) se zamítá, měna jde z košíku a legal entity. `lang/sk/surface.php` je nový soubor bez
dvojčete v `lang/cs` (české texty jsou ve větvích kódu); `LangSkParityTest` hlídá jeho klíče, placeholdery a hlavičku.

**B2 je otevřené rozhodnutí** (sk do `PublicSeo::LANGUAGES`, hreflang, sitemap). Doporučení: podsložka `/sk/` na stejné
doméně (bez kroku v DNS) a `sk` do `LANGUAGES` až ve stejném commitu jako vlastní SK URL. Připomínky Adriana z W4-K3
se zapracují samostatným commitem, až dorazí.
