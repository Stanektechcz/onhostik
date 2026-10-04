<?php

declare(strict_types=1);

namespace App\Http\Support;

use App\Http\Middleware\TokenRouteScope;
use Illuminate\Support\Facades\Cache;
use Onhost\Domain\Identity\Authorization\TokenScopes;
use Onhost\Domain\Notifications\WebhookDispatcher;
use Onhost\Domain\Notifications\Webhooks\WebhookEvents;
use Onhost\Domain\Notifications\Webhooks\WebhookSigner;
use Onhost\Platform\Errors\ProviderErrorCode;
use ReflectionClassConstant;
use Symfony\Component\Finder\Finder;

/**
 * Everything the public API pages say about the API, taken from the code and the configuration (audit 2026-10, package D3).
 *
 * The prototype's `/api` and `/dokumentace` carried a hand-written story: `/v1/servers`, `/v1/backups`, `/v1/audit` and
 * `/v1/export` (no such routes), an `X-Confirm-Delete` header nothing reads, "600 / min" for a key that gets 120, a 24 h
 * webhook retry, six sample events nobody sends. This class is the one place the truth is assembled; the pages read it through
 * the seams (SurfaceController::asset for the two prototype scripts, SurfaceRenderer for the copy inside the page, and
 * ApiDocsController for /dokumentace/api), and tests/Feature/Http/ApiDocsTest.php holds every statement against the routes,
 * the config and the code.
 */
final class PublicApiDocs
{
    /**
     * The endpoints worth showing a customer: [method, path as routed, what it does]. Every one must exist in the router
     * (ApiDocsTest), and the key scope each needs is derived from the same table the token middleware enforces.
     *
     * @var list<array{0:string,1:string,2:string}>
     */
    private const ENDPOINTS = [
        ['GET', '/v1/me', 'kdo klíč je a za kterou organizaci jedná'],
        ['GET', '/v1/services', 'seznam služeb organizace, stránkovaný'],
        ['GET', '/v1/services/{service}', 'detail jedné služby'],
        ['GET', '/v1/services/{service}/usage', 'spotřeba služby'],
        ['GET', '/v1/services/{service}/backups', 'zálohy služby a body obnovy'],
        ['POST', '/v1/services/{service}/actions', 'akce nad službou (restart, záloha, snímek…) — oprávnění podle akce'],
        ['GET', '/v1/dns/zones', 'DNS zóny organizace'],
        ['GET', '/v1/dns/zones/{zone}', 'zóna se záznamy, TTL a stavem DNSSEC'],
        ['POST', '/v1/dns/zones/{zone}/changes', 'úprava záznamů; zapíše se do návrhu, ne rovnou do zóny'],
        ['POST', '/v1/dns/zones/{zone}/commit', 'zveřejnění návrhu zóny'],
        ['GET', '/v1/domains', 'domény organizace'],
        ['GET', '/v1/domains/{domain}', 'detail domény'],
        ['GET', '/v1/invoices', 'faktury, stránkované'],
        ['GET', '/v1/invoices/{invoice}', 'faktura s položkami'],
        ['GET', '/v1/invoices/{invoice}/pdf', 'faktura jako PDF'],
        ['GET', '/v1/wallet', 'stav peněženky'],
        ['GET', '/v1/tickets', 'tikety organizace'],
        ['POST', '/v1/tickets', 'nový tiket'],
        ['POST', '/v1/tickets/{ticket}/messages', 'odpověď v tiketu'],
        ['GET', '/v1/status', 'stav služeb a incidenty — veřejné, bez klíče'],
        ['GET', '/v1/webhooks', 'odběry událostí (jen z panelu)'],
        ['POST', '/v1/webhooks', 'nový odběr událostí (jen z panelu)'],
        ['DELETE', '/v1/webhooks/{endpoint}', 'zrušení odběru (jen z panelu)'],
    ];

    /**
     * Answers the framework and the middleware give before or beside a DomainError: the slug appears in bootstrap/app.php or
     * the idempotency middleware (ApiDocsTest looks each literal up there).
     *
     * @var array<string,array{0:int,1:string}>
     */
    private const FRAMEWORK_SLUGS = [
        'validation_failed' => [422, 'Požadavek nemá platný tvar; pole `errors` říká, které pole a proč.'],
        'unauthenticated' => [401, 'Chybí platný klíč nebo přihlášení.'],
        'forbidden' => [403, 'Přístup je zakázán.'],
        'method_not_allowed' => [405, 'Tahle cesta tuhle metodu nezná.'],
        'rate_limited' => [429, 'Vyčerpaný limit požadavků; počkejte podle hlavičky `Retry-After`.'],
        'csrf_token_mismatch' => [419, 'Přihlášená relace panelu poslala neplatný CSRF token (klíče se netýká).'],
        'invalid_transition' => [409, 'Objekt je ve stavu, ze kterého se tímhle krokem nedá pokračovat.'],
        'invalid_idempotency_key' => [422, 'Hlavička `Idempotency-Key` je delší, než je povoleno.'],
        'idempotency_in_progress' => [409, 'Požadavek se stejným `Idempotency-Key` ještě běží; zopakujte ho se stejným klíčem po době z hlavičky `Retry-After`.'],
        'already_done' => [409, 'Požadavek už proběhl a jeho výsledek obsahoval tajemství, které se ukazuje jednou a neuchovává.'],
    ];

    private const COLORS = ['breaking' => ['nekompatibilní', '#fff', '#c1121f'], 'compatible' => ['kompatibilní', 'var(--color-text)', 'color-mix(in srgb,#1a7f37 22%,transparent)'], 'new' => ['nové', 'var(--color-text)', 'color-mix(in srgb,#1a7f37 22%,transparent)'], 'security' => ['bezpečnost', '#fff', 'var(--color-accent)']];

