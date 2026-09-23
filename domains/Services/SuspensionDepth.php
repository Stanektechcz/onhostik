<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\GameToolsProvider;
use Onhost\Providers\Contracts\MailProvider;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\WebHostingProvider;
use Onhost\Providers\Contracts\WebToolsProvider;

/**
 * A suspended service is more than a stopped vhost. The panels switch the web server's answer off and nothing else: the
 * site's cron jobs kept running (a quarantined site went on sending mail from its cron), and its files stayed reachable —
 * and writable — by FTP. A game server's schedules are the same story: the panel stops the server, the schedule rows stay
 * armed, and every slot fires against a server that may not run. A site's Node.js app is the panel's too, with its own
 * proxy for the site's domains: stopping the vhost left it answering. Pausing switches all of those off and REMEMBERS which
 * ones it switched off; resuming switches exactly those on again, so a job the customer had turned off themselves stays
 * off (H440: suspension decides what stops, and everything it stopped comes back).
 *
 * The memory lives in `services.tags.suspension_paused` and is a union: a quarantine on top of an unpaid suspension does
 * not forget what the first one paused. Databases have no switch in either panel; with the site stopped nothing of the
 * site reaches them, and remote access is a permission of its own. Scheduled backups need no switch either — the
 * scheduler only looks at services that are ACTIVE or DEGRADED, so a suspended service makes none and keeps the ones
 * it has.
 */
final class SuspensionDepth
{
    /** A tag of its own: `tags.suspension` belongs to the holds (`SuspensionHold` reads its mere presence). */
    public const TAG = 'suspension_paused';

    /** Everything a suspension switches off, in the order it is switched off. */
    public const KINDS = ['cron', 'ftp', 'schedule', 'app', 'mail'];

    /**
     * @return array{cron:list<string>, ftp:list<string>, schedule:list<string>, app:list<string>, mail:list<string>, errors:list<string>, transient:bool}
     */
    public function pause(Service $service, object $adapter, ResourceRef $ref): array
    {
        $paused = $this->remembered($service);
        $errors = [];
        $transient = false;
        foreach ($this->switches($adapter, $ref, self::mailRef($service)) as $kind => $switch) {
            foreach ($this->listing($switch['list'], $errors, $transient) as $row) {
                if (! ($row['active'] ?? true)) {
                    continue;
                }
                $id = (string) $row['remote_id'];
                if ($this->attempt(fn () => ($switch['set'])($id, false), "{$kind} {$id}", $errors, $transient)) {
                    $paused[$kind][] = $id;
                }
            }
        }
        $paused = array_map(fn (array $ids) => array_values(array_unique($ids)), $paused);
        $this->remember($service, $paused);

        return $paused + ['errors' => $errors, 'transient' => $transient];
    }

    /**
     * @return array{cron:list<string>, ftp:list<string>, schedule:list<string>, app:list<string>, mail:list<string>, errors:list<string>, transient:bool}
     */
    public function resume(Service $service, object $adapter, ResourceRef $ref): array
    {
        $remembered = $this->remembered($service);
        $switches = $this->switches($adapter, $ref, self::mailRef($service));
        $left = $done = array_fill_keys(self::KINDS, []);
        $errors = [];
        $transient = false;
        foreach (self::KINDS as $kind) {
            $switch = $switches[$kind] ?? null;
            foreach ($remembered[$kind] as $id) {
                $ok = $switch !== null && $this->attempt(fn () => ($switch['set'])($id, true), "{$kind} {$id}", $errors, $transient, gone: true);
                $ok ? $done[$kind][] = $id : $left[$kind][] = $id;
            }
        }
        $this->remember($service, $transient ? $left : array_fill_keys(self::KINDS, [])); // what could not be switched on because the panel did not answer is tried again; what is gone is forgotten

        return $done + ['errors' => $errors, 'transient' => $transient];
    }

    /**
     * What this adapter can switch off, by kind. A capability the adapter does not have is simply not in the list —
     * the same service seen through a panel that cannot do it pauses what it can and says so in the errors.
     *
     * @return array<string, array{list:callable():array<int,array<string,mixed>>, set:callable(string,bool):mixed}>
     */
    /** The mail domain a service was given for its mailboxes, if it has one (a web service's mail is its own resource). */
    private static function mailRef(Service $service): ?ResourceRef
    {
        return ProviderBinding::query()->where('service_id', $service->id)->where('remote_type', 'mail_domain')->first()?->ref();
    }

