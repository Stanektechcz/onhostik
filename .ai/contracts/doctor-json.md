# Kontrakt: `onhost:doctor --json` (W5-7)

Tvar, který tiskne `app/Console/Commands/Doctor.php` (:177) a čte `infra/aapanel/deploy-gate.php` (`readReport` :554, `rows` :194-203).
Kontrakt výstup nemění, jen ho popisuje. Hlídají ho `tests/Feature/Platform/DoctorJsonContractTest.php` a
`tests/Feature/Platform/StagingIsolationDoctorTest.php` (řádek `fail_everywhere`).

## Horní úroveň

| klíč | typ | význam |
|---|---|---|
| `environment` | string | `app()->environment()` |
| `fail` | int | počet řádků se stavem `FAIL` |
| `warn` | int | počet řádků se stavem `WARN` |
| `checks` | list | řádky v pořadí výpisu |

## Řádek (`checks[]`), klíče přesně v tomto pořadí

| klíč | typ | význam |
|---|---|---|
| `area` | string | oblast (např. `storage`, `launch`) |
| `check` | string | název kontroly; `area|check` je klíč řádku v deploy gate a v expected-nonok seznamu |
| `status` | `OK` \| `WARN` \| `FAIL` | |
| `detail` | string | zjištění |
| `remedy` | string | náprava; prázdný řetězec u `OK` |
| `blocking` | bool | `true`: v produkci FAIL, jinde WARN (`Doctor::add`) |

`fail_everywhere` (`Doctor::addChecked`, `domains/Platform/IsolationChecks.php`) je vstupní příznak kontroly, ne klíč výstupu:
neúspěšný řádek je `FAIL` v každém prostředí a má `blocking: true`.

## Pravidla změn

- Nový klíč řádku nebo horní úrovně je změna kontraktu: upravit tento soubor, contract test a ověřit deploy gate.
- Odebrat nebo přejmenovat `area`, `check`, `status` nebo `checks` nelze bez současné změny `deploy-gate.php`.