    /** The anchor of an error slug: what `DomainError::toProblem()` puts into `help` (underscores become hyphens). */
    public static function anchor(string $slug): string
    {
        return str_replace('_', '-', $slug);
    }

    public static function baseUrl(): string
    {
        $configured = config('onhost.api.base_url');

        return is_string($configured) && $configured !== '' ? rtrim($configured, '/') : rtrim((string) config('onhost.portal_url', config('app.url')), '/').'/v1';
    }

    /** Whether a source file of the platform contains a literal: a page mentions a header or an error only when the code can produce it. */
    public static function codeHas(string $path, string $needle): bool
    {
        $file = base_path($path);

        return is_file($file) && str_contains((string) file_get_contents($file), $needle);
    }

    /** @return array{default:int,public:int,page:int,max_page:int,key_max:int,ttl_hours:int,version:?string,in_progress:bool,prefix:string} */
    public static function limits(): array
    {
        $version = config('onhost.api.version');

        return [
            'default' => (int) config('onhost.api.default_rate_limit_per_minute', 120),
            'public' => (int) config('onhost.api.public_rate_limit_per_minute', 600),
            'page' => (int) config('onhost.api.page_size', 40),
            'max_page' => (int) config('onhost.api.max_page_size', 200),
            'key_max' => (int) config('onhost.api.idempotency_key_max_length', 200),
            'ttl_hours' => (int) config('onhost.api.idempotency_ttl_hours', 24),
            // these two exist only once the version policy (D7) and the atomic reservation (D5) are in the code; the page never claims what is not there
            'version' => self::codeHas('app/Http/Middleware/ApiDeprecation.php', "'X-API-Version'") && is_string($version) && $version !== '' ? $version : null,
            'in_progress' => self::codeHas('platform/Http/Middleware/IdempotencyKey.php', "'idempotency_in_progress'"),
            'prefix' => (string) config('onhost.api.token_prefix', 'onh_live_'),
        ];
    }

    /**
     * What the webhook dispatcher does after a failed delivery: the first attempt and one retry per backoff step, the last retry
     * the sum of every backoff after the first attempt (WebhookDispatcher::retryScheduleMinutes / scheduledAttempts).
     *
     * @return array{attempts:int,offsets:list<int>,last_minutes:int,backoff:list<int>}
     */
    public static function retrySchedule(): array
    {
        $retries = WebhookDispatcher::retryScheduleMinutes();

        return ['attempts' => WebhookDispatcher::scheduledAttempts(), 'offsets' => [0, ...$retries], 'last_minutes' => (int) end($retries), 'backoff' => WebhookDispatcher::BACKOFF_MINUTES];
    }

    public static function duration(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $h === 0 ? "{$m} min" : ($m === 0 ? "{$h} h" : "{$h} h {$m} min");
    }

    /** One sentence for every place that talks about the retries. */
    public static function retrySentence(): string
    {
        $r = self::retrySchedule();

        return "{$r['attempts']} pokusů o doručení: hned, a pak po ".implode(', ', array_map(fn (int $o) => self::duration($o), array_slice($r['offsets'], 1)))." od prvního; potom doručení vzdáme (celkem {$r['attempts']} pokusů během ".self::duration($r['last_minutes']).')';
    }

    /** @return list<string> the scopes a token can carry */
    public static function scopes(): array
    {
        return TokenScopes::ALL;
    }

    /** @return array<string,array{0:?string,1:?string}> first path segment → [scope for reads, scope for writes], as the token middleware enforces it */
    private static function families(): array
    {
        /** @var array<string,array{0:?string,1:?string}> $families */
        $families = (new ReflectionClassConstant(TokenRouteScope::class, 'FAMILIES'))->getValue();

        return $families;
    }

    /** What a key needs to call one endpoint, in words. */
    public static function scopeOf(string $method, string $path): string
    {
        $segments = explode('/', trim($path, '/'));
        $family = $segments[1] ?? '';
        if ($family === 'status') {
            return 'bez klíče';
        }
        if ($family === 'me') {
            return 'libovolný klíč';
        }
        $pair = self::families()[$family] ?? null;
        $needed = $pair === null ? null : $pair[in_array($method, ['GET', 'HEAD'], true) ? 0 : 1];
        if ($family === 'services' && ($segments[3] ?? '') === 'actions') {
            return 'services:power nebo services:console podle akce';
        }

        return $needed ?? 'jen přihlášený panel';
    }

    /** @return list<array{m:string,p:string,d:string,scope:string,c:string}> */
    public static function endpoints(): array
    {
        return array_map(fn (array $e) => ['m' => $e[0], 'p' => $e[1], 'd' => $e[2], 'scope' => self::scopeOf($e[0], $e[1]), 'c' => match ($e[0]) {
            'POST' => 'var(--color-accent-700)',
            'DELETE' => '#c1121f',
            default => '#1a7f37',
        }], self::ENDPOINTS);
    }

    /**
     * Every error slug the code can answer with, one row each. Scanned from the source (`new DomainError('slug', 'message', status)`
     * and `DomainError::conflict('slug', 'message')`) plus the framework answers above and `provider_*` for every provider failure
     * class; the scan is cached for an hour (the code does not change under a running process).
     *
     * @return array<string,array{statuses:list<int>,message:string,framework:bool}>
     */
    public static function errorSlugs(): array
    {
        return Cache::remember('onhost:api-docs:error-slugs:'.self::buildMarker(), 86400, fn () => self::scanErrorSlugs());
    }

