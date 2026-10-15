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
