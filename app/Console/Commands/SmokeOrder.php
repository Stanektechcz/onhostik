<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Services\Models\GameServer;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\InfrastructureProvider;
use Onhost\Providers\Contracts\ResourceRef;
use Throwable;

/**
 * Operator smoke test of the real order path on a live installation (audit §5z): for a test organization it quotes,
 * orders from credit and waits for the provisioning on the live panels — web hosting pinned to the named ISPConfig and
 * aaPanel instances, a game server of the named template and version — then verifies the result and, with --cleanup,
 * terminates what it created. Every step goes through the same quote, checkout, fulfilment and workflows as a
 * customer's order; the panels are only touched through their APIs. Credit for the test comes as audited promo credit
 * (--fund) so no real payment is needed.
 */
final class SmokeOrder extends Command
{
    protected $signature = 'onhost:smoke:order
        {organization : test organization id, slug or owner e-mail}
        {--web=* : web hosting on these instances, e.g. --web=ispconfig-s2 --web=aapanel-cz1}
        {--web-plan=start : web hosting plan}
        {--game= : game template and version, e.g. minecraft-vanilla@1.21.8}
        {--fund : top up promo credit for the test when the balance is short}
        {--cleanup : terminate the created services after the verification}
        {--timeout=900 : seconds to wait for each provisioning}
        {--work : process the queue of the operation in this command (an installation without running queue workers)}
        {--create-org : when the e-mail belongs to an account without a customer profile, create a test organization it owns}';

    protected $description = 'Place real orders through the customer path and verify provisioning on the live panels';