    /** The newest modification time of what the scan reads, so a deploy that adds an error invalidates the cached index (the marker itself is looked up once a minute). */
    public static function buildMarker(): string
    {
        return (string) Cache::remember('onhost:api-docs:build-marker', 60, function (): int {
            $dirs = array_values(array_filter(array_map(fn (string $d) => base_path($d), ['app', 'domains', 'platform', 'providers', 'bootstrap']), 'is_dir'));
            $newest = 0;
            foreach ((new Finder)->files()->in($dirs)->name('*.php') as $file) {
                $newest = max($newest, $file->getMTime());
            }

            return $newest;
        });
    }

    /** @return array<string,array{statuses:list<int>,message:string,framework:bool}> */
    public static function scanErrorSlugs(): array
    {
        $found = [];
        $add = function (string $slug, ?int $status, string $message) use (&$found): void {
            $found[$slug] ??= ['statuses' => [], 'message' => '', 'framework' => false];
            if ($status !== null && ! in_array($status, $found[$slug]['statuses'], true)) {
                $found[$slug]['statuses'][] = $status;
            }
            if ($found[$slug]['message'] === '' && $message !== '' && ! str_contains($message, '$')) {
                $found[$slug]['message'] = stripcslashes($message);
            }
        };
        $dirs = array_values(array_filter(array_map(fn (string $d) => base_path($d), ['app', 'domains', 'platform', 'providers']), 'is_dir'));
        $files = (new Finder)->files()->in($dirs)->name('*.php')->sortByName();
        $message = "(?:'((?:[^'\\\\]|\\\\.)*)'|\"((?:[^\"\\\\]|\\\\.)*)\")";
        foreach ($files as $file) {
            $code = (string) file_get_contents($file->getPathname());
            if (! str_contains($code, 'DomainError') && ! str_contains($code, "new self('")) {
                continue;
            }
            if (preg_match_all("~new\\s+DomainError\\(\\s*'([a-z][a-z0-9_.\\-]*)'\\s*(?:,\\s*{$message}(?:\\s*,\\s*(\\d{3}))?)?~", $code, $m, PREG_SET_ORDER)) {
                foreach ($m as $x) {
                    $add($x[1], isset($x[4]) ? (int) $x[4] : (isset($x[2]) || isset($x[3]) ? 422 : null), ($x[2] ?? '') !== '' ? $x[2] : ($x[3] ?? ''));
                }
            }
            if (preg_match_all("~DomainError::conflict\\(\\s*'([a-z][a-z0-9_.\\-]*)'\\s*(?:,\\s*{$message})?~", $code, $m, PREG_SET_ORDER)) {
                foreach ($m as $x) {
                    $add($x[1], 409, ($x[2] ?? '') !== '' ? $x[2] : ($x[3] ?? ''));
                }
            }
            if ($file->getFilename() === 'DomainError.php' && preg_match_all("~new self\\('([a-z][a-z0-9_.\\-]*)'~", $code, $m)) {
                foreach ($m[1] as $slug) {
                    $add($slug, $slug === 'not_found' ? 404 : ($slug === 'access_not_approved' ? 403 : null), '');
                }
            }
        }
        $produced = '';
        foreach (['bootstrap/app.php', 'platform/Http/Middleware/IdempotencyKey.php', 'app/Http/Support/ApiContext.php'] as $source) {
            $produced .= is_file(base_path($source)) ? (string) file_get_contents(base_path($source)) : '';
        }
        foreach (self::FRAMEWORK_SLUGS as $slug => [$status, $text]) {
            if (! str_contains($produced, "'$slug'") && ! isset($found[$slug])) {
                continue; // described only when the code can answer it
            }
            $add($slug, $status, $text);
            $found[$slug]['framework'] = true;
        }
        foreach (ProviderErrorCode::cases() as $case) {
            $found['provider_'.strtolower($case->value)] = ['statuses' => [502, 503], 'message' => $case->isRetryable() ? 'Dodavatel služby je přechodně nedostupný; odpověď nese `retryable: true`, zkuste to znovu s odstupem.' : 'Dodavatel služby požadavek odmítl; opakování nepomůže, odpověď říká proč.', 'framework' => true];
        }
        ksort($found);
        foreach ($found as &$row) {
            sort($row['statuses']);
        }

        return $found;
    }

    /** @return list<array{iso:string,date:string,t:string,tag:string,fg:string,bg:string,win:string,d:string}> */
    public static function changelog(): array
    {
        $file = base_path('docs/api/CHANGELOG.md');
        $text = is_file($file) ? (string) file_get_contents($file) : '';
        $entries = [];
        foreach (preg_split('~^## ~m', $text) ?: [] as $block) {
            $lines = explode("\n", trim($block), 2);
            $head = array_map('trim', explode('|', $lines[0]));
            if (count($head) < 3 || ! preg_match('~^\d{4}-\d{2}-\d{2}$~', $head[0]) || ! isset(self::COLORS[$head[1]])) {
                continue;
            }
            [$tag, $fg, $bg] = self::COLORS[$head[1]];
            $entries[] = [
                'iso' => $head[0], 'date' => date('j. n. Y', (int) strtotime($head[0])), 't' => $head[2], 'tag' => $tag, 'fg' => $fg, 'bg' => $bg,
                'win' => $head[3] ?? '—', 'd' => trim(preg_replace('~\s+~', ' ', (string) ($lines[1] ?? '')) ?? ''),
            ];
        }

        return $entries;
    }

