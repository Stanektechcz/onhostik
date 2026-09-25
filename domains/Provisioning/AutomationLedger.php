<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Onhost\Domain\Provisioning\Jobs\QueueHeartbeat;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Settings\SettingsStore;

/**
 * What the automations did last (audit §5f-2) and which of them staff switched off (§5g-7): every scheduled rule
 * records its last run and the counts it reported, so the staff console shows "usage watch · before 40 min ·
 * 212 checked, 3 warned" instead of a static list. A switched-off rule still runs on schedule but records a skip,
 * so the console shows it is off rather than silent. The two rules the platform cannot live without (dispatching
 * operations, probing the panels) have no switch — the provisioning freeze is the tool for an incident.
 * Runs are kept in the cache for six weeks; the switches live in the settings table.
 */
final class AutomationLedger
{
    public const TTL_SECONDS = 42 * 86400;

    public const SETTING = 'automation.disabled';

    /** rules that are off unless staff switched them on (`default_off`) */
    public const SETTING_ON = 'automation.enabled';

    /** Older than this and the scheduler / the queue worker count as down (minutes). */
    public const STALE_MINUTES = 5;

    /** The rules the console lists: key, command, human name, what it does, when it runs, whether staff may switch it off. @return list<array{key:string,command:?string,name:string,does:string,runs:string,switchable:bool}> */
    public const RULES = [
        ['key' => 'provisioning.tick', 'command' => 'onhost:provisioning:tick', 'name' => 'Provisioning', 'does' => 'spouští splatné operace a oživuje zaseknuté', 'runs' => 'každou minutu', 'switchable' => false],
        ['key' => 'integrations.health', 'command' => 'onhost:integrations:health', 'name' => 'Zdraví integrací', 'does' => 'ping každé instance panelu, výpadek = interní oznámení; hlídá i frontu úloh', 'runs' => 'každou minutu', 'switchable' => false],
        ['key' => 'usage.watch', 'command' => 'onhost:services:usage-watch', 'name' => 'Hlídání využití tarifu', 'does' => 'při 85/95 % upozorní; s politikou služby objedná vyšší tarif z kreditu', 'runs' => 'každou hodinu', 'switchable' => true],
        ['key' => 'renewal.guard', 'command' => 'onhost:billing:renewal-guard', 'name' => 'Ochrana obnov', 'does' => 'týden před obnovou porovná kredit; chybí-li, dobije z uložené karty nebo upozorní', 'runs' => 'denně 07:35', 'switchable' => true],
        ['key' => 'operations.board', 'command' => 'onhost:provisioning:board', 'name' => 'Automatické odstavení uzlu', 'does' => 'uzel s přechodnými chybami odstaví; po zdravých sondách vrátí', 'runs' => 'každých 5 minut', 'switchable' => true],
        ['key' => 'nodes.check', 'command' => 'onhost:nodes:check', 'name' => 'Prerekvizity uzlů', 'does' => 'API, verze PHP, cron API, shellová sonda, herní panel; funkce se nabízejí jen kde fungují', 'runs' => 'denně 05:20', 'switchable' => true],
        ['key' => 'certificates.issue', 'command' => 'onhost:certificates:issue-pending', 'name' => 'Automatické certifikáty', 'does' => 'vystaví certifikát, jakmile DNS domény ukazuje na nás', 'runs' => 'každých 15 minut', 'switchable' => true],
        ['key' => 'certificates.watch', 'command' => 'onhost:certificates:watch', 'name' => 'Hlídání platnosti certifikátů', 'does' => 'přečte certifikát, kterým se web opravdu hlásí; vypršelý nebo cizí ohlásí provozu a pod deset dní si obnovu vyžádá sám', 'runs' => 'denně 04:40', 'switchable' => true],
        ['key' => 'backups.run', 'command' => 'onhost:backups:run', 'name' => 'Zálohy podle plánu', 'does' => 'spouští zálohy podle tarifu a uklízí staré generace', 'runs' => 'každých 15 minut', 'switchable' => true],
        // servers and managed databases were sold with backups nobody took (TASK-0019); starting them on existing services is the owner's decision
        ['key' => 'backups.compute', 'command' => null, 'name' => 'Zálohy serverů a databází podle plánu', 'does' => 'managed databázím s dny záloh v tarifu a VPS se zálohovacím doplňkem spouští zálohy na zálohovací úložiště Proxmoxu a maže ty s prošlou retencí; závěrečné, chráněné a zadržené zálohy nikdy. Kdo by byl dotčen: onhost:backups:compute-plan', 'runs' => 'každých 15 minut spolu se Zálohami podle plánu', 'switchable' => true, 'default_off' => true],
        // TASK-0024 (owner decision 18): backups as often and as long as the price list sells them; changes existing web/managed services, so off until staff switch it on
        ['key' => 'backups.as_sold', 'command' => null, 'name' => 'Zálohy přesně podle ceníku', 'does' => 'frekvence 1h/60m se čte jako hodinová a u webových a managed tarifů se nad počet generací drží jedna záloha denně po dobu backup_days; po vypnutí se další denní zálohy už nedrží a ty dosud držené dožijí svou retenci (nejvýš backup_days), hromadně se nic nesmaže. Kdo by byl dotčen: onhost:backups:frequency-plan', 'runs' => 'každých 15 minut spolu se Zálohami podle plánu', 'switchable' => true, 'default_off' => true],
        // TASK-0024 (owner decision 3): mailboxes keep the daily backups a mail plan sells; changes existing customers' mail (and a downgrade deletes backups), so off until staff switch it on
        ['key' => 'mail.backup_retention', 'command' => null, 'name' => 'Zálohy schránek podle tarifu', 'does' => 'nové schránky poštovních tarifů dostanou denní zálohy na backup_days dní a změna tarifu je přenese na schránky služby (méně kopií jen na pokyn provozu); stávající schránky jen přes onhost:mail:backup-retention --apply, cizí schránky nikdy', 'runs' => 'při založení schránky a při změně tarifu', 'switchable' => true, 'default_off' => true],
        ['key' => 'services.purge', 'command' => 'onhost:services:purge', 'name' => 'Odstranění zrušených služeb', 'does' => 'po uplynutí lhůty na obnovu službu odstraní; předtím znovu ověří identitu a archiv', 'runs' => 'denně 03:40', 'switchable' => true],
        ['key' => 'access.expire', 'command' => 'onhost:access:expire', 'name' => 'Ukončení dočasných přístupů', 'does' => 'po datu konce odebere členství nebo projektovou roli a s nimi účty spolupracovníka a SSH klíče na panelech; samotné oprávnění přestává platit ihned i bez tohoto pravidla', 'runs' => 'každých 5 minut', 'switchable' => true],
        ['key' => 'ssh_keys.settle', 'command' => 'onhost:ssh-keys:settle', 'name' => 'Dokončení odvolání SSH klíčů', 'does' => 'zopakuje odvolání klíčů, která panel ještě nepotvrdil, a nahlásí ta, která opakovaně selhávají; dokud není potvrzeno, klíč může fungovat', 'runs' => 'každých 5 minut', 'switchable' => true],
        ['key' => 'access.review', 'command' => 'onhost:access:review', 'name' => 'Revize delegovaných přístupů', 'does' => 'najde účty spolupracovníků, které patří lidem mimo organizaci, a upozorní zákazníka; nic nemaže', 'runs' => 'v pondělí 04:50', 'switchable' => true],
        ['key' => 'digest.weekly', 'command' => 'onhost:digest:weekly', 'name' => 'Týdenní přehled zákazníkům', 'does' => 'obnovy, kredit, zálohy, monitoring; frekvenci určuje zákazník', 'runs' => 'pondělí 07:00', 'switchable' => true],
        ['key' => 'digest.staff', 'command' => 'onhost:digest:staff-daily', 'name' => 'Denní provozní přehled', 'does' => 'zaseknuté operace, selhání, pohledávky, kapacita', 'runs' => 'denně 07:15', 'switchable' => true],
        ['key' => 'commerce.prune', 'command' => 'onhost:commerce:prune', 'name' => 'Úklid obchodu', 'does' => 'maže staré nabídky a opuštěné košíky', 'runs' => 'denně 04:20', 'switchable' => true],
        ['key' => 'order.risk', 'command' => null, 'name' => 'Kontrola objednávek', 'does' => 'skóre rizika při objednávce; nad prahem čeká zaplacená objednávka na rozhodnutí', 'runs' => 'při každé objednávce', 'switchable' => true],
        ['key' => 'partners.auto_approve', 'command' => null, 'name' => 'Automatické schválení smluvních změn', 'does' => 'zámek sazby do 6 měsíců a výplatní podmínky partnera s čistým rokem schválí bez financí; model a white-label vždy čekají', 'runs' => 'při každé žádosti', 'switchable' => true],
        ['key' => 'capacity.auto_order', 'command' => 'onhost:provisioning:capacity-forecast', 'name' => 'Automatický nákup uzlů', 'does' => 'schválí a objedná uzel u dodavatele, když fond dochází a instance umí objednávat (jinak jen návrh pro provoz)', 'runs' => 'denně 03:45', 'switchable' => true, 'default_off' => true],
        ['key' => 'oncall.escalate', 'command' => 'onhost:oncall:escalate', 'name' => 'Eskalace on-call', 'does' => 'alert, který nikdo nepotvrdil do X minut, znovu zavolá pager s vyšší závažností (nejvýš N×)', 'runs' => 'každou minutu', 'switchable' => true],
        ['key' => 'platform.backup', 'command' => 'onhost:platform:backup', 'name' => 'Záloha platformy', 'does' => 'denně zálohuje databázi control plane a soukromé soubory (doklady, důkazy, exporty) na zálohovací disk a ověřuje obnovitelnost', 'runs' => 'denně 02:15, ověření 03:15', 'switchable' => true],
        ['key' => 'oncall.remind', 'command' => 'onhost:oncall:remind', 'name' => 'Připomenutí on-call směny', 'does' => 'hodinu před začátkem směny pošle jejímu držiteli upozornění a e-mail', 'runs' => 'každých 5 minut', 'switchable' => true],
        ['key' => 'game.templates.verify', 'command' => 'onhost:game:templates:verify', 'name' => 'Kontrola herních šablon', 'does' => 'porovná namapované šablony s eggy panelu; chybějící odebere z nabídky a ohlásí provozu, povinné proměnné načte znovu', 'runs' => 'denně 05:10', 'switchable' => true],
        ['key' => 'files.scan', 'command' => 'onhost:files:scan', 'name' => 'Antivirová kontrola souborů', 'does' => 'znovu prověří soubory, které ClamAV při nahrání nestihl; infikované smaže a nahlásí bezpečnosti', 'runs' => 'každých 10 minut', 'switchable' => false],
        ['key' => 'files.prune', 'command' => 'onhost:files:prune', 'name' => 'Retence souborů', 'does' => 'maže důkazy z marketplace po retenční lhůtě a exporty dat po expiraci', 'runs' => 'denně 04:25', 'switchable' => true],
        ['key' => 'nodes.usage', 'command' => 'onhost:nodes:usage', 'name' => 'Obsazenost disků uzlů', 'does' => 'zeptá se každého webového uzlu, jak plný má disk, aby umísťování mělo s čím počítat', 'runs' => 'každých 15 minut', 'switchable' => true],
        ['key' => 'sites.integrity', 'command' => 'onhost:sites:integrity', 'name' => 'Kontrola napadení webů', 'does' => 'hledá kód ve složkách pro nahrané soubory a otisky webshellů; jen hlásí, nikdy nezasahuje', 'runs' => 'denně 04:40', 'switchable' => true],
        ['key' => 'mail.blocklist', 'command' => 'onhost:mail:blocklist', 'name' => 'Blocklisty odesílacích adres', 'does' => 'ptá se blocklistů, jestli adresa uzlu není na seznamu; jeden spammer na sdíleném uzlu shodí poštu všem ostatním', 'runs' => 'denně 05:25', 'switchable' => true],
        ['key' => 'dns.check', 'command' => 'onhost:dns:check', 'name' => 'Kontrola DNS zákaznických domén', 'does' => 'porovná, co veřejné DNS odpovídá, s tím, co jsme pro doménu publikovali; vlastní zónu opraví, zbytek řekne zákazníkovi', 'runs' => 'denně 05:10', 'switchable' => true],
        ['key' => 'game.migration', 'command' => null, 'name' => 'Stěhování herních serverů', 'does' => 'záloha, nový server na jiném uzlu, přenos archivu, přepnutí adresy, úklid — bez zásahu do hry', 'runs' => 'na pokyn obsluhy', 'switchable' => false],
    ];

