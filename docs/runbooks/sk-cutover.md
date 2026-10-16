# Runbook: přepnutí slovenského webu (onhost.cz/sk/)

W6-6, rozhodnutí vlastníka 2026-10-16: slovenský veřejný web běží na stejné doméně pod `https://onhost.cz/sk/…`.
Doména `onhost.sk` (a `www.onhost.sk`) jen trvale přesměrovává (301) na `https://onhost.cz/sk` + původní cesta a query.
V kódu je vše připraveno a vypnuté přepínačem `ONHOST_LOCALE_SK` (výchozí `false`): při vypnutém přepínači vrací `/sk/…`
404 a sitemap i hreflang jsou jen české.

## Co dělá kód (při `ONHOST_LOCALE_SK=true`)

* Routa `surface.public.sk` (`/sk/{path?}`, jen veřejné cesty) vykreslí veřejnou plochu s locale `sk`; `/sk/panel`
  a `/sk/sprava` jsou 404.
* hreflang `cs` / `sk` / `x-default` (= cs) na obou jazykových verzích, canonical slovenské stránky ukazuje na `/sk/…`.
* `/sitemap.xml` obsahuje slovenské URL jako samostatné `<url>` s `<xhtml:link rel="alternate">`.
* Formuláře (`/sk/kosik`, `/sk/prihlaseni`, `/sk/registrace`, `/sk/obnova-hesla`) mají `noindex` a nejsou v sitemap ani hreflang.

## Kroky vlastníka (cutover)

1. **DNS:** `onhost.sk` a `www.onhost.sk` (A/AAAA) na edge server. TTL předem snížit na 300 s.
2. **Edge:** nasadit jeden ze souborů
   * Caddy: `infra/edge/Caddyfile.sk-redirect` (import z `conf.d`, certifikát vydá Caddy sám),
   * nginx: `infra/edge/nginx-sk-redirect.conf` (certifikát pro oba hosty přes certbot webroot `/var/www/acme`).
   Ověřit: `curl -sI https://onhost.sk/ceny?x=1` → `301`, `Location: https://onhost.cz/sk/ceny?x=1`.
3. **Aplikace:** nastavit `ONHOST_LOCALE_SK=true`, `php artisan config:cache` a `php artisan route:cache`
   (routa je registrovaná vždy, cache se nemusí měnit podle přepínače).
4. **Kontrola:** `https://onhost.cz/sk/ceny` → 200, hreflang trojice v hlavičce stránky, `/sitemap.xml` obsahuje
   `/sk/` URL; v Search Console odeslat sitemap znovu.

## Návrat

* **Před zaindexováním** (sk URL ještě nejsou ve vyhledávači): `ONHOST_LOCALE_SK=false` + `config:cache`; `/sk/…` je 404,
  sitemap a hreflang jsou zpět jen české. Edge přesměrování `.sk` lze ponechat nebo vrátit v DNS.
* **Po zaindexování** nikdy nenechávat `/sk/…` na 404: na edge nastavit trvalé přesměrování `/sk/*` na českou cestu
  (`/sk/ceny` → `/ceny`, `/sk/` → `/`, 301, query zachovat), teprve potom `ONHOST_LOCALE_SK=false`. Přesměrování z `onhost.sk`
  změnit na `https://onhost.cz{uri}` (301). Sitemap tím ztratí sk URL a vyhledávač převezme české.
