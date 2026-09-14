<?php

declare(strict_types=1);

namespace Onhost\Domain\Support\Assistant;

use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Support\Triage;

/**
 * What the customer wants done to a service, read from plain language ("restartuj vps", "zálohuj shop.cz", "nasaď
 * poslední verzi", "aktualizuj wordpress", "zapni redis cache", "obnov staging", "přepni na php 8.3"). The result is a
 * proposal — the panel chat, Discord and the LLM agent show it as a button that the customer confirms; nothing runs
 * without that click. Only actions the service's plan and executor offer are proposed.
 */
final class ServiceIntent
{
    /** regex on the normalized text, action, params, families, label cs/en (%s = service), feature key */
    private const RULES = [
        ['/\b(restartuj|restartovat|restart|reboot|rebootni|rebootnout)\b/', 'power', ['power_action' => 'reboot'], ['cloud', 'game'], ['Restartovat %s', 'Restart %s'], 'power'],
        ['/\b(vypni|vypnout|shutdown|zastav|zastavit|stopni)\b(?!.*\b(monitoring|cache|redis|https|ssl|cdn|hsts)\b)/', 'power', ['power_action' => 'shutdown'], ['cloud', 'game'], ['Vypnout %s', 'Shut down %s'], 'power'],
        ['/\b(nastartuj|nastartovat|nahod|nahodit|spust server|spustit server|start server|zapni server|zapnout server)\b/', 'power', ['power_action' => 'start'], ['cloud', 'game'], ['Zapnout %s', 'Start %s'], 'power'],
        ['/\b(zalohuj|zalohovat|zalohu|zaloha|zalohy|backup)\b/', 'backup', ['kind' => 'manual'], ['web', 'managed', 'cloud', 'game', 'mail'], ['Zálohovat %s', 'Back up %s'], 'backups'],
        ['/\b(deploy|deployni|deploynout|nasad|nasadit|nasadte|nasazeni|nasazen)\b/', 'deploy.run', [], ['web', 'managed'], ['Nasadit poslední verzi na %s', 'Deploy the latest version to %s'], 'deploy'],
        ['/\b(aktualizuj|aktualizovat|aktualizace|update|updatni|updatuj|upgraduj)\b[^.]{0,40}\b(wordpress|wp|plugin|pluginy|sablon|jadro|core)\b|\b(wordpress|wp)\b[^.]{0,40}\b(aktualizuj|aktualizovat|aktualizace|update)\b/', 'wp.update', ['what' => 'all', 'staged' => true], ['web', 'managed'], ['Aktualizovat WordPress na %s', 'Update WordPress on %s'], 'wordpress'],
        ['/\b(zapni|zapnout|aktivuj|aktivovat|enable)\b[^.]{0,40}\b(redis|object cache|objektov[a-z]* cache|cache)\b/', 'wp.cache', ['enabled' => true], ['web', 'managed'], ['Zapnout Redis cache na %s', 'Turn Redis cache on for %s'], 'wordpress'],
        ['/\b(vypni|vypnout|deaktivuj|disable)\b[^.]{0,40}\b(redis|object cache|cache)\b/', 'wp.cache', ['enabled' => false], ['web', 'managed'], ['Vypnout Redis cache na %s', 'Turn Redis cache off for %s'], 'wordpress'],
        ['/\b(obnov|obnovit|refresh|refreshni|aktualizuj)\b[^.]{0,30}\bstaging\b|\bstaging\b[^.]{0,30}\b(obnov|obnovit|refresh)\b/', 'staging.refresh', ['databases' => true], ['web', 'managed'], ['Obnovit staging z produkce (%s)', 'Refresh staging from production (%s)'], 'staging'],
        ['/\b(zaloz|zalozit|vytvor|vytvorit|create|udelej|udelat)\b[^.]{0,30}\bstaging\b/', 'staging.create', ['databases' => true], ['web', 'managed'], ['Založit staging pro %s', 'Create staging for %s'], 'staging'],
        ['/\b(prenes|prenest|push|nahraj|nahrat|preklop)\b[^.]{0,30}\bstaging\b[^.]{0,30}\b(produkc|ostr)|\bstaging\b[^.]{0,30}\b(do produkce|na ostrou|to production)\b/', 'staging.push', ['databases' => true, 'confirm' => true], ['web', 'managed'], ['Přenést staging do produkce (%s)', 'Push staging to production (%s)'], 'staging'],
        ['/\b(vymaz|vymazat|vyprazdni|vyprazdnit|promaz|purge|clear|flush)\b[^.]{0,30}\b(cdn|cache)\b|\bcdn\b[^.]{0,20}\b(vymaz|vyprazdni|purge)\b/', 'cdn.purge', ['settings' => []], ['web', 'managed'], ['Vyprázdnit CDN cache pro %s', 'Purge the CDN cache of %s'], 'cdn'],
        ['/\b(vystav|vystavit|obnov|obnovit|issue|renew|zaridit|zarid)\b[^.]{0,30}\b(certifikat|ssl|https|lets? ?encrypt)\b|\b(certifikat|ssl)\b[^.]{0,30}\b(vystav|vystavit|obnov|obnovit)\b/', 'ssl.issue', [], ['web', 'managed'], ['Vystavit certifikát pro %s', 'Issue a certificate for %s'], 'ssl'],
        ['/\b(vynut|vynutit|force|zapni|zapnout)\b[^.]{0,20}\bhttps\b/', 'https.force', ['enabled' => true], ['web', 'managed'], ['Vynutit HTTPS na %s', 'Force HTTPS on %s'], 'https'],
        ['/\b(zapni|zapnout|aktivuj|aktivovat|enable|povol|povolit|turn on)\b[^.]{0,30}\b(http ?\/?3|quic)\b|\b(http ?\/?3|quic)\b[^.]{0,30}\b(zapni|zapnout|aktivuj|aktivovat|povol|on)\b/', 'http3.set', ['enabled' => true], ['web', 'managed'], ['Zapnout HTTP/3 na %s', 'Turn HTTP/3 on for %s'], 'http3'],
        ['/\b(vypni|vypnout|deaktivuj|deaktivovat|disable|zakaz|zakazat|turn off)\b[^.]{0,30}\b(http ?\/?3|quic)\b|\b(http ?\/?3|quic)\b[^.]{0,30}\b(vypni|vypnout|deaktivuj|zakaz|off)\b/', 'http3.set', ['enabled' => false], ['web', 'managed'], ['Vypnout HTTP/3 na %s', 'Turn HTTP/3 off for %s'], 'http3'],
        ['/\bphp\s*(5\.6|7\.[0-4]|8\.[0-5])\b/', 'php.set', ['version' => '$1'], ['web', 'managed'], ['Přepnout %s na PHP $1', 'Switch %s to PHP $1'], 'php'],
    ];