    public function __construct(private readonly CacheRepository $cache, private readonly SettingsStore $settings) {}

    /** @param array<string,int|string|bool|null> $stats */
    public function record(string $key, array $stats = [], ?string $error = null): void
    {
        $this->cache->put("onhost:automation:{$key}", ['at' => now()->toIso8601String(), 'stats' => $stats, 'error' => $error], self::TTL_SECONDS);
    }

    /** @return array{at:?string, stats:array<string,mixed>, error:?string}|null */
    public function last(string $key): ?array
    {
        $row = $this->cache->get("onhost:automation:{$key}");

        return is_array($row) ? $row : null;
    }

    /** @return array{key:string,command:?string,name:string,does:string,runs:string,switchable:bool} */
    public function rule(string $key): array
    {
        foreach (self::RULES as $rule) {
            if ($rule['key'] === $key) {
                return $rule;
            }
        }
        throw new DomainError('automation_rule_unknown', 'No such automation rule: '.$key.'.', 404, ['field' => 'key']);
    }

    /** Whether the rule runs; a rule without a switch is always on. */
    public function enabled(string $key): bool
    {
        foreach (self::RULES as $rule) {
            if ($rule['key'] === $key && ! empty($rule['default_off'])) {
                return in_array($key, $this->switchedOn(), true) && ! in_array($key, $this->disabled(), true);
            }
        }

        return ! in_array($key, $this->disabled(), true);
    }

