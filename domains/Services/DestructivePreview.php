<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Web\StagingService;
use Onhost\Platform\Errors\DomainError;
use Throwable;

/**
 * What exactly a destructive action will do, and whether it can be taken back (Brain card H414, audit §5ai).
 *
 * "Opravdu?" is not a confirmation. The customer is told the service by name, the things that will actually go, what
 * else hangs on them, and — since a destructive action now keeps a copy of what it replaces — how far back they could
 * come. The preview also carries a **fingerprint of the target**: if what would be destroyed is not the same when the
 * button is finally pressed (a database was added, another backup became the newest, a staging site appeared), the
 * platform refuses the stale confirmation instead of destroying something the person never saw.
 *
 * The preview reads; it changes nothing and needs no step-up. It is the server's own answer, so a surface cannot
 * describe an action more gently than it is.
 */
final class DestructivePreview
{
    /** Actions that destroy or overwrite customer data. A confirmation of one of these can go stale. */
    public const ACTIONS = [
        'terminate', 'purge', 'restore', 'archive.restore', 'rollback_snapshot', 'reinstall',
        'database.delete', 'backup.delete', 'gbackup.delete', 'snapshot.delete', 'staging.delete', 'staging.push', 'site.delete',
    ];

    public function __construct(private readonly ServiceFeatures $features, private readonly DeletionPolicy $policy) {}

