<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Penpot\PenpotParents;
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

    /**
     * The preview speaks the language of the person asking (G8, audit follow-up): English when the request says `?locale=en` (the
     * panel always does, from the language the page is in), Czech otherwise. The browser's Accept-Language is deliberately not
     * consulted: the application default is English and nearly every browser sends en-US, so it would turn the Czech preview of a
     * Czech customer English. Only the words differ; the fingerprint is made from the target and is the same in both languages.
     */
    private static function t(string $cs, string $en): string
    {
        return self::english() ? $en : $cs;
    }

    private static function english(): bool
    {
        return request()->query('locale') === 'en';
    }

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
            throw new DomainError('action_not_destructive', self::t('Tato akce nic nemaže ani nepřepisuje; náhled není potřeba.', 'This action deletes and overwrites nothing; no preview is needed.'), 422, ['action' => $action]);
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
            throw new DomainError('target_changed', self::t('Od zobrazení náhledu se změnilo, čeho se akce týká. Otevřete náhled znovu a potvrďte ho.', 'What the action affects has changed since the preview was shown. Open the preview again and confirm it.'), 409, ['action' => $action, 'fingerprint' => $now]);
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
        if ($action === 'reinstall' && $service->family === 'cloud') {
            $out['image'] = (string) ($params['image'] ?? ''); // another system than the one previewed is another action (C9)
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
            'terminate' => $out[] = self::t('Služba se vypne a po ochranné lhůtě odstraní.', 'The service is switched off and removed after the grace period.'),
            'purge' => $out[] = self::t('Služba se odstraní u poskytovatele. Tohle je konec, ne pozastavení.', 'The service is removed at the provider. This is the end, not a suspension.'),
            'restore', 'archive.restore' => $out[] = self::t('Současný obsah služby se přepíše zálohou'.(($backup['at'] ?? null) !== null ? ' z '.$backup['at'] : '').'.', 'The current content of the service is overwritten by the backup'.(($backup['at'] ?? null) !== null ? ' from '.$backup['at'] : '').'.'),
            'rollback_snapshot' => $out[] = self::t('Server se vrátí do stavu ze snapshotu „'.($target['snapshot'] ?? '').'“; všechno, co udělal od té doby, zmizí.', 'The server goes back to the state of the snapshot "'.($target['snapshot'] ?? '').'"; everything it has done since then is lost.'),
            'reinstall' => $out[] = $service->family === 'cloud'
                ? self::t('Systémový disk serveru se nahradí čistou instalací '.((string) ($target['image'] ?? '') ?: 'vybraného systému').'; všechno, co na něm je, zmizí. Server se na chvíli vypne. Původní disk zůstane odpojený u snapshotu před reinstalací.', 'The system disk of the server is replaced by a clean installation of '.((string) ($target['image'] ?? '') ?: 'the chosen system').'; everything on it is lost. The server is switched off for a moment. The original disk stays detached with the snapshot taken before the reinstall.')
                : self::t('Soubory serveru se přepíšou instalací od začátku.', 'The files of the server are overwritten by an installation from scratch.'),
            'database.delete' => $out[] = self::t('Databáze se smaže i s obsahem.', 'The database is deleted with its content.'),
            'backup.delete', 'gbackup.delete' => $out[] = self::t('Záloha se smaže; obnovit z ní už nepůjde.', 'The backup is deleted; nothing can be restored from it any more.'),
            'staging.delete' => $out[] = self::t('Testovací kopie webu se odstraní. Ostrý web zůstává.', 'The test copy of the site is removed. The live site stays.'),
            'site.delete' => $out[] = self::t('Web '.($target['site'] ?? '').' se vypne, zazálohuje a po ochranné lhůtě odstraní i se soubory a databázemi. Ostatní weby služby zůstávají.', 'The site '.($target['site'] ?? '').' is switched off, backed up and, after the grace period, removed with its files and databases. The other sites of the service stay.'),
            'staging.push' => $out[] = self::t('Ostrý web se přepíše obsahem testovací kopie.', 'The live site is overwritten by the content of the test copy.'),
            default => null,
        };
        if (in_array($action, ['terminate', 'purge', 'reinstall', 'restore', 'rollback_snapshot', 'staging.push'], true)) {
            if ($databases !== []) {
                $out[] = count($databases).self::t('× databáze: ', '× database: ').implode(', ', array_slice($databases, 0, 6)).(count($databases) > 6 ? ' …' : '');
            }
            if ($mailboxes !== []) {
                $out[] = count($mailboxes).self::t('× poštovní schránka: ', '× mailbox: ').implode(', ', array_slice($mailboxes, 0, 6)).(count($mailboxes) > 6 ? ' …' : '');
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
            $out[] = self::t('Zruší se i doplňky: ', 'The add-ons are cancelled too: ').implode(', ', $addons).'.';
        }
        try {
            $staging = app(StagingService::class)->link($service);
            if ($staging !== null) {
                $out[] = self::t('Odstraní se i testovací kopie webu.', 'The test copy of the site is removed too.');
            }
            // the further sites of the plan go with the service too — each one archived first, like the service itself
            $sites = array_values(array_diff(IncludedServices::domains($service), [(string) $staging?->staging_domain]));
            if ($sites !== []) {
                $out[] = self::t('Zruší se i další weby služby: ', 'The other sites of the service are cancelled too: ').implode(', ', array_slice($sites, 0, 10)).(count($sites) > 10 ? self::t(' a další', ' and more') : '').'.';
            }
        } catch (Throwable) {
            // no staging link is not a reason to refuse a preview
        }
        $penpots = PenpotParents::of($service)->filter(fn (Service $child) => $child->terminate_at === null)->map(fn (Service $child) => (string) ($child->hostname ?: $child->name))->values()->all();
        if ($penpots !== []) { // H-R7: the Penpot ordered for the service ends with it, archived first like the service itself
            $out[] = self::t('Zruší se i Penpot služby: ', 'The Penpot of the service is cancelled too: ').implode(', ', $penpots).'.';
        }
        $zones = DnsZone::query()->where('organization_id', $service->organization_id)->where('name', (string) $service->hostname)->count();
        if ($zones > 0) {
            $out[] = self::t('DNS zóna '.$service->hostname.' zůstane; záznamy na tuto službu přestanou platit.', 'The DNS zone '.$service->hostname.' stays; the records pointing at this service stop working.');
        }
        if (Subscription::query()->where('service_id', $service->id)->whereNotIn('state', [Subscription::CANCELLED])->exists()) {
            $out[] = self::t('Předplatné se zruší, další platba se nestrhne.', 'The subscription is cancelled; no further payment is taken.');
        }

        return $out;
    }

    /** How far back the customer could come afterwards — named, not promised in general. @return array<string,mixed> */
    private function recovery(Service $service, string $action): array
    {
        if (in_array($action, ['backup.delete', 'gbackup.delete'], true)) {
            return ['kind' => 'none', 'note' => self::t('Smazanou zálohu už obnovit nepůjde.', 'A deleted backup cannot be restored any more.')];
        }
        if (in_array($action, ['terminate', 'purge'], true)) {
            return ['kind' => 'final_archive', 'grace_days' => $this->policy->graceDays(), 'retention_days' => $this->policy->retentionDays(),
                'note' => self::t('Před odstraněním uděláme úplnou zálohu a uchováme ji '.$this->policy->retentionDays().' dní; službu lze obnovit '.$this->policy->graceDays().' dní.', 'Before the removal we make a full backup and keep it for '.$this->policy->retentionDays().' days; the service can be restored for '.$this->policy->graceDays().' days.')];
        }
        if (in_array($action, ['restore', 'rollback_snapshot', 'reinstall'], true)) {
            $latest = Backup::query()->where('service_id', $service->id)->whereIn('kind', ['pre_restore', 'pre_rollback', 'pre_reinstall'])
                ->where('state', 'completed')->orderByDesc('finished_at')->orderByDesc('id')->first();

            return ['kind' => 'safety_copy', 'retention_days' => $this->policy->retentionDays(), 'previous' => $latest?->finished_at?->toIso8601String(),
                'note' => self::t('Než cokoli přepíšeme, uděláme zálohu současného stavu a uchováme ji '.$this->policy->retentionDays().' dní.', 'Before we overwrite anything we make a backup of the current state and keep it for '.$this->policy->retentionDays().' days.')];
        }

        return ['kind' => 'backups', 'note' => self::t('Obnovit jde z poslední zálohy služby.', 'You can restore from the latest backup of the service.')];
    }
}
