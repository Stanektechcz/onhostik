<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Providers\Pterodactyl\PanelIdentity;
use Onhost\Providers\Pterodactyl\PterodactylGameProvider;
use Throwable;

/**
 * `onhost:game:panel-identity --dry-run` — the triage list of TASK-0033 (permission program P0-02, IF-6 / D12). A game
 * server's panel user used to be found by the customer-typed billing e-mail, so a service may sit under a panel user that
 * is not its organization's: another organization's (a hijack, PA-01) or a user the platform never made (unmarked). Such
 * services are now refused the panel password, the panel account view and new collaborators; this command lists them for
 * an operator, one row per game server, and changes nothing. There is deliberately no `--apply`: a mismatch is re-homed
 * by hand after talking to both customers, never automatically (program §8 ruling 34, owner rule: no mass change).
 */
final class GamePanelIdentity extends Command
{
    protected $signature = 'onhost:game:panel-identity {--dry-run : list only (the default and the only mode)} {--apply : refused — mismatches are re-homed by hand} {--instance= : only this game panel instance key}';

    protected $description = 'List game services whose Pterodactyl panel user is not their organization\'s (read-only triage for TASK-0033)';

    public function handle(ProviderRegistry $providers): int
    {
        if ((bool) $this->option('apply')) {
            $this->error('There is no --apply: a panel user that is not the organization\'s is re-homed by an operator after triage, never in bulk. Nothing changed.');

            return self::FAILURE;
        }
        $instances = ProviderInstance::query()->where('provider', PterodactylGameProvider::providerKey())
            ->when($this->option('instance'), fn ($q, $key) => $q->where('key', (string) $key))->get()->keyBy('id');
        $rows = [];
        $counts = [];
        $bindings = ProviderBinding::query()->where('remote_type', 'server')->whereIn('provider_instance_id', $instances->keys()->all())->orderBy('created_at')->get();
        foreach ($bindings as $binding) {
            $service = Service::query()->find($binding->service_id);
            if ($service === null || $service->state === ServiceStateMachine::TERMINATED) {
                continue;
            }
            [$verdict, $userId, $externalId] = $this->judge($providers, $instances[$binding->provider_instance_id], $binding, $service);
            $counts[$verdict] = ($counts[$verdict] ?? 0) + 1;
            $rows[] = [$service->id, $service->organization_id, $service->state, $binding->remote_id, $userId, $verdict, $externalId ?? '—'];
        }
        $this->table(['služba', 'organizace', 'stav', 'server', 'uživatel panelu', 'výsledek', 'external_id v panelu'], $rows);
        $mismatched = count($rows) - ($counts[PanelIdentity::OWNED] ?? 0);
        $this->line('Herních serverů: '.count($rows).', neodpovídá: '.$mismatched.' ('.implode(', ', array_map(fn ($k, $v) => "{$k} {$v}", array_keys($counts), $counts)).')');
        $this->line('Nic nebylo změněno. Služby, které neodpovídají, nedostanou heslo do panelu ani nové spolupracovníky, dokud je operátor nepřevede ručně.');

        return self::SUCCESS;
    }

    /** @return array{0: string, 1: string, 2: ?string} verdict, panel user id, the panel's external id */
    private function judge(ProviderRegistry $providers, ProviderInstance $instance, ProviderBinding $binding, Service $service): array
    {
        try {
            $adapter = $providers->forInstance($instance);
            if (! $adapter instanceof PterodactylGameProvider) {
                return ['unsupported', '—', null];
            }
            $identity = $adapter->panelIdentity($binding->ref(), (string) $service->organization_id);

            return [$identity->verdict, $identity->userId, $identity->externalId];
        } catch (Throwable $e) { // one unreachable panel or server does not hide the rest of the list
            return ['unreadable: '.mb_substr($e->getMessage(), 0, 80), '—', null];
        }
    }
}