    /**
     * The "first call in five minutes" steps. `code` holds the shell lines, `{base}` stays in the text and is the API base URL;
     * docs/api/FIRST-CALL.md carries the same lines (ApiDocsTest compares them).
     *
     * @return list<array{title:string,text:string,code:string}>
     */
    public static function firstCall(): array
    {
        $l = self::limits();

        return [
            ['title' => 'Nastavte adresu a zkuste veřejný koncový bod', 'text' => 'Stav služeb nepotřebuje klíč, takže hned poznáte, že se k API dostanete.', 'code' => 'export ONHOST_API={base}'."\n".'curl -s "$ONHOST_API/status" -H "Accept: application/json"'],
            ['title' => 'Vytvořte klíč', 'text' => 'V panelu v části Účet → API klíče vyberte jen rozsahy, které skript potřebuje ('.implode(', ', self::scopes()).'), případně expiraci. Tajemství se ukáže jednou. Klíč začíná `'.$l['prefix'].'`.', 'code' => 'export ONHOST_TOKEN='.$l['prefix'].'…'],
            ['title' => 'Zeptejte se, kdo klíč je', 'text' => 'Odpověď říká, za kterou organizaci klíč jedná. Jiná organizace se v hlavičce `X-Organization` nastavit nedá.', 'code' => 'curl -s "$ONHOST_API/me" -H "Authorization: Bearer $ONHOST_TOKEN" -H "Accept: application/json"'],
            ['title' => 'Vypište služby a přečtěte si hlavičky', 'text' => 'Stránkuje se přes `limit` a `offset` (výchozí '.$l['page'].', nejvýš '.$l['max_page'].'), celkový počet je v hlavičce `X-Total-Count`, zbývající limit v `X-RateLimit-Remaining`. Potřebuje rozsah `services:read`.', 'code' => 'curl -si "$ONHOST_API/services?limit=5" -H "Authorization: Bearer $ONHOST_TOKEN" -H "Accept: application/json"'],
            ['title' => 'Napište něco s Idempotency-Key', 'text' => 'Zápis, který se při ztracené odpovědi smí opakovat, nese vlastní klíč (nejvýš '.$l['key_max'].' znaků, platí '.$l['ttl_hours'].' h). Potřebuje rozsah `tickets:write`.', 'code' => 'export KEY=$(uuidgen)'."\n".'curl -s -X POST "$ONHOST_API/tickets" -H "Authorization: Bearer $ONHOST_TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" -H "Idempotency-Key: $KEY" -d \'{"subject":"Zkouška API","body":"První volání přes API."}\''],
            ['title' => 'Zopakujte ho a pak změňte tělo', 'text' => 'Stejný klíč a stejné tělo vrátí původní odpověď s hlavičkou `Idempotent-Replayed: true`. Stejný klíč s jiným tělem je `409 idempotency_key_reused`.', 'code' => 'curl -si -X POST "$ONHOST_API/tickets" -H "Authorization: Bearer $ONHOST_TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" -H "Idempotency-Key: $KEY" -d \'{"subject":"Zkouška API","body":"Jiné tělo."}\''],
        ];
    }

    /**
     * The headers a client meets, one row each: [name, direction, meaning].
     *
     * @return list<array{0:string,1:string,2:string}>
     */
    public static function headers(): array
    {
        $l = self::limits();
        $rows = [
            ['Authorization: Bearer …', 'požadavek', 'klíč z panelu; bez něj `401 unauthenticated`, s klíčem bez potřebného rozsahu `403 access_not_approved`'],
            ['Idempotency-Key', 'požadavek', "u POST, PATCH a PUT; nejvýš {$l['key_max']} znaků, jinak `422 invalid_idempotency_key`; platí {$l['ttl_hours']} h; stejný klíč a stejné tělo vrátí původní odpověď (`Idempotent-Replayed: true`), jiné tělo je `409 idempotency_key_reused`"],
            ['X-Organization', 'požadavek', 'organizace, za kterou se jedná; klíč jedná jen za svou vlastní, jiná je odmítnuta'],
            ['X-Total-Count', 'odpověď', "celkový počet řádků u stránkovaných seznamů (`limit` výchozí {$l['page']}, nejvýš {$l['max_page']}; `offset`)"],
            ['X-RateLimit-Limit, X-RateLimit-Remaining', 'odpověď', "limit na minutu ({$l['default']} na klíč, {$l['public']} na adresu u veřejných koncových bodů) a kolik z něj zbývá"],
            ['Retry-After', 'odpověď', 'u `429 rate_limited`: za kolik sekund to zkusit znovu'],
        ];
        if ($l['version'] !== null) {
            $rows[] = ['X-API-Version', 'odpověď', "verze API, která odpověděla (nyní {$l['version']})"];
        }
        if ($l['in_progress']) {
            $rows[] = ['409 idempotency_in_progress', 'odpověď', 'první požadavek s tímhle klíčem ještě běží; zopakujte ho se stejným klíčem po době z hlavičky `Retry-After` a dostanete jeho odpověď'];
        }

        return $rows;
    }

    /** The signature a customer verifies, as the dispatcher builds it. */
    public static function webhookEnvelope(): string
    {
        return "{\n  \"id\": \"<id doručení; stejné při každém opakování>\",\n  \"event\": \"service.created\",\n  \"created_at\": \"2026-10-04T12:00:00+02:00\",\n  \"data\": {\n    \"aggregate\": { \"type\": \"service\", \"id\": \"svc_…\" },\n    \"organization_id\": \"org_…\",\n    \"payload\": { … veřejná pole události … }\n  }\n}";
    }

