<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\WebHostingProvider;
use Onhost\Providers\Contracts\WebToolsProvider;

/**
 * A suspended site is more than a stopped vhost. The panels switch the web server's answer off and nothing else: the
 * site's cron jobs kept running (a quarantined site went on sending mail from its cron), and its files stayed reachable —
 * and writable — by FTP. Pausing switches those off as well and REMEMBERS which ones it switched off; resuming switches
 * exactly those on again, so a job the customer had turned off themselves stays off.
 *
 * The memory lives in `services.tags.suspension_paused` and is a union: a quarantine on top of an unpaid suspension does
 * not forget what the first one paused. Databases have no switch in either panel; with the site stopped nothing of the
 * site reaches them, and remote access is a permission of its own.
 */
final class SuspensionDepth
{
    /** A tag of its own: `tags.suspension` belongs to the holds (`SuspensionHold` reads its mere presence). */
    public const TAG = 'suspension_paused';

    /**
     * @return array{cron:list<string>, ftp:list<string>, errors:list<string>, transient:bool}
     */
    public function pause(Service $service, object $adapter, ResourceRef $site): array
    {
        $remembered = (array) data_get($service->tags, self::TAG, []);
        $paused = ['cron' => array_values(array_map('strval', (array) ($remembered['cron'] ?? []))), 'ftp' => array_values(array_map('strval', (array) ($remembered['ftp'] ?? [])))];
        $errors = [];
        $transient = false;
        if ($adapter instanceof WebHostingProvider && $adapter instanceof WebToolsProvider) {
            foreach ($this->listing(fn () => $adapter->listCron($site), $errors, $transient) as $job) {
                if (! ($job['active'] ?? true)) {
                    continue;
                }
                if ($this->attempt(fn () => $adapter->setCronActive($site, (string) $job['remote_id'], false), "cron {$job['remote_id']}", $errors, $transient)) {
                    $paused['cron'][] = (string) $job['remote_id'];
                }
            }
        }
        if ($adapter instanceof WebHostingProvider) {
            foreach ($this->listing(fn () => $adapter->listFtpAccounts($site), $errors, $transient) as $account) {
                if (! ($account['active'] ?? true)) {
                    continue;
                }
                if ($this->attempt(fn () => $adapter->setFtpAccountActive($site, (string) $account['remote_id'], false), "ftp {$account['remote_id']}", $errors, $transient)) {
                    $paused['ftp'][] = (string) $account['remote_id'];
                }
            }
        }
        $paused = ['cron' => array_values(array_unique($paused['cron'])), 'ftp' => array_values(array_unique($paused['ftp']))];
        $this->remember($service, $paused);

        return $paused + ['errors' => $errors, 'transient' => $transient];
    }

    /**
     * @return array{cron:list<string>, ftp:list<string>, errors:list<string>, transient:bool}
     */
    public function resume(Service $service, object $adapter, ResourceRef $site): array
    {
        $remembered = (array) data_get($service->tags, self::TAG, []);
        $left = ['cron' => [], 'ftp' => []];
        $done = ['cron' => [], 'ftp' => []];
        $errors = [];
        $transient = false;
        foreach (array_map('strval', (array) ($remembered['cron'] ?? [])) as $id) {
            $ok = $adapter instanceof WebToolsProvider && $this->attempt(fn () => $adapter->setCronActive($site, $id, true), "cron {$id}", $errors, $transient, gone: true);
            $ok ? $done['cron'][] = $id : $left['cron'][] = $id;
        }
        foreach (array_map('strval', (array) ($remembered['ftp'] ?? [])) as $id) {
            $ok = $adapter instanceof WebHostingProvider && $this->attempt(fn () => $adapter->setFtpAccountActive($site, $id, true), "ftp {$id}", $errors, $transient, gone: true);
            $ok ? $done['ftp'][] = $id : $left['ftp'][] = $id;
        }
        $this->remember($service, $transient ? $left : ['cron' => [], 'ftp' => []]); // what could not be switched on because the panel did not answer is tried again; what is gone is forgotten

        return $done + ['errors' => $errors, 'transient' => $transient];
    }

    /** @param array{cron:list<string>, ftp:list<string>} $paused */
    private function remember(Service $service, array $paused): void
    {
        $tags = (array) $service->tags;
        if ($paused['cron'] === [] && $paused['ftp'] === []) {
            unset($tags[self::TAG]);
        } else {
            $tags[self::TAG] = $paused;
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
