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