    /** @return list<string> the event families a webhook can subscribe to (`service`, `invoice` …) */
    public static function eventFamilies(): array
    {
        return WebhookEvents::FAMILIES;
    }

    /** The data the two prototype scripts take over (see publicScript / docsModule). @return array<string,mixed> */
    public static function payload(): array
    {
        $l = self::limits();
        $base = self::baseUrl();
        $line = fn (string $t, string $c = '#d8d6d4') => ['t' => $t, 'c' => $c];
        $errors = [];
        $describe = [
            'unauthenticated' => ['401', 'Chybí klíč, nebo propadl. Vydejte nový v panelu.', 'var(--color-text)', 'color-mix(in srgb,#e0a100 30%,transparent)'],
            'access_not_approved' => ['403', 'Klíč nemá rozsah, který volání potřebuje, nebo je koncový bod jen pro přihlášený panel. Zprávu čtěte celou: jmenuje chybějící rozsah.', 'var(--color-text)', 'color-mix(in srgb,#e0a100 30%,transparent)'],
            'not_found' => ['404', 'Objekt neexistuje, nebo patří jiné organizaci. Rozdíl nepoznáte záměrně.', 'var(--color-text)', 'color-mix(in srgb,#e0a100 30%,transparent)'],
            'validation_failed' => ['422', 'Požadavek nemá platný tvar; pole `errors` jmenuje pole a důvod.', 'var(--color-text)', 'color-mix(in srgb,#e0a100 30%,transparent)'],
            'idempotency_key_reused' => ['409', 'Stejný `Idempotency-Key` už poslal jiné tělo. Nový klíč pro nový požadavek.', '#fff', '#c1121f'],
            'rate_limited' => ['429', "Vyčerpaný limit ({$l['default']} za minutu na klíč). `Retry-After` říká, za kolik sekund to projde.", 'var(--color-text)', 'color-mix(in srgb,#e0a100 30%,transparent)'],
            'provider_transient' => ['503', 'Dodavatel služby je přechodně nedostupný; odpověď nese `retryable: true`. Zkuste to s odstupem.', 'var(--color-text)', 'color-mix(in srgb,#1a7f37 22%,transparent)'],
        ];
        if ($l['in_progress']) {
            $describe = array_slice($describe, 0, 5, true) + ['idempotency_in_progress' => ['409', 'Požadavek se stejným klíčem ještě běží. Zopakujte ho se stejným klíčem po `Retry-After` a dostanete jeho odpověď.', '#fff', '#c1121f']] + array_slice($describe, 5, null, true);
        }
        foreach ($describe as $slug => [$code, $d, $fg, $bg]) {
            $errors[] = ['code' => $code, 'slug' => $slug, 'fg' => $fg, 'bg' => $bg, 'd' => $d, 'body' => '{ "error": "'.$slug.'", "status": '.$code.', "help": "/dokumentace/api#'.self::anchor($slug).'" }'];
        }
        $hook = fn (string $ev, string $note, string $body) => ['ev' => $ev, 'd' => $ev, 'retry' => 'opakování: '.self::retrySentence(), 'note' => $note, 'body' => $body];
        $families = implode(', ', array_map(fn (string $f) => $f.'.*', self::eventFamilies()));

        return [
            'api' => [
                'heads' => [
                    ['k' => 'Základ', 'v' => 'REST + JSON', 'n' => 'žádné SDK není povinné'],
                    ['k' => 'Limit', 'v' => $l['default'].' / min', 'n' => 'na klíč; zpomalí odpovědí 429 s Retry-After'],
                    ['k' => 'Status API', 'v' => 'bez klíče', 'n' => "veřejné čtení, {$l['public']} / min na adresu"],
                    ['k' => 'Verze', 'v' => 'v1', 'n' => 'v2 bez odstřižení v1'],
                ],
                'auth' => [
                    ['t' => 'Klíč s rozsahem', 'd' => 'Klíč vytvoříte v panelu (Účet → API klíče), dostane rozsahy ('.implode(', ', self::scopes()).') a volitelně datum expirace. Tajemství zobrazíme jednou — podruhé už ho neumíme přečíst ani my.', 'c' => 'Authorization: Bearer '.$l['prefix'].'…'],
                    ['t' => 'Limit zpomaluje', 'd' => "Na klíč {$l['default']} požadavků za minutu (u veřejných koncových bodů {$l['public']} na adresu). Každá odpověď nese, kolik zbývá; po vyčerpání přijde 429 rate_limited a hlavička Retry-After se sekundami do dalšího pokusu.", 'c' => 'X-RateLimit-Remaining: '.max(0, $l['default'] - 1)],
                    ['t' => 'Klíč jedná za jednu organizaci', 'd' => 'Klíč patří organizaci, která ho vydala. Hlavička X-Organization smí jmenovat jen ji, jiná je odmítnuta. Přístup ke službě sdílený jiným člověkem a správa webhooků jsou jen z panelu.', 'c' => 'X-Organization: org_…'],
                    ['t' => 'Chyby se kódem', 'd' => 'Každá chyba má kód v poli error, větu, status a odkaz help na popis kódu. Parsujte kód, ne větu. Index všech kódů je na stránce dokumentace API.', 'c' => '{"error":"access_not_approved","status":403,"help":"/dokumentace/api#access-not-approved"}'],
                ],
                'endpoints' => array_map(fn (array $e) => ['m' => $e['m'], 'p' => $e['p'], 'd' => $e['d'].' · '.$e['scope'], 'c' => $e['c']], self::endpoints()),
                'examples' => [
                    ['t' => 'První volání bez klíče', 'n' => 'stav služeb nepotřebuje registraci ani klíč', 'lines' => [
                        $line("curl -s {$base}/status \\"), $line('  -H "Accept: application/json"', '#b8ff2e'), $line('# veřejný koncový bod · '.$l['public'].' / min na adresu', 'rgba(216,214,212,.5)'),
                    ]],
                    ['t' => 'Seznam služeb', 'n' => 'klíč s rozsahem services:read; celkový počet je v X-Total-Count', 'lines' => [
                        $line("curl -si \"{$base}/services?limit=5\" \\"), $line('  -H "Authorization: Bearer $ONHOST_TOKEN"', '#b8ff2e'), $line('# X-Total-Count: …  ·  X-RateLimit-Remaining: …', 'rgba(216,214,212,.5)'),
                    ]],
                    ['t' => 'Zápis, který smíte opakovat', 'n' => 'rozsah tickets:write; stejný klíč vrátí původní odpověď', 'lines' => [
                        $line("curl -X POST {$base}/tickets \\"), $line('  -H "Authorization: Bearer $ONHOST_TOKEN" \\'), $line('  -H "Idempotency-Key: $(uuidgen)" \\'), $line('  -d \'{"subject":"Zkouška API","body":"První volání."}\'', '#b8ff2e'), $line('# opakování se stejným klíčem: Idempotent-Replayed: true', 'rgba(216,214,212,.5)'),
                    ]],
                ],
                'errors' => $errors,
                'idem' => [
                    ['t' => 'Idempotency-Key', 'd' => "Pošlete vlastní klíč (nejvýš {$l['key_max']} znaků) u každého POST, PATCH a PUT, který něco vytváří nebo účtuje. Druhé volání se stejným klíčem a tělem vrátí původní odpověď s hlavičkou Idempotent-Replayed, ne nový objekt.", 'v' => $l['ttl_hours'].' h'],
                    ['t' => 'Stejný klíč, jiné tělo', 'd' => 'Vrátíme 409 idempotency_key_reused. Tiché přepsání by z chyby ve vašem skriptu udělalo chybu na vaší faktuře.', 'v' => '409'],
                    ...($l['in_progress'] ? [['t' => 'První požadavek ještě běží', 'd' => 'Druhý požadavek se stejným klíčem dostane 409 idempotency_in_progress a Retry-After. Zopakujte ho se stejným klíčem — dostanete odpověď prvního.', 'v' => '409']] : []),
                    ['t' => 'Tajemství se ukazuje jednou', 'd' => 'Odpověď, která vydala tajemství (nový klíč, heslo), se neuchovává. Její opakování je 409 already_done — tajemství podruhé neukážeme.', 'v' => '409'],
                    ['t' => 'Co klíč nezakryje', 'd' => 'Dvě různá volání se dvěma různými klíči jsou dvě operace. Idempotence chrání před opakováním téhož požadavku, ne před chybou ve vašem cyklu.', 'v' => 'dva klíče, dvě volání'],
                ],
                'changes' => self::changelog(),
                'hookUrl' => 'https://vas-server.cz/hooks/onhost',
            ],
            'hooks' => [
                $hook('obálka', 'Každé doručení je POST s JSON obálkou {id, event, created_at, data}. Hlavičky nesou událost (X-ONhost-Event), id doručení (X-ONhost-Delivery) a čas (X-ONhost-Timestamp). Adresa musí být https na portu '.implode(' nebo ', WebhookDispatcher::ALLOWED_PORTS).', veřejná a odpovědět do několika sekund; přesměrování nesledujeme. Doručení je alespoň jednou: stejné doručení může přijít víckrát (opakování, ruční opakování), proto si ukládejte X-ONhost-Delivery a to, co už máte, zahazujte.', self::webhookEnvelope()),
                $hook('podpis', 'Podpis je v hlavičce X-ONhost-Signature jako v1=<hex>, kde hex je HMAC-SHA256 tajemstvím odběru z textu `<X-ONhost-Timestamp>.<tělo>` — tělo se podepisuje tak, jak přišlo. Porovnávejte konstantním časem a odmítněte čas starší než '.intdiv(WebhookSigner::TOLERANCE_SECONDS, 60).' minut. Tajemství je celý řetězec whsec_…; ukáže se jen při založení odběru.', "expected = hmac_sha256(secret, timestamp + \".\" + raw_body)\nvalid = constant_time_equals(\"v1=\" + hex(expected), header[\"X-ONhost-Signature\"])"),
                $hook('události', 'Posíláme události organizace z těchto rodin: '.$families.'. Události vlastního provozu platformy se k zákazníkovi nedostanou. Zkušební doručení webhook.ping lze poslat jednou za 30 s na odběr.', implode("\n", array_map(fn (string $f) => $f.'.*', self::eventFamilies()))),
                $hook('opakování', 'Neúspěch je jiná odpověď než 2xx nebo chyba spojení. Po neúspěchu se čeká podle tabulky; po vyčerpání pokusů je doručení mrtvé a zůstane v seznamu doručení odběru; odběr po '.WebhookDispatcher::SUSPEND_AFTER.' neúspěšných pokusech za sebou pozastavíme (stav suspended, zákazník se to dozví) a zapne se znovu v panelu. Jedno doručení lze celkem zkusit nejvýš '.WebhookDispatcher::MAX_ATTEMPTS.'krát včetně ručních opakování.', "pokus  čas od prvního pokusu\n".implode("\n", array_map(fn (int $o, int $i) => str_pad((string) ($i + 1), 5).'  '.($o === 0 ? 'hned' : self::duration($o)), self::retrySchedule()['offsets'], array_keys(self::retrySchedule()['offsets'])))),
            ],
            'docs' => self::docsArticles(),
        ];
    }

