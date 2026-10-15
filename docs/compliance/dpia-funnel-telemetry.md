# DPIA — funnel telemetry (`cart.quote.requested`, W4-6)

Stav: návrh pro schválení onhost-security (krok 0 návrhu W4-6, varianta A'). Zapnutí na produkci je produkční krok vlastníka.

## Účel

Zjistit, které tarify si lidé nechávají nacenit v košíku, a porovnat to s existujícími událostmi `order.placed`
a `order.paid` (konverze). Zobrazení ceníku se neměří. Metrika slouží jen provozovateli platformy.

## Zpracovávaná data

Událost `cart.quote.requested` (outbox, `aggregate_type` = `metric`, `aggregate_id` = den `YYYY-MM-DD`) nese výhradně:

| Klíč | Hodnoty |
| --- | --- |
| `plan_keys` | seřazené, unikátní klíče tarifů, které existují v katalogu, nejvýš 20 |
| `period` | `month` / `year` |
| `locale` | `cs` / `sk` / `en` |

Denní agregát `funnel_daily`: `date`, `plan_key`, `period`, `locale`, `count`.

## Žádné osobní údaje

Neukládá se IP adresa, User-Agent, `user_id`, `organization_id`, e-mail, částky, ID košíku ani ID nabídky.
Řádek outboxu má `organization_id` null.

## Řádek outboxu

Řádek outboxu nese `correlation_id` požadavku, který je propojitelný s aplikačními logy. Proto se řídí retencí outboxu:
zveřejněný řádek se maže po 30 dnech (`onhost.outbox.retention_days`, rozhodnutí A1).

## Agregát

`funnel_daily` neobsahuje žádný identifikátor osoby, organizace, košíku ani požadavku. Je jen pro platformu: nad agregátem
nevzniká žádný endpoint, tenantská obrazovka ani řádek Doctoru.

Plní ho posluchač při relay outboxu (+1 pro každý `plan_key`). Pokud relay spadne po zápisu a zprávu doručí znovu, počet se
zdvojí; to se toleruje a metrika je proto **přibližná**.

## Vypnutí

Přepínač `ONHOST_FUNNEL_METRICS` (výchozí `false`). Vypnutý přepínač = nic se nepublikuje ani neagreguje.

## Ochrana malých čísel

V jakémkoli reportu nad agregátem se buňky s `count < 5` nezobrazují.

## Throttling

Endpoint `POST /v1/cart/quote` je ve skupině `throttle:public`; požadavky odmítnuté throttlingem se nepočítají.

## Report

`php artisan onhost:funnel-report {--days=30} {--group=day|week} {--json}` (W5-6) je jediný report nad agregátem. Je jen
pro CLI platformy; nemá route, tenantskou obrazovku ani config klíč. Spuštění na produkci je produkční akce a smí ho provést
jen vlastník (W5-K2).

* **Jen čtení:** nic nezapisuje, nepublikuje event a nepotřebuje migraci. `--days` musí být 1-366, jinak skončí s kódem 1.
* **Zdroje:** `funnel_daily` (`SUM(count)` po `date`) a z `orders` jen počty: *placed* = `placed_at` v okně, *paid* =
  `paid_at` v okně a `cancelled_at IS NULL`, podle data `paid_at`. Dny i týdny (ISO, od pondělí) jsou v **UTC**, stejně jako
  `funnel_daily`.
* **Trh:** řádky objednávek se člení podle `orders.currency` jako proxy trhu (CZK a EUR se nemíchají). Poptávky ve
  `funnel_daily` měnu nemají, proto se vypisují za období souhrnně za všechny trhy.
* **Refundace se neodečítají:** vazba refundace → objednávka ve schématu není jednoznačná (`payment_refunds` vede přes
  `payment_intent_id` a může být částečná, refundace do peněženky vazbu na platbu nemají). Sloupec se proto jmenuje
  `paid (bez odečtu refundací)`.
* **Žádné částky ani identifikátory:** výstup nikdy neobsahuje `organization_id`, `user_id`, e-mail, ID či číslo
  objednávky ani IP. Tenant scope se obchází záměrně (`DB::table`, docblock „platformní agregát, W5-6“), protože výstupem
  jsou jen počty za celou platformu.
* **Ochrana malých čísel:** buňka `< 5` se vypíše jako `<5` (v JSON `{"value": null, "suppressed": true}`). Součty se
  počítají jen z viditelných buněk, a jsou proto **dolní mez**. Konverze (paid/placed, placed/poptávky) se počítá jen tam,
  kde jsou obě složky ≥ 5.
* **Vypnutý přepínač:** při `funnel_metrics=false` se poptávky nevypisují (JSON `quotes: null`, `funnel_metrics: false`) a
  report upozorní „funnel_metrics vypnuto, konverze se nepočítá“.
