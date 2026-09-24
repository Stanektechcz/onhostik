<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Onhost\Domain\Services\Mail\MailDomains;
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
     * The panel saying "not now", not "never": worth another round. A breaker that is open and a rate limit belong
     * here as much as a timeout does — counting them as final used to end the retries and wipe the memory of what
     * had been switched off.
     */
    private const RETRYABLE = [ProviderErrorCode::TRANSIENT, ProviderErrorCode::UNKNOWN, ProviderErrorCode::CIRCUIT_OPEN, ProviderErrorCode::RATE_LIMIT];

    /**
     * @return array{cron:list<string>, ftp:list<string>, schedule:list<string>, app:list<string>, mail:list<string>, errors:list<string>, transient:bool}
     */
    public function pause(Service $service, object $adapter, ResourceRef $ref): array
    {
        $paused = $this->remembered($service);
        $errors = [];
        $transient = false;
        foreach ($this->switches($adapter, $ref, self::mailRefs($service)) as $kind => $switch) {
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
        $switches = $this->switches($adapter, $ref, self::mailRefs($service));
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
        // What is still off stays remembered, whatever the reason — the memory is the only record that the platform
        // switched it off. Keeping it only for a timeout meant a flat refusal (aaPanel answers `status:false`, which
        // is VALIDATION) wiped the memory: the cron job stayed off at the panel, nothing knew it ever had been on,
        // and no later resume could put it back. What is genuinely gone is forgotten by `attempt(gone: true)`.
        $this->remember($service, $left);

        return $done + ['errors' => $errors, 'transient' => $transient, 'left' => array_filter($left, fn (array $ids) => $ids !== [])];
    }

    /**
     * What this adapter can switch off, by kind. A capability the adapter does not have is simply not in the list —
     * the same service seen through a panel that cannot do it pauses what it can and says so in the errors.
     *
     * @return array<string, array{list:callable():array<int,array<string,mixed>>, set:callable(string,bool):mixed}>
     */
    /** Every mail domain a service was given for its mailboxes (a web service's mail is a resource of its own). @return list<ResourceRef> */
    private static function mailRefs(Service $service): array
    {
        return MailDomains::refsOf($service);
    }

    /** @param list<ResourceRef> $mail */
    private function switches(object $adapter, ResourceRef $ref, array $mail = []): array
    {
        $out = [];
        if ($adapter instanceof MailProvider && $mail !== []) {
            // a suspended site must not keep sending mail — an unpaid customer's mailboxes are a spam relay with a
            // bill attached, and a quarantined one is the reason the quarantine exists. Receiving stays on, so
            // nothing addressed to the customer is lost while they are switched off. Every domain the service has
            // mail in is switched, and each mailbox is written through the domain it actually belongs to.
            $boxes = [];
            $of = function () use ($adapter, $mail, &$boxes): array {
                if ($boxes === []) {
                    foreach ($mail as $domain) {
                        foreach ($adapter->listMailboxes($domain) as $box) {
                            $boxes[(string) $box['remote_id']] = [$domain, array_merge($box, ['active' => (bool) ($box['sending'] ?? true)])];
                        }
                    }
                }

                return $boxes;
            };
            $out['mail'] = [
                'list' => fn () => array_values(array_map(fn (array $found) => $found[1], $of())),
                'set' => function (string $id, bool $on) use ($adapter, $mail, $of) {
                    $domain = $of()[$id][0] ?? $mail[0];

                    return $adapter->updateMailbox(new ResourceRef('mailbox', $id, $domain->node, ['client_id' => $domain->meta['client_id'] ?? null], $domain->serviceId), ['sending_enabled' => $on]);
                },
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
            $transient = $transient || in_array($e->errorCode, self::RETRYABLE, true);

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
            $transient = $transient || in_array($e->errorCode, self::RETRYABLE, true);

            return false;
        }
    }
}