    /** @return list<string> default-off rules staff switched on */
    public function switchedOn(): array
    {
        return array_values(array_filter(array_map('strval', (array) $this->settings->get(self::SETTING_ON, [])), fn (string $k) => $k !== ''));
    }

    /** @return list<string> */
    public function disabled(): array
    {
        return array_values(array_filter(array_map('strval', (array) $this->settings->get(self::SETTING, [])), fn (string $k) => $k !== ''));
    }

    /**
     * Staff switch (§5g-7); the change lands in the settings table (audited through the command bus that calls this).
     *
     * @return array<string,mixed> the rule row as the console shows it
     */
    public function setEnabled(string $key, bool $enabled, ?string $by = null): array
    {
        $rule = $this->rule($key);
        if (! $rule['switchable'] && ! $enabled) {
            throw new DomainError('automation_rule_required', $rule['name'].' cannot be switched off; freeze provisioning during an incident instead.', 422, ['field' => 'key']);
        }
        $disabled = array_values(array_filter($this->disabled(), fn (string $k) => $k !== $key));
        if (! $enabled) {
            $disabled[] = $key;
        }
        $this->settings->set(self::SETTING, $disabled, $by);
        if (! empty($rule['default_off'])) { // a default-off rule remembers that staff switched it on
            $on = array_values(array_filter($this->switchedOn(), fn (string $k) => $k !== $key));
            if ($enabled) {
                $on[] = $key;
            }
            $this->settings->set(self::SETTING_ON, $on, $by);
        }

        return $this->present($rule);
    }