    public function handle(QuoteService $quotes, CheckoutService $checkout, WalletService $wallets, ServiceService $services): int
    {
        $organization = $this->organization((string) $this->argument('organization'));
        if ($organization === null) {
            $needle = strtolower((string) $this->argument('organization'));
            $user = str_contains($needle, '@') ? User::query()->where('email', $needle)->first() : null;
            if ($user !== null && $this->option('create-org')) {
                $organization = app(OrganizationService::class)->create($user, ['name' => 'ONhost · test objednávek', 'type' => 'company', 'ico' => null, 'billing_email' => $user->email, 'street' => 'Testovací 1', 'city' => 'Praha', 'postal_code' => '11000', 'country' => 'CZ'], CommandContext::system('cli:smoke:order'));
                $this->info("Založena testovací organizace {$organization->name} ({$organization->id}) pro {$user->email}.");
            } else {
                $this->error('Organization not found.');
                $this->line($user !== null
                    ? "  Účet {$user->email} nemá zákaznický profil. Spusťte znovu s --create-org, nebo zadejte e-mail zákaznického účtu."
                    : '  Zadejte id nebo slug organizace, nebo e-mail vlastníka (s --create-org pro účet bez profilu).');

                return self::FAILURE;
            }
        }
        $keys = array_merge((array) $this->option('web'));
        $known = ProviderInstance::query()->whereIn('provider', ['ispconfig', 'aapanel', 'pterodactyl'])->get(['key', 'provider', 'state']);
        $unknown = array_values(array_diff($keys, $known->pluck('key')->all()));
        if ($unknown !== []) {
            $this->error('Neznámá instance: '.implode(', ', $unknown));
            $this->line('  Dostupné instance: '.$known->map(fn ($i) => "{$i->key} ({$i->provider}, {$i->state})")->implode(' · '));

            return self::FAILURE;
        }
        $cases = [];
        foreach ((array) $this->option('web') as $instance) {
            $cases[] = ['label' => "webhosting na {$instance}", 'item' => ['product_key' => 'web-hosting', 'plan_key' => (string) $this->option('web-plan'), 'qty' => 1, 'config' => ['placement_instance' => (string) $instance, 'label' => 'smoke '.$instance]]];
        }
        if ((string) $this->option('game') !== '') {
            [$egg, $version] = array_pad(explode('@', (string) $this->option('game'), 2), 2, '');
            $config = ['egg' => $egg, 'label' => 'smoke '.$egg];
            if ($version !== '') {
                $config['version'] = $version;
            }
            $cases[] = ['label' => "herní server {$egg}".($version !== '' ? " {$version}" : ''), 'item' => ['product_key' => 'game', 'plan_key' => (string) config('onhost.game.configurator.plan', 'game-custom'), 'qty' => 1, 'config' => $config]];
        }
        if ($cases === []) {
            $this->error('Nothing to test: pass --web=<instance> and/or --game=<egg@version>.');

            return self::FAILURE;
        }
        $context = CommandContext::system('cli:smoke:order');
        $failed = 0;
        foreach ($cases as $case) {
            $this->newLine();
            $this->info('▶ '.$case['label']);
            try {
                $failed += $this->run1($case, $organization, $quotes, $checkout, $wallets, $services, $context) ? 0 : 1;
            } catch (DomainError $e) {
                $failed++;
                $this->error("  ✗ {$e->error}: {$e->getMessage()}");
            } catch (Throwable $e) {
                $failed++;
                $this->error('  ✗ '.class_basename($e).': '.$e->getMessage());
            }
        }
        $this->newLine();
        $failed === 0 ? $this->info('All smoke orders passed.') : $this->error("{$failed} smoke order(s) failed.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @param array{label:string, item:array<string,mixed>} $case */
    private function run1(array $case, Organization $organization, QuoteService $quotes, CheckoutService $checkout, WalletService $wallets, ServiceService $services, CommandContext $context): bool
    {
        $currency = (string) ($organization->currency ?? 'CZK');
        $quote = $quotes->quote([$case['item']], $currency, ['country' => $organization->country ?? 'CZ', 'customer_class' => $organization->customer_class ?? 'b2c', 'vat_status' => $organization->vat_status ?? 'unknown', 'ip_country' => null], 1, null, $organization);
        $total = $quote->total_minor / 100;
        $this->line(sprintf('  1. nabídka %s · %s %s s DPH', $quote->id, number_format($total, 2, ',', ' '), $currency));
        $spendable = $wallets->spendable($organization, $currency);
        if ($spendable->minor < $quote->total_minor) {
            if (! $this->option('fund')) {
                throw new DomainError('insufficient_funds', 'Kredit organizace nestačí; spusťte s --fund nebo kredit připište.');
            }
            $missing = $quote->total_minor - $spendable->minor + 100;
            $wallets->topup($organization, Money::minor($missing, $currency), 'promo', 'smoke-fund:'.$quote->id, $context->withScope($organization->id), note: 'Smoke test objednávky (operátor)', promo: true);
            $this->line('     připsán testovací promo kredit '.number_format($missing / 100, 2, ',', ' ')." {$currency}");
        }
        $consents = [];
        foreach ($checkout->requiredDocuments($quote, $organization) as $key) {
            $consents[$key] = ['person' => 'ONhost smoke test (operátor)', 'language' => 'cs'];
        }
        $result = $checkout->placeOrder($quote, $organization, null, $consents, ['mode' => 'wallet'], 'smoke:'.$quote->id, $context, 'cli');
        $order = $result['order']->fresh();
        $this->line("  2. objednávka {$order->number} · {$order->state} · z kreditu");

        $deadline = time() + max(60, (int) $this->option('timeout'));
        $service = null;
        $last = '';
        while (time() < $deadline) {
            app(OutboxPublisher::class)->relayPending(); // the fulfilment listener creates the service; the workers pick the operation up
            $item = OrderItem::query()->where('order_id', $order->id)->first();
            $service = $item?->service_id ? Service::query()->find($item->service_id) : null;
            if ($service !== null) {
                $operation = Operation::query()->where('service_id', $service->id)->orderByDesc('created_at')->first();
                $now = $service->state.' · '.($operation ? $operation->state.' krok '.(int) $operation->step.'/'.(int) $operation->steps_total : 'bez operace');
                if ($now !== $last) {
                    $this->line('  3. služba '.$service->id.' · '.$now);
                    $last = $now;
                }
                $this->work($operation);
                if (in_array($service->state, [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED], true)) {
                    break;
                }
                if ($service->state === ServiceStateMachine::FAILED || ($operation !== null && $operation->state === Operation::FAILED)) {
                    $this->error('  ✗ provisioning selhal: '.json_encode($operation?->error, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

                    return false;
                }
            }
            sleep(5);
        }
        if ($service === null || ! in_array($service->state, [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED], true)) {
            $this->error('  ✗ služba nebyla do limitu aktivní (stav '.($service === null ? 'nevytvořena' : $service->state).')');

            return false;
        }
        $service->refresh();
        $spec = (array) $service->desired_spec;
        $instanceKey = (string) ProviderInstance::query()->whereKey($service->provider_instance_id)->value('key');
        $this->line('  4. ověření: instance '.($instanceKey ?: (string) $service->provider_instance_id).' · '.($spec['domain'] ?? $spec['hostname'] ?? $service->hostname ?? ''));
        if ($service->family === 'game') {
            $game = GameServer::query()->where('service_id', $service->id)->first();
            $env = (array) data_get($game?->startup, 'environment', []);
            $this->line('     server '.($game === null ? '—' : (string) $game->ptero_identifier).' · adresa '.data_get($game?->allocation, 'ip', '').':'.data_get($game?->allocation, 'port', '').' · egg '.($game === null ? '' : (string) $game->egg_key).' · verze '.($env['VANILLA_VERSION'] ?? $env['MINECRAFT_VERSION'] ?? 'latest'));
            $expected = (string) ($case['item']['config']['version'] ?? '');
            if ($expected !== '' && ($env['VANILLA_VERSION'] ?? $env['MINECRAFT_VERSION'] ?? '') !== $expected) {
                $this->error("  ✗ verze na serveru neodpovídá objednávce ({$expected})");

                return false;
            }
        } elseif (($pinned = (string) ($case['item']['config']['placement_instance'] ?? '')) !== '' && $instanceKey !== $pinned) {
            $this->error("  ✗ služba běží na {$instanceKey}, ne na {$pinned}");

            return false;
        }
        $binding = $service->primaryBinding();
        $adapter = $binding !== null && $instanceKey !== '' ? app(ProviderRegistry::class)->forKey($instanceKey) : null;
        if ($binding !== null && $adapter instanceof InfrastructureProvider) { // the panel itself, through its API: the resource exists and runs
            $actual = $adapter->getActualState(new ResourceRef((string) $binding->remote_type, (string) $binding->remote_id, null, (array) $binding->meta, $service->id));
            $this->line('     na panelu: '.($actual->exists ? 'existuje' : 'NEEXISTUJE').' · stav '.($actual->status ?? '—'));
            if (! $actual->exists) {
                $this->error('  ✗ panel zdroj nevidí');

                return false;
            }
        }
        $this->info('  ✓ zřízeno a ověřeno');
        if ($this->option('cleanup')) {
            $operation = $services->requestAction($service, 'terminate', $context->withScope($organization->id), 'smoke-cleanup:'.$service->id);
            $this->line("  5. úklid: zrušení služby, operace {$operation->id}");
            $until = time() + 300;
            while (time() < $until) {
                $this->work($operation->fresh());
                $current = Service::withTrashed()->find($service->id);
                if ($current === null || $current->state === ServiceStateMachine::TERMINATED) {
                    break;
                }
                sleep(5);
            }
            $this->line('     stav po úklidu: '.(($gone = Service::withTrashed()->find($service->id)) === null ? 'odstraněna' : $gone->state));
        }

        return true;
    }

    /** --work: run the jobs of the operation's queue here (the operation's own queue only, nothing else waiting on the installation). */
    private function work(?Operation $operation): void
    {
        if (! $this->option('work') || $operation === null || ! in_array($operation->state, [Operation::PENDING, Operation::RUNNING, Operation::WAITING], true)) {
            return;
        }
        Artisan::call('queue:work', ['--queue' => (string) ($operation->queue ?: 'default'), '--stop-when-empty' => true, '--tries' => 1, '--timeout' => 600]);
    }

    private function organization(string $needle): ?Organization
    {
        $organization = Organization::query()->whereKey($needle)->orWhere('slug', $needle)->first();
        if ($organization === null && str_contains($needle, '@')) {
            $user = User::query()->where('email', strtolower($needle))->first();
            $organization = $user !== null ? Organization::query()->where('owner_user_id', $user->id)->orderBy('created_at')->first() : null;
        }

        return $organization;
    }
}