    /**
     * @return list<array{kind:string,label:string,service_id:string,action:string,params:array<string,mixed>,confirm:bool,class:string,service:string}>
     */
    public static function detect(string $text, ?Organization $organization, ServiceFeatures $features, string $locale = 'cs'): array
    {
        if ($organization === null || trim($text) === '') {
            return [];
        }
        $normalized = ' '.Triage::normalize($text).' ';
        $services = Service::query()->where('organization_id', $organization->id)->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])->get();
        if ($services->isEmpty()) {
            return [];
        }
        $named = $services->filter(function (Service $s) use ($normalized) {
            foreach (array_filter([$s->hostname, $s->label, $s->name]) as $n) {
                $n = Triage::normalize((string) $n);
                if (strlen($n) >= 4 && str_contains($normalized, $n)) {
                    return true;
                }
            }

            return false;
        });
        $out = [];
        foreach (self::RULES as [$pattern, $action, $params, $families, $labels, $feature]) {
            if (! preg_match($pattern, $normalized, $m)) {
                continue;
            }
            $params = array_map(fn ($v) => is_string($v) && $v === '$1' ? ($m[1] ?? '') : $v, $params);
            $candidates = ($named->isNotEmpty() ? $named : $services)->filter(fn (Service $s) => in_array($s->family, $families, true));
            foreach ($candidates->take(3) as $service) {
                $f = $features->features($service);
                if (empty($f[$feature]['enabled']) || ! in_array($action, $features->actions($service), true)) {
                    continue;
                }
                if ($action === 'php.set') {
                    try {
                        $versions = (array) data_get($features->resources($service, 'php'), 'versions', []);
                    } catch (\Throwable) {
                        $versions = [];
                    }
                    if (! in_array((string) $params['version'], array_map('strval', $versions), true)) {
                        continue;
                    }
                }
                $name = (string) ($service->hostname ?: ($service->label ?: $service->name));
                $out[] = ['kind' => 'service_action', 'label' => str_replace('$1', (string) ($m[1] ?? ''), sprintf($locale === 'en' ? $labels[1] : $labels[0], $name)), 'service_id' => $service->id, 'action' => $action, 'params' => $params, 'confirm' => true, 'class' => 'SAFE_WRITE', 'service' => $name];
            }
            if (count($out) >= 4) {
                break;
            }
        }

        return $out;
    }
}