    public static function destructive(string $action): bool
    {
        return in_array($action, self::ACTIONS, true);
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array{action:string, service:array<string,mixed>, what:list<string>, depends:list<string>, recovery:array<string,mixed>, fingerprint:string}
     */
    public function of(Service $service, string $action, array $params = []): array
    {
        if (! self::destructive($action)) {
            throw new DomainError('action_not_destructive', 'Tato akce nic nemaže ani nepřepisuje; náhled není potřeba.', 422, ['action' => $action]);
        }
        $target = $this->target($service, $action, $params);

        return [
            'action' => $action,
            'service' => ['id' => $service->id, 'name' => $service->label ?: ($service->hostname ?: $service->name), 'hostname' => $service->hostname, 'product_key' => $service->product_key],
            'what' => $this->what($service, $action, $target),
            'depends' => $this->depends($service, $action),
            'recovery' => $this->recovery($service, $action),
            'fingerprint' => self::fingerprint($service, $action, $target),
        ];
    }

    /**
     * The confirmation the customer pressed must still describe what would happen now.
     *
     * @param  array<string,mixed>  $params
     */
    public function assertFresh(Service $service, string $action, array $params, string $confirm): void
    {
        if (! self::destructive($action)) {
            return; // nothing of this kind can go stale
        }
        $now = self::fingerprint($service, $action, $this->target($service, $action, $params));
        if (! hash_equals($now, $confirm)) {
            throw new DomainError('target_changed', 'Od zobrazení náhledu se změnilo, čeho se akce týká. Otevřete náhled znovu a potvrďte ho.', 409, ['action' => $action, 'fingerprint' => $now]);
        }
    }

    /** A stable digest of everything the action would touch. @param array<string,mixed> $target */
    public static function fingerprint(Service $service, string $action, array $target): string
    {
        ksort($target);

        return hash('sha256', $service->id.'|'.$action.'|'.json_encode($target, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * What the action is aimed at, read from the platform's own records and the panel — the thing that must not change
     * between the preview and the press.
     *
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    private function target(Service $service, string $action, array $params): array
    {
        $out = ['state' => $service->state];
        if (in_array($action, ['restore', 'archive.restore', 'backup.delete', 'gbackup.delete'], true)) {
            $backup = Backup::query()->where('service_id', $service->id)->find((string) ($params['backup_id'] ?? ''));
            $out['backup'] = $backup === null ? null : ['id' => $backup->id, 'kind' => $backup->kind, 'at' => $backup->finished_at?->toIso8601String(), 'bytes' => $backup->size_bytes];
        }
        if (in_array($action, ['rollback_snapshot', 'snapshot.delete'], true)) {
            $out['snapshot'] = (string) ($params['name'] ?? '');
        }
        if ($action === 'site.delete') {
            $site = Service::query()->where('organization_id', $service->organization_id)->find((string) ($params['site_id'] ?? ''));
            $out['site'] = $site === null ? null : (string) ($site->hostname ?: $site->name);
        }
        if ($action === 'database.delete') {
            $out['database'] = (string) ($params['remote_id'] ?? '');
        }
        if (in_array($action, ['terminate', 'purge', 'reinstall', 'restore', 'rollback_snapshot', 'staging.push'], true)) {
            // everything of the customer's that lives on the service: a database added since the preview changes what is lost
            $out['contents'] = $this->contents($service);
        }

        return $out;
    }

    /** @return array<string,mixed> */
    private function contents(Service $service): array
    {
        $out = [];
        foreach (['databases' => 'databases', 'mailboxes' => 'mailboxes'] as $key => $kind) {
            try {
                $rows = $this->features->resources($service, $kind);
                $out[$key] = array_values(array_map(fn ($row) => (string) (is_array($row) ? ($row['name'] ?? $row['remote_id'] ?? '') : $row), $rows));
                sort($out[$key]);
            } catch (Throwable) {
                // a panel that does not offer the listing (or is not answering) simply contributes nothing to the target
            }
        }

        return $out;
    }

    /** @param array<string,mixed> $target @return list<string> */
    private function what(Service $service, string $action, array $target): array
    {
        $out = [];
        $databases = (array) data_get($target, 'contents.databases', []);
        $mailboxes = (array) data_get($target, 'contents.mailboxes', []);
        $backup = (array) ($target['backup'] ?? []);
        match ($action) {
            'terminate' => $out[] = 'Služba se vypne a po ochranné lhůtě odstraní.',
            'purge' => $out[] = 'Služba se odstraní u poskytovatele. Tohle je konec, ne pozastavení.',
            'restore', 'archive.restore' => $out[] = 'Současný obsah služby se přepíše zálohou'.(($backup['at'] ?? null) !== null ? ' z '.$backup['at'] : '').'.',
            'rollback_snapshot' => $out[] = 'Server se vrátí do stavu ze snapshotu „'.($target['snapshot'] ?? '').'“; všechno, co udělal od té doby, zmizí.',
            'reinstall' => $out[] = 'Soubory serveru se přepíšou instalací od začátku.',
            'database.delete' => $out[] = 'Databáze se smaže i s obsahem.',
            'backup.delete', 'gbackup.delete' => $out[] = 'Záloha se smaže; obnovit z ní už nepůjde.',
            'staging.delete' => $out[] = 'Testovací kopie webu se odstraní. Ostrý web zůstává.',
            'site.delete' => $out[] = 'Web '.($target['site'] ?? '').' se vypne, zazálohuje a po ochranné lhůtě odstraní i se soubory a databázemi. Ostatní weby služby zůstávají.',
            'staging.push' => $out[] = 'Ostrý web se přepíše obsahem testovací kopie.',
            default => null,
        };
        if (in_array($action, ['terminate', 'purge', 'reinstall', 'restore', 'rollback_snapshot', 'staging.push'], true)) {
            if ($databases !== []) {
                $out[] = count($databases).'× databáze: '.implode(', ', array_slice($databases, 0, 6)).(count($databases) > 6 ? ' …' : '');
            }
            if ($mailboxes !== []) {
                $out[] = count($mailboxes).'× poštovní schránka: '.implode(', ', array_slice($mailboxes, 0, 6)).(count($mailboxes) > 6 ? ' …' : '');
            }
        }

        return $out;
    }

    /** What else hangs on this service and would go with it. @return list<string> */
    private function depends(Service $service, string $action): array
    {
        if (! in_array($action, ['terminate', 'purge'], true)) {
            return [];
        }
        $out = [];
        $addons = Service::query()->where('family', 'addon')->where('tags->parent_service_id', $service->id)
            ->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])->pluck('product_key')->all();
        if ($addons !== []) {
            $out[] = 'Zruší se i doplňky: '.implode(', ', $addons).'.';
        }
        try {
            $staging = app(StagingService::class)->link($service);
            if ($staging !== null) {
                $out[] = 'Odstraní se i testovací kopie webu.';
            }
            // the further sites of the plan go with the service too — each one archived first, like the service itself
            $sites = array_values(array_diff(IncludedServices::domains($service), [(string) $staging?->staging_domain]));
            if ($sites !== []) {
                $out[] = 'Zruší se i další weby služby: '.implode(', ', array_slice($sites, 0, 10)).(count($sites) > 10 ? ' a další' : '').'.';
            }
        } catch (Throwable) {
            // no staging link is not a reason to refuse a preview
        }
        $zones = DnsZone::query()->where('organization_id', $service->organization_id)->where('name', (string) $service->hostname)->count();
        if ($zones > 0) {
            $out[] = 'DNS zóna '.$service->hostname.' zůstane; záznamy na tuto službu přestanou platit.';
        }
        if (Subscription::query()->where('service_id', $service->id)->whereNotIn('state', [Subscription::CANCELLED])->exists()) {
            $out[] = 'Předplatné se zruší, další platba se nestrhne.';
        }

        return $out;
    }

    /** How far back the customer could come afterwards — named, not promised in general. @return array<string,mixed> */
    private function recovery(Service $service, string $action): array
    {
        if (in_array($action, ['backup.delete', 'gbackup.delete'], true)) {
            return ['kind' => 'none', 'note' => 'Smazanou zálohu už obnovit nepůjde.'];
        }
        if (in_array($action, ['terminate', 'purge'], true)) {
            return ['kind' => 'final_archive', 'grace_days' => $this->policy->graceDays(), 'retention_days' => $this->policy->retentionDays(),
                'note' => 'Před odstraněním uděláme úplnou zálohu a uchováme ji '.$this->policy->retentionDays().' dní; službu lze obnovit '.$this->policy->graceDays().' dní.'];
        }
        if (in_array($action, ['restore', 'rollback_snapshot', 'reinstall'], true)) {
            $latest = Backup::query()->where('service_id', $service->id)->whereIn('kind', ['pre_restore', 'pre_rollback', 'pre_reinstall'])
                ->where('state', 'completed')->orderByDesc('finished_at')->orderByDesc('id')->first();

            return ['kind' => 'safety_copy', 'retention_days' => $this->policy->retentionDays(), 'previous' => $latest?->finished_at?->toIso8601String(),
                'note' => 'Než cokoli přepíšeme, uděláme zálohu současného stavu a uchováme ji '.$this->policy->retentionDays().' dní.'];
        }

        return ['kind' => 'backups', 'note' => 'Obnovit jde z poslední zálohy služby.'];
    }
}