    /**
     * The two API guides of /dokumentace, in the prototype's block format, as `{id: {cs: article, en: article}}`.
     *
     * @return array<string,array{cs:array<string,mixed>,en:array<string,mixed>}>
     */
    public static function docsArticles(): array
    {
        $l = self::limits();
        $base = self::baseUrl();
        $blocks = [['k' => 'p', 't' => 'Pět minut, šest kroků, všechno kopírovatelné. Adresa API je '.$base.'.']];
        foreach (self::firstCall() as $i => $s) {
            $blocks[] = ['k' => 'h', 't' => ($i + 1).'. '.$s['title']];
            $blocks[] = ['k' => 'p', 't' => str_replace('{base}', $base, $s['text'])];
            $blocks[] = ['k' => 'code', 'lang' => 'bash', 't' => str_replace('{base}', $base, $s['code'])];
        }
        $blocks[] = ['k' => 'note', 'tone' => 'tip', 'title' => 'Celá reference', 't' => 'Všechny koncové body, schémata a index chybových kódů jsou na stránce /dokumentace/api; smlouva ke stažení je /openapi.yaml.'];
        $limits = [
            ['k' => 'h', 't' => 'Chybová odpověď'],
            ['k' => 'code', 'lang' => 'json', 't' => "{\n  \"error\": \"access_not_approved\",\n  \"message\": \"The API token lacks the services:read scope.\",\n  \"status\": 403,\n  \"help\": \"/dokumentace/api#access-not-approved\"\n}"],
            ['k' => 'p', 't' => 'Parsujte pole error, ne větu — věta se mění. Pole help vede na popis kódu v indexu na /dokumentace/api; validační chyby navíc nesou pole errors s názvy polí.'],
            ['k' => 'table', 'head' => ['HTTP', 'Kdy', 'Co udělat'], 'rows' => [
                ['401', 'klíč chybí nebo propadl', 'vydat nový'],
                ['403', 'klíč nemá rozsah, nebo je koncový bod jen pro panel', 'doplnit rozsah u klíče'],
                ['404', 'objekt neexistuje nebo patří jiné organizaci', 'zkontrolovat id a organizaci'],
                ['409', 'konflikt stavu nebo Idempotency-Key', 'přečíst error'],
                ['422', 'platný tvar, neproveditelné', 'přečíst error a errors'],
                ['429', 'vyčerpaný limit', 'čekat podle Retry-After'],
                ['502, 503', 'dodavatel služby', 'zopakovat s odstupem, když je retryable'],
            ]],
            ['k' => 'h', 't' => 'Limity'],
            ['k' => 'p', 't' => "Na klíč {$l['default']} požadavků za minutu, u veřejných koncových bodů {$l['public']} za minutu na adresu."],
            ['k' => 'code', 'lang' => 'http', 't' => "X-RateLimit-Limit: {$l['default']}\nX-RateLimit-Remaining: ".($l['default'] - 1)."\n\n(po vyčerpání)\nHTTP/1.1 429\nRetry-After: 41"],
            ['k' => 'h', 't' => 'Webhooky'],
            ['k' => 'p', 't' => 'Dlouhé úlohy nedokončí požadavek. Místo dotazování si nechte posílat události; odběr zakládáte v panelu. Doručení je podepsané a opakuje se: '.self::retrySentence().'. Doručení je alespoň jednou: ukládejte si X-ONhost-Delivery a doručení, které už znáte, zahazujte.'],
            ['k' => 'code', 'lang' => 'php', 't' => "\$ts = \$_SERVER['HTTP_X_ONHOST_TIMESTAMP'] ?? '';\n\$body = file_get_contents('php://input');\n\$calc = 'v1=' . hash_hmac('sha256', \$ts . '.' . \$body, getenv('ONHOST_WEBHOOK_SECRET'));\n\nif (!hash_equals(\$calc, \$_SERVER['HTTP_X_ONHOST_SIGNATURE'] ?? '')) {\n    http_response_code(400);\n    exit;\n}"],
            ['k' => 'note', 'tone' => 'tip', 'title' => 'Odpovězte rychle', 't' => 'Doručení čeká na odpověď jen několik sekund. Zpracování dejte do fronty a hned odpovězte 2xx; jiná odpověď se počítá jako neúspěch a doručení se opakuje. Odběr smí mířit jen na https, port '.implode(' nebo ', WebhookDispatcher::ALLOWED_PORTS).'.'],
        ];

        $updated = self::changelog()[0]['date'] ?? date('j. n. Y', (int) filemtime(base_path('docs/api/CHANGELOG.md')));
        $article = fn (string $id, string $title, string $lead, array $b) => ['id' => $id, 'sec' => 'api', 'read' => '5 min', 'updated' => $updated, 'title' => $title, 'lead' => $lead, 'blocks' => $b];

        return [
            'api-start' => [
                'cs' => $article('api-start', 'První volání za 5 minut', 'Od klíče po první zápis s Idempotency-Key. Příklady jsou pro curl a odpovídají tomu, co API opravdu dělá.', $blocks),
                'en' => $article('api-start', 'Your first call in 5 minutes', 'From a key to the first write with an Idempotency-Key. The examples use curl and match what the API really does.', $blocks),
            ],
            'api-limity' => [
                'cs' => $article('api-limity', 'Chyby, limity a webhooky', 'Co API odpovídá, když něco nejde, jak rychle se smí volat a jak se hlídá doručení webhooků.', $limits),
                'en' => $article('api-limity', 'Errors, limits and webhooks', 'What the API answers when something fails, how fast it may be called and how webhook delivery is retried.', $limits),
            ],
        ];
    }