    /**
     * True when staff switched the rule off — the caller returns without doing anything; the skip is recorded so the
     * console shows "switched off" with a fresh timestamp rather than a rule that went quiet.
     */
    public function off(string $key): bool
    {
        if ($this->enabled($key)) {
            return false;
        }
        $this->record($key, ['skipped' => 1]);

        return true;
    }

    /** Every rule with its last run and its switch. @return list<array<string,mixed>> */
    public function overview(): array
    {
        return array_map(fn (array $rule) => $this->present($rule), self::RULES);
    }

    /**
     * Liveness of the two machines every rule depends on (§5g-6): the scheduler (last provisioning tick) and the
     * queue worker (its heartbeat job). Either older than STALE_MINUTES is reported as down.
     *
     * @return array{scheduler:array{at:?string,alive:bool}, worker:array{at:?string,alive:bool,driver:string}}
     */
    public function liveness(): array
    {
        $tick = $this->last('provisioning.tick');
        $tickAt = isset($tick['at']) ? CarbonImmutable::parse((string) $tick['at']) : null;
        $workerAt = QueueHeartbeat::lastSeenAt($this->cache);
        $driver = (string) config('queue.default');
        $fresh = fn (?CarbonImmutable $at) => $at !== null && $at->diffInMinutes(now(), true) < self::STALE_MINUTES;

        return [
            'scheduler' => ['at' => $tickAt?->toIso8601String(), 'alive' => $fresh($tickAt)],
            'worker' => ['at' => $workerAt?->toIso8601String(), 'alive' => $driver === 'sync' || $fresh($workerAt), 'driver' => $driver],
        ];
    }

    /**
     * Operations due for longer than the age limit, per queue (audit §5h-5): a backlog above the threshold means the
     * worker cannot keep up (or is gone) — the health rule raises `platform.queue.backlog`, the doctor warns, the
     * metrics endpoint exposes the gauge so a second worker can be started on it.
     *
     * @return array{stale:int, by_queue:array<string,int>, jobs:?int, threshold:int, age_minutes:int, alert:bool}
     */
    public function backlog(): array
    {
        $ageMinutes = max(1, (int) config('onhost.provisioning.backlog.age_minutes', 5));
        $threshold = max(1, (int) config('onhost.provisioning.backlog.threshold', 25));
        $byQueue = Operation::query()->whereIn('state', [Operation::PENDING, Operation::WAITING])->where('next_run_at', '<=', now()->subMinutes($ageMinutes))
            ->selectRaw('queue, count(*) as n')->groupBy('queue')->pluck('n', 'queue')->map(fn ($n) => (int) $n)->all();
        $stale = (int) array_sum($byQueue);
        $jobs = (string) config('queue.default') === 'database' && Schema::hasTable('jobs') ? (int) DB::table('jobs')->count() : null;

        return ['stale' => $stale, 'by_queue' => $byQueue, 'jobs' => $jobs, 'threshold' => $threshold, 'age_minutes' => $ageMinutes, 'alert' => $stale >= $threshold];
    }

    /** @param  array{key:string,command:?string,name:string,does:string,runs:string,switchable:bool}  $rule
     * @return array<string,mixed> */
    private function present(array $rule): array
    {
        return $rule + ['enabled' => $this->enabled($rule['key']), 'last' => $this->last($rule['key'])];
    }
}