    private function switches(object $adapter, ResourceRef $ref, ?ResourceRef $mail = null): array
    {
        $out = [];
        if ($adapter instanceof MailProvider && $mail !== null) {
            // a suspended site must not keep sending mail — an unpaid customer's mailboxes are a spam relay with a
            // bill attached, and a quarantined one is the reason the quarantine exists. Receiving stays on, so
            // nothing addressed to the customer is lost while they are switched off.
            $out['mail'] = [
                'list' => fn () => array_map(fn (array $box) => array_merge($box, ['active' => (bool) ($box['sending'] ?? true)]), $adapter->listMailboxes($mail)),
                'set' => fn (string $id, bool $on) => $adapter->updateMailbox(new ResourceRef('mailbox', $id, $mail->node, ['client_id' => $mail->meta['client_id'] ?? null], $mail->serviceId), ['sending_enabled' => $on]),
            ];
        }
        if ($adapter instanceof WebHostingProvider && $adapter instanceof WebToolsProvider) {
            $out['cron'] = ['list' => fn () => $adapter->listCron($ref), 'set' => fn (string $id, bool $on) => $adapter->setCronActive($ref, $id, $on)];
        }
        if ($adapter instanceof WebHostingProvider) {
            $out['ftp'] = ['list' => fn () => $adapter->listFtpAccounts($ref), 'set' => fn (string $id, bool $on) => $adapter->setFtpAccountActive($ref, $id, $on)];
        }
        if ($adapter instanceof GameToolsProvider) {
            $out['schedule'] = ['list' => fn () => $adapter->listSchedules($ref), 'set' => fn (string $id, bool $on) => $adapter->setScheduleActive($ref, $id, $on)];
        }
        if ($adapter instanceof WebToolsProvider) {
            // a Node.js app of a site is the panel's, not the site's: stopping the vhost left it running and answering for
            // the site's domains through its own proxy. Its listing says running/stopped; here that is the switch.
            $out['app'] = [
                'list' => fn () => array_map(fn (array $p) => $p + ['active' => ($p['state'] ?? '') === 'running'], $adapter->nodeProjects($ref)),
                'set' => fn (string $id, bool $on) => $adapter->nodeProjectAction($ref, $id, $on ? 'start' : 'stop'),
            ];
        }

        return $out;
    }

    /** @return array{cron:list<string>, ftp:list<string>, schedule:list<string>, app:list<string>} */
    private function remembered(Service $service): array
    {
        $tags = (array) data_get($service->tags, self::TAG, []);
        $out = [];
        foreach (self::KINDS as $kind) {
            $out[$kind] = array_values(array_map('strval', (array) ($tags[$kind] ?? [])));
        }

        return $out;
    }

    /** @param array{cron:list<string>, ftp:list<string>, schedule:list<string>, app:list<string>} $paused */
    private function remember(Service $service, array $paused): void
    {
        $tags = (array) $service->tags;
        $kept = array_filter($paused, fn (array $ids) => $ids !== []); // an empty kind is not written: a web service's tag keeps the shape it always had
        if ($kept === []) {
            unset($tags[self::TAG]);
        } else {
            $tags[self::TAG] = $kept;
        }
        $service->forceFill(['tags' => $tags])->save();
    }

    /**
     * @param  callable():array<int,array<string,mixed>>  $list
     * @param  list<string>  $errors
     * @return array<int,array<string,mixed>>
     */
    private function listing(callable $list, array &$errors, bool &$transient): array
    {
        try {
            return $list();
        } catch (ProviderException $e) {
            $errors[] = 'listing: '.$e->errorCode->value;
            $transient = $transient || in_array($e->errorCode, [ProviderErrorCode::TRANSIENT, ProviderErrorCode::UNKNOWN], true);

            return [];
        }
    }

    /** @param list<string> $errors */
    private function attempt(callable $call, string $what, array &$errors, bool &$transient, bool $gone = false): bool
    {
        try {
            $call();

            return true;
        } catch (ProviderException $e) {
            if ($gone && $e->errorCode === ProviderErrorCode::NOT_FOUND) {
                return true; // deleted while the site was down: nothing to switch on, nothing to remember
            }
            $errors[] = "{$what}: ".$e->errorCode->value;
            $transient = $transient || in_array($e->errorCode, [ProviderErrorCode::TRANSIENT, ProviderErrorCode::UNKNOWN], true);

            return false;
        }
    }
}