    /** The prototype's pages show text as written, so the backticks that mark code in this class's sentences go (code blocks keep theirs). */
    private static function plain(mixed $value, bool $code = false): mixed
    {
        if (is_string($value)) {
            return $code ? $value : str_replace('`', '', $value);
        }
        if (! is_array($value)) {
            return $value;
        }
        $isCode = ($value['k'] ?? '') === 'code';
        foreach ($value as $key => $item) {
            $value[$key] = self::plain($item, $isCode && $key === 't');
        }

        return $value;
    }

    /** The prototype's onhost-public.js with its API data replaced (outside demo mode). */
    public static function publicScript(string $original): string
    {
        $data = self::plain(self::payload());
        unset($data['docs']); // the guides travel with onhost-docs.js
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);

        return $original."\n;(function () {\n  var P = window.ONHOST_PUBLIC, D = ".$json.";\n  if (!P || !P.api) return;\n"
            ."  Object.keys(D.api).forEach(function (k) { P.api[k] = D.api[k]; });\n"
            ."  // the prototype's \"missing\" list stays, minus the delete-confirmation header that no route reads\n"
            ."  P.api.missing = (P.api.missing || []).filter(function (m) { return m.t !== 'DELETE bez potvrzení'; });\n"
            ."  P.hooks = D.hooks;\n})();\n";
    }

    /**
     * The prototype's onhost-docs.js with the two API guides replaced. Returns null when the module no longer has the shape
     * the seam expects (a drifted prototype keeps its own text; ApiDocsTest fails).
     */
    public static function docsModule(string $original): ?string
    {
        $needle = 'export function docsArticles(cs) {';
        if (substr_count($original, $needle) !== 1) {
            return null;
        }
        $json = json_encode(self::plain(self::docsArticles()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);

        return str_replace($needle, 'function docsArticlesPrototype(cs) {', $original)
            ."\nconst API_ARTICLES = ".$json.";\n"
            ."export function docsArticles(cs) {\n  return docsArticlesPrototype(cs).map(function (a) { return API_ARTICLES[a.id] ? API_ARTICLES[a.id][cs ? 'cs' : 'en'] : a; });\n}\n";
    }

    /** Sentences inside Onhost.dc.html that quote a number; replaced in SurfaceRenderer, anchor by anchor. @return array<string,string> needle → replacement */
    public static function pageCopy(): array
    {
        $l = self::limits();
        $base = self::baseUrl();
        $retry = self::retrySchedule();
        $window = self::duration($retry['last_minutes']);

        return [
            "'REST nad https://api.onhost.cz/v1, autentizace klíčem s rozsahem. Žádné SDK není povinné a verzi neodstřihneme dřív než po dvanácti měsících.'" => "'REST nad {$base}, autentizace klíčem s rozsahem. Žádné SDK není povinné a verzi neodstřihneme dřív než po dvanácti měsících.'",
            "'REST over https://api.onhost.cz/v1, authenticated by a scoped key. No SDK is mandatory and we never retire a version in under twelve months.'" => "'REST over {$base}, authenticated by a scoped key. No SDK is mandatory and we never retire a version in under twelve months.'",
            "'Stránkování přes limit a offset, celkový počet v hlavičce X-Total-Count. Výchozí okno je 40 záznamů.'" => "'Stránkování přes limit a offset, celkový počet v hlavičce X-Total-Count. Výchozí okno je {$l['page']} záznamů, nejvýš {$l['max_page']}. Každý koncový bod uvádí, jaký rozsah klíče potřebuje. Úplná reference je v dokumentaci API.'",
            "'Pagination via limit and offset, the total in the X-Total-Count header. The default window is 40 records.'" => "'Pagination via limit and offset, the total in the X-Total-Count header. The default window is {$l['page']} records, at most {$l['max_page']}. Each endpoint says which key scope it needs. The full reference is in the API documentation.'",
            "'Podepisujeme tajemstvím odběru a opakujeme 24 hodin. Vyberte událost a uvidíte skutečné tělo, které vám přijde.'" => "'Podepisujeme tajemstvím odběru a opakujeme: {$retry['attempts']} pokusů během {$window}. Vyberte téma a uvidíte, jak doručení vypadá a jak ho ověřit.'",
            "'Signed with the subscription secret and retried for 24 hours. Pick an event to see the actual body you receive.'" => "'Signed with the subscription secret and retried: {$retry['attempts']} attempts within {$window}. Pick a topic to see what a delivery looks like and how to verify it.'",
            // the status page (#/stav): the endpoint is public but not unlimited, and its answer is {data: {overall, components[], incidents[]}}
            "'Stav je i na veřejném endpointu bez klíče a bez limitu. Když si dostupnost můžete měřit sami, nemusíte věřit našemu marketingu.'" => "'Stav je i na veřejném endpointu bez klíče (čtení má limit {$l['public']} za minutu na adresu). Když si dostupnost můžete měřit sami, nemusíte věřit našemu marketingu.'",
            "'The status is also on a public endpoint with no key and no rate limit. If you can measure uptime yourself, you need not trust our marketing.'" => "'The status is also on a public endpoint with no key (reads are limited to {$l['public']} a minute per address). If you can measure uptime yourself, you need not trust our marketing.'",
            "'curl -s https://api.onhost.cz/v1/status | jq \'.services[] | {name, uptime_30d, open_incidents}\''" => "'curl -s {$base}/status | jq \'.data | {overall, components: [.components[] | {key, state, u}], open_incidents: (.incidents | length)}\''",
        ];
    }
}
