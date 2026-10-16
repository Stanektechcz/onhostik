# Runbook: přechod na PHP 8.5 (aaPanel)

W7-6, varianta A. Kód je připravený, přepnutí serverů a povinný CI check jsou kroky vlastníka. Výchozí stav se nemění:
bez nastaveného `PHP_VERSION` používají `infra/aapanel/deploy.sh` i `install.sh` dál `/www/server/php/83/bin/php`
a `infra/docker/app.Dockerfile` staví z `php:8.3-cli-alpine` (`ARG PHP_VERSION=8.3`).

`onhost:doctor` hlásí řádek `runtime | PHP version` s hodnotou `PHP_VERSION`: od 8.4 OK, pod 8.4 neblokující WARN
s odkazem sem.

`composer.json` zůstává `"php": "^8.3"`, dokud produkce na 8.5 neběží stabilně. Až potom se může požadavek zvednout.

## Měření a srovnatelnost

Měření stagingu v `MERENI-vykon-2026-10-16.md` proběhlo na PHP stagingu, pravděpodobně už 8.5. CI běží na 8.3.
Čísla proto nejsou srovnatelná a žádný „zisk 8.5“ se bez měření před a po na stejném stroji neuvádí.

## 1. Předpoklady

- V aaPanelu je nainstalované PHP 8.5 (`/www/server/php/85/bin/php`) s rozšířeními **intl, pdo_pgsql, bcmath, gmp,
  zip, pcntl, opcache, sodium** (a redis jako dnes). Kontrola: `/www/server/php/85/bin/php -m`.
- CI job `php85` v `.github/workflows/tests.yml` je zelený na revizi, která se nasazuje.
- Existuje záloha a funguje návrat (oddíl 5).

## 2. Měření před přepnutím (staging na 83)

1. TTFB podle MERENI §1: pro každou adresu (`/`, `/ceny`, `/kosik`, `/v1/catalog`, `/v1/status`, `/v1/cart`)
   medián z 10 dotazů na udržovaném spojení, např.
   `curl -s -o /dev/null -w '%{time_starttransfer}\n' https://staging.onhost.cz/ceny` (opakovat v jednom procesu
   přes více URL, aby se spojení udrželo). Ze stejného měřicího místa jako po přepnutí.
2. Report výkonnostního rozpočtu W7-5 (e2e smoke) uložit jako výchozí hodnotu.
3. Stav opcache: `opcache_get_status(false)` přes FPM (hit rate, paměť, `restart` počty).

## 3. Přepnutí stagingu

1. aaPanel → web `staging.onhost.cz` → verze PHP: **85**.
2. V prostředí deploye nastavit `PHP_VERSION=85` a `PHP_FPM_RELOAD='/etc/init.d/php-fpm-85 reload'`.
3. Nasadit stejnou revizi (`deploy.sh`) a zkontrolovat `onhost:doctor`: řádek `runtime | PHP version` je OK a nic
   dalšího nového nepadá.
4. Znovu změřit body 2.1–2.3. Rozdíl zapsat do reportu s datem, revizí a měřicím místem.

## 4. Přepnutí produkce

Jen po zelených měřeních na stagingu (žádné zhoršení TTFB ani rozpočtu W7-5, opcache bez restartů, doctor bez nových
FAIL/WARN) a po souhlasu vlastníka. Postup je stejný jako v oddílu 3 pro web `onhost.cz`, mimo špičku. Po přepnutí
zopakovat měření a hlídat chyby v logu aspoň 24 h.

## 5. Návrat na 8.3

1. aaPanel → web → verze PHP zpět na **83**.
2. `PHP_VERSION=83` (nebo proměnnou odstranit) a `PHP_FPM_RELOAD='/etc/init.d/php-fpm-83 reload'`.
3. Reload FPM: `/etc/init.d/php-fpm-83 reload`.
4. Reset opcache (reload FPM ji vyprázdní; jinak `opcache_reset()` přes FPM), poté znovu nasadit stejnou revizi
   a ověřit `onhost:doctor`.

`composer.json` se při návratu neupravuje, protože stále požaduje `^8.3`.
