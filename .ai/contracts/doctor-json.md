# Kontrakt: `php artisan onhost:doctor --json` → `infra/aapanel/deploy-gate.php`

* Producent: `app/Console/Commands/Doctor.php` (výpis JSON, ~ř. 177).
* Konzument: `infra/aapanel/deploy-gate.php` (`readReport()` ~ř. 555–566, `rows()` ~ř. 195–208), volaný z `deploy.sh`.
* Testy: `tests/Feature/Platform/DoctorJsonContractTest.php` (tvar + reálný výstup projde parserem),
  `tests/Feature/Platform/DeployGateTest.php` (soudce; existence gatovaných názvů řádků).

## Obálka

```
{ "environment": string, "fail": int, "warn": int, "checks": list<Row> }
```

## Řádek (`Row`)

```
{ "area": string, "check": string, "status": "OK"|"WARN"|"FAIL", "detail": string, "remedy": string, "blocking": bool }
```

* Název řádku je `area|check`; deploy-gate na něj gatuje (seznamy HARD, GATED a drained v deploy-gate.php).
  Názvy musí být v rámci jednoho výstupu unikátní (parser je klíčuje podle názvu).
* `blocking=true` platí pro každý řádek se `status=FAIL`, včetně `addChecked` s `fail_everywhere` (Doctor.php ~ř. 482).
* Neblokující řádek (`blocking=false`) je nejhůř `WARN`.

## Co konzument čte

* Pouze `environment`, `checks[].area`, `checks[].check` a `checks[].status`.
* `checks` musí být JSON list (`array_is_list`), jinak HARD-FAIL „not JSON with a checks list“.
* Toleruje text před JSON (např. PHP notice): bere nejvnější objekt `{ … }`.

## Pravidla změny

* Přidat nový klíč (do obálky nebo řádku) smí kdokoli bez úpravy deploy-gate.
* Přejmenování nebo odebrání `environment`/`checks`/`area`/`check`/`status`, změna povolených hodnot `status`
  nebo změna názvu řádku, na který deploy-gate gatuje (HARD / GATED / drained), musí jít **ve stejném PR** jako
  úprava `infra/aapanel/deploy-gate.php` a `tests/Feature/Platform/DeployGateTest.php`.
