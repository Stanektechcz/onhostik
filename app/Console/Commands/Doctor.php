<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\Models\TldPolicy;
use Onhost\Domain\Domains\Models\RegistrarTldCost;
use Onhost\Domain\Domains\RegistrarClient;
use Onhost\Domain\Domains\RegistrarPricing;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Invoicing\Models\LegalEntity;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Provisioning\Models\IntegrationHealth;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\PlanPlacement;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Models\Region;
use Onhost\Domain\Provisioning\PlacementService;
use Onhost\Domain\Provisioning\ProviderInstanceService;
use Onhost\Domain\WalletLedger\AutoTopup;
use Onhost\Platform\Files\VirusScanner;

/**
 * Production readiness self-check (docs/runbooks/go-live-checklist.md). Every row is a fact the control plane can
 * verify about itself: environment, storage, secrets, outbound TLS, provider instances and their health, payments,
 * documents, identity policy, mail and observability. Blocking findings are FAIL in production and WARN elsewhere;
 * the exit code is 1 when anything fails, so the deploy pipeline can gate on it.
 */
final class Doctor extends Command
{
    protected $signature = 'onhost:doctor {--json : Machine-readable report}';

    protected $description = 'Production readiness self-check: environment, storage, secrets, TLS, providers, payments, documents, identity, mail, observability';

    /** @var list<array{area:string, check:string, status:string, detail:string}> */
    private array $rows = [];

    private bool $production = false;

    public function handle(ProviderInstanceService $instances, AutomationLedger $ledger): int
    {
        $this->rows = []; // the command instance is reused within one process (tests, tinker): every run reports its own rows
        $this->production = app()->environment('production');
        $this->environment();
        $this->storage();
        $this->automation($ledger);
        $this->secretsAndTls();
        $this->providers($instances);
        $this->payments();
        $this->documents();
        $this->identity();
        $this->mailAndObservability();

        $fails = count(array_filter($this->rows, fn ($r) => $r['status'] === 'FAIL'));
        $warns = count(array_filter($this->rows, fn ($r) => $r['status'] === 'WARN'));
        if ($this->option('json')) {
            $this->line((string) json_encode(['environment' => app()->environment(), 'fail' => $fails, 'warn' => $warns, 'checks' => $this->rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(['Area', 'Check', 'Status', 'Detail'], array_map(fn ($r) => [$r['area'], $r['check'], $r['status'], $r['detail']], $this->rows));
            $this->line(sprintf('%s · %d checks · %d FAIL · %d WARN', app()->environment(), count($this->rows), $fails, $warns));
        }

        return $fails > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function add(string $area, string $check, bool $ok, string $detail = '', bool $blocking = true): void
    {
        $this->rows[] = ['area' => $area, 'check' => $check, 'status' => $ok ? 'OK' : ($blocking && $this->production ? 'FAIL' : 'WARN'), 'detail' => $detail];
    }

    private function environment(): void
    {
        $this->add('app', 'APP_ENV is production', $this->production, (string) config('app.env'), false);
        $this->add('app', 'APP_DEBUG off', ! config('app.debug'), config('app.debug') ? 'debug pages would leak configuration' : '');
        $this->add('app', 'APP_KEY set', (string) config('app.key') !== '', '');
        $this->add('app', 'APP_URL uses https', str_starts_with((string) config('app.url'), 'https://'), (string) config('app.url'));
        $this->add('app', 'Sanctum stateful domains set', trim((string) env('SANCTUM_STATEFUL_DOMAINS', '')) !== '', 'the surfaces authenticate with the session cookie');
    }

    private function storage(): void
    {
        try {
            DB::select('select 1');
            $this->add('storage', 'database reachable', true, (string) config('database.default'));
        } catch (\Throwable $e) {
            $this->add('storage', 'database reachable', false, $e->getMessage());
        }
        $this->add('storage', 'database engine', $this->production ? config('database.default') === 'pgsql' : true, (string) config('database.default').' (production: pgsql)', false);
        $queue = (string) config('queue.default');
        $this->add('storage', 'queue driver', $queue !== 'sync', $queue.($queue === 'database' ? ' (works; redis recommended)' : ''), $queue === 'sync');
        $this->add('storage', 'cache store', in_array(config('cache.default'), ['redis', 'memcached', 'dynamodb', 'array', 'file', 'database'], true) && (! $this->production || config('cache.default') === 'redis'), (string) config('cache.default').' (production: redis)', false);
        $this->add('storage', 'session driver', ! $this->production || in_array(config('session.driver'), ['redis', 'database'], true), (string) config('session.driver'), false);
        $this->add('storage', 'storage/app writable', is_writable(storage_path('app')), storage_path('app'));
    }

    /** The two machines every rule depends on (audit §5g-6) and the switches staff flipped (§5g-7). */
    private function automation(AutomationLedger $ledger): void
    {
        $live = $ledger->liveness();
        $since = fn (?string $at) => $at !== null ? CarbonImmutable::parse($at)->diffForHumans() : null;
        $this->add('automation', 'scheduler running', $live['scheduler']['alive'], $since($live['scheduler']['at']) !== null ? 'last provisioning tick '.$since($live['scheduler']['at']) : 'no tick recorded — is `php artisan schedule:run` in cron every minute?');
        $this->add('automation', 'queue worker alive', $live['worker']['alive'], $live['worker']['driver'] === 'sync' ? 'sync driver (development only)' : ($since($live['worker']['at']) !== null ? 'heartbeat '.$since($live['worker']['at']) : 'no heartbeat — is `php artisan queue:work` running?'));
        $off = $ledger->disabled();
        $this->add('automation', 'no rule switched off', $off === [], $off === [] ? 'every rule on' : 'off: '.implode(', ', $off), false);
        $backlog = $ledger->backlog();
        $this->add('automation', 'operation backlog', ! $backlog['alert'], $backlog['stale'].' operation(s) due for more than '.$backlog['age_minutes'].' min (threshold '.$backlog['threshold'].($backlog['jobs'] !== null ? ', '.$backlog['jobs'].' queued job(s)' : '').')', false);
    }

    private function secretsAndTls(): void
    {
        $driver = (string) config('onhost.secrets.driver');
        $this->add('secrets', 'secrets driver', $driver !== 'env', $driver.($driver === 'env' ? ' — environment secrets are for development only' : ($driver === 'db' ? ' (encrypted with APP_KEY; OpenBao recommended for HA)' : '')));
        $locations = function_exists('openssl_get_cert_locations') ? openssl_get_cert_locations() : [];
        $bundle = ini_get('curl.cainfo') ?: ini_get('openssl.cafile') ?: (string) ($locations['default_cert_file'] ?? '');
        $this->add('tls', 'CA bundle for outbound TLS', $bundle !== '' && is_file($bundle), $bundle !== '' ? $bundle : 'set curl.cainfo / openssl.cafile or install ca-certificates');
    }

    private function providers(ProviderInstanceService $instances): void
    {
        $regions = Region::query()->count();
        $this->add('providers', 'regions defined', $regions > 0, "{$regions} region(s)");
        $all = ProviderInstance::query()->platform()->orderBy('provider')->orderBy('key')->get();
        $this->add('providers', 'provider instances registered', $all->isNotEmpty(), $all->count().' instance(s): '.$all->pluck('key')->implode(', '));
        $health = IntegrationHealth::query()->get()->keyBy('provider_instance_id');
        foreach ($all as $instance) {
            $status = $instances->credentialStatus($instance);
            $missing = (array) ($status['missing'] ?? []);
            $h = $health->get($instance->id);
            $up = $h !== null && (bool) $h->up;
            $detail = ($missing !== [] ? 'missing credentials: '.implode(', ', $missing) : 'credentials stored').' · '.($h === null ? 'never probed' : ($up ? 'up' : 'down: '.mb_substr((string) $h->last_error, 0, 80)).' ('.$h->checked_at?->diffForHumans().')');
            $this->add('providers', "{$instance->key} ({$instance->provider}, {$instance->state})", $missing === [] && $up, $detail, $instance->state === 'active');
        }
        $roles = Node::query()->where('state', 'active')->get()->groupBy('role')->map->count();
        $this->add('providers', 'active nodes', $roles->isNotEmpty(), $roles->isEmpty() ? 'none — run Discover or register nodes' : $roles->map(fn ($n, $r) => "{$r}: {$n}")->implode(', '));
        $this->add('providers', 'WEDOS test mode off', ! config('onhost.wapi.test_mode'), config('onhost.wapi.test_mode') ? 'WEDOS_TEST_MODE=true makes registry operations dry runs' : '');
        $registrars = app(RegistrarClient::class)->instances();
        $this->add('providers', 'registrar available', $registrars !== [], $registrars === [] ? 'no usable registrar instance (wedos / subreg): domains cannot be registered' : implode(', ', array_map(fn ($i) => $i->key.' ('.$i->provider.')', $registrars)));
        $tlds = TldPolicy::query()->where('registrable', true)->pluck('tld')->all();
        $costs = RegistrarTldCost::query()->whereIn('tld', $tlds)->get()->groupBy('tld');
        $noCost = array_values(array_filter($tlds, fn ($t) => ! $costs->has($t)));
        $stale = $costs->flatten(1)->filter(fn ($c) => $c->isStale((int) config('onhost.domains.registrar.cost_ttl_hours', 24)))->map(fn ($c) => $c->registrar_provider.'/.'.$c->tld)->values()->all();
        $this->add('providers', 'registrar cost prices known for every TLD', $noCost === [] && $stale === [], ($noCost === [] ? count($tlds).' TLD(s) priced at '.$costs->flatten(1)->pluck('registrar_provider')->unique()->implode(', ') : 'no cost price for: .'.implode(', .', $noCost)).($stale !== [] ? ' · stale: '.implode(', ', $stale) : ''), false);
        // selling below the cheapest registrar is a pricing mistake, not a business decision: surface it before go-live
        $below = [];
        foreach ($costs as $tld => $rows) {
            try {
                $sell = app(CatalogService::class)->domainPrice((string) $tld, 'CZK')->register()->minor;
            } catch (\Throwable) {
                continue; // no CZK selling price
            }
            $cheapest = $rows->map(fn ($c) => $c->register_minor === null ? null : RegistrarPricing::toCzkMinor((int) $c->register_minor, $c->currency))->filter(fn ($v) => $v !== null)->min();
            if ($cheapest !== null && $sell < $cheapest) {
                $below[] = '.'.$tld.' ('.number_format($sell / 100, 0, ',', ' ').' < '.number_format($cheapest / 100, 0, ',', ' ').' Kč)';
            }
        }
        $this->add('providers', 'no TLD sold below its cheapest cost price', $below === [], $below === [] ? 'every priced TLD keeps a positive margin' : 'below cost: '.implode(', ', $below).' — raise the selling price (Nastavení → Domény a TLD) or pin a cheaper registrar', false);
        // every sellable plan must have somewhere to run: a placement (panel + server) or a schedulable node of its executor family
        $placements = PlanPlacement::query()->where('state', 'active')->with('providerInstance')->get();
        $usableProviders = $all->filter(fn ($i) => $i->isUsable())->pluck('provider')->unique()->all();
        $missing = [];
        foreach (Product::query()->where('state', 'active')->whereNotNull('executor')->get() as $product) {
            $family = PlacementService::COMPATIBLE[(string) $product->executor] ?? [(string) $product->executor];
            $pinned = $placements->first(fn ($p) => $p->product_key === $product->key && $p->providerInstance?->isUsable());
            if ($pinned === null && array_intersect($family, $usableProviders) === []) {
                $missing[] = $product->key.' ('.$product->executor.')';
            }
        }
        $this->add('providers', 'every product can be provisioned', $missing === [], $missing === [] ? $placements->count().' placement(s), executors covered' : 'no usable instance or placement for: '.implode(', ', $missing));
    }

    private function payments(): void
    {
        $gateway = (string) config('onhost.payments.default');
        $merchant = (string) config("onhost.payments.{$gateway}.merchant", '');
        $this->add('payments', "card gateway ({$gateway}) configured", $merchant !== '', $merchant !== '' ? "merchant {$merchant}" : 'merchant id missing — card payments fail with payment_provider_error');
        $this->add('payments', 'card gateway live mode', ! (bool) config("onhost.payments.{$gateway}.test", true), (bool) config("onhost.payments.{$gateway}.test", true) ? 'test flag on' : 'live');
        $this->add('payments', 'stored cards for automatic top-ups', app(AutoTopup::class)->supported(), app(AutoTopup::class)->supported() ? 'a gateway charges stored methods (recurring)' : 'no gateway charges stored methods — automatic top-ups only notify (COMGATE_MERCHANT + COMGATE_RECURRING)', false);
        $bank = (array) config('onhost.payments.bank');
        $this->add('payments', 'bank account for transfers', ($bank['iban'] ?? '') !== '' && ($bank['account_number'] ?? '') !== '', 'ONHOST_BANK_IBAN / ONHOST_BANK_ACCOUNT printed on proformas and top-up instructions');
        $this->add('payments', 'bank statement import', ($bank['fio_token'] ?? '') !== '', ($bank['fio_token'] ?? '') !== '' ? 'Fio API token set — onhost:bank:sync runs every 5 minutes' : 'ONHOST_BANK_FIO_TOKEN missing — transfers must be recorded by hand in Nastavení → Bankovní platby');
    }

    private function documents(): void
    {
        $entity = LegalEntity::query()->find((string) config('onhost.billing.legal_entity', 'onhost-cz'));
        $this->add('documents', 'legal entity exists', $entity !== null, (string) config('onhost.billing.legal_entity', 'onhost-cz'));
        if ($entity !== null) {
            $placeholder = str_starts_with((string) $entity->iban, 'CZ0000') || (string) $entity->iban === '';
            $this->add('documents', 'legal entity bank details real', ! $placeholder, $placeholder ? 'seeded placeholder IBAN — run LegalEntitySeeder with production values' : (string) $entity->iban);
            $this->add('documents', 'legal entity identification', (string) $entity->ico !== '' && (string) $entity->name !== '', "{$entity->name} · IČO {$entity->ico}");
        }
    }

    private function identity(): void
    {
        $this->add('identity', 'staff MFA required', (bool) config('onhost.security.staff_mfa_required'), config('onhost.security.staff_mfa_required') ? '' : 'ONHOST_STAFF_MFA_REQUIRED=false');
        $demo = User::query()->whereIn('email', ['demo@onhost.cz', 'agentura@onhost.cz', 'admin@onhost.cz', 'noc@onhost.cz', 'finance@onhost.cz', 'support@onhost.cz'])->count();
        $this->add('identity', 'no development accounts', $demo === 0, $demo > 0 ? "{$demo} DevAccountSeeder account(s) present" : '');
        $staff = User::query()->where('is_staff', true)->count();
        $this->add('identity', 'staff users exist', $staff > 0, "{$staff} staff user(s)");
    }

    private function mailAndObservability(): void
    {
        $mailer = (string) config('mail.default');
        $this->add('mail', 'transactional mailer', ! in_array($mailer, ['log', 'array'], true), $mailer);
        $this->add('mail', 'sender address set', (string) config('mail.from.address', '') !== '' && ! str_contains((string) config('mail.from.address'), 'example.com'), (string) config('mail.from.address'));
        $this->add('observability', 'metrics token or allow-list', (string) config('onhost.metrics.token') !== '' || (array) config('onhost.metrics.allow_ips') !== [], (string) config('onhost.metrics.token') !== '' ? 'bearer token set' : 'allow-list only', false);
        $scanner = app(VirusScanner::class); // §5t-6: uploads are scanned by a clamd with fresh signatures
        $clam = $scanner->version();
        $fresh = $clam !== null && $clam['signatures_at'] !== null && strtotime($clam['signatures_at']) > time() - 2 * 86400;
        $this->add('files', 'virus scanner (clamd)', $fresh, ! $scanner->enabled() ? 'ONHOST_CLAMAV_HOST not set — uploads are not scanned' : ($clam === null ? 'clamd not reachable' : $clam['engine'].' · db '.$clam['database'].' · signatures '.($clam['signatures_at'] ?? '?').($fresh ? '' : ' (older than 2 days — is freshclam running?)')));
        $this->add('observability', 'console relay key', (string) config('onhost.console.relay_key') !== '', 'ONHOST_CONSOLE_RELAY_KEY');
        // game panels (audit §5f): every registered panel needs the client key for the server tools and at least one mapped template to sell
        foreach (ProviderInstance::query()->platform()->where('provider', 'pterodactyl')->whereIn('state', ['active', 'draining', 'maintenance'])->get() as $panel) {
            $prereqs = (array) data_get($panel->capabilities, 'prereqs', []);
            $clientApi = (string) ($prereqs['client_api'] ?? 'unknown');
            $this->add('game', "{$panel->key}: client API key", $clientApi === 'ok', $clientApi === 'unknown' ? 'not checked yet — run onhost:nodes:check or the prerequisites check' : ($clientApi === 'ok' ? 'accepted' : "{$clientApi} — store client_key with onhost:integrations:secret {$panel->key} client_key --check"), $panel->state === 'active');
            $eggs = (array) $panel->option('eggs', []);
            $bad = array_keys(array_filter((array) ($prereqs['game']['eggs'] ?? []), fn ($state) => $state !== 'ok'));
            $this->add('game', "{$panel->key}: templates mapped", $eggs !== [] && $bad === [], $eggs === [] ? 'no template mapped (staff console → Šablony her) — game orders would fail' : (count($eggs).' mapped'.($bad !== [] ? '; not on the panel: '.implode(', ', $bad) : '')), $panel->state === 'active');
            $daemonsDown = array_column(array_filter((array) ($prereqs['game']['nodes'] ?? []), fn ($n) => ($n['daemon'] ?? '') === 'down'), 'name');
            $this->add('game', "{$panel->key}: node daemons", $daemonsDown === [], $daemonsDown === [] ? count((array) ($prereqs['game']['nodes'] ?? [])).' node(s) checked' : 'not answering: '.implode(', ', $daemonsDown), false);
        }
        $risk = (array) config('onhost.orders.risk');
        $this->add('orders', 'intake pre-check', (bool) ($risk['enabled'] ?? false), ($risk['enabled'] ?? false) ? 'hold at score '.($risk['hold_score'] ?? 60).' · '.count((array) ($risk['disposable_domains'] ?? [])).' disposable domains' : 'ONHOST_ORDER_RISK=false — suspicious paid orders provision immediately', false);
        $this->add('notifications', 'staff digest recipients', trim((string) config('onhost.notifications.staff_digest_to', '')) !== '', 'ONHOST_STAFF_DIGEST_TO', false);
        $this->add('observability', 'log channel', ! in_array(config('logging.default'), ['single'], true) || ! $this->production, (string) config('logging.default').' (production: ship to Loki/stack)', false);
    }
}
