<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Onhost\Domain\Identity\Authorization\TokenScopes;
use Onhost\Domain\Identity\Models\PersonalAccessToken;
use Onhost\Domain\Services\Commands\ServiceActionCommand;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Web\UptimeMonitor;
use Onhost\Platform\Commands\CommandAuthorizer;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Throwable;

/**
 * Declarative service spec (audit §5e-6, §5f-4): the whole configurable state of a service as one document — for a
 * web service PHP version, reverse proxies, default documents, the redirect, the security rules, cron jobs and
 * monitoring; for a game server its name, container image, startup variables and schedules; for a VPS its firewall
 * — read with GET and applied with PUT. Applying compares the document with what the node reports and dispatches
 * only the actions that change something, each through the ordinary workflow (audited, retried, reported), so
 * automation (Terraform-style, CI) can converge a service idempotently instead of scripting clicks.
 */
final class ServiceSpecService
{
    /** Sections per service family and the feature that must be on for each. */
    public const SECTIONS = [
        'web' => ['php' => 'php', 'proxies' => 'proxy', 'index' => 'default_docs', 'redirect' => 'redirects', 'security' => 'security', 'cron' => 'cron', 'monitoring' => 'monitoring'],
        'managed' => ['php' => 'php', 'proxies' => 'proxy', 'index' => 'default_docs', 'redirect' => 'redirects', 'security' => 'security', 'cron' => 'cron', 'monitoring' => 'monitoring'],
        'game' => ['name' => 'game_settings', 'image' => 'startup', 'variables' => 'startup', 'schedules' => 'schedule_tools'],
        'cloud' => ['firewall' => 'firewall'],
        'mail' => ['forwards' => 'forwards', 'catchall' => 'catchall', 'aliases' => 'aliases', 'mailboxes' => 'mailboxes', 'autoresponders' => 'autoresponder', 'spam' => 'spam'],
    ];

    /** Per-mailbox details (autoresponders, spam policies) are read for this many mailboxes at most — one panel call each. */
    public const MAIL_DETAIL_LIMIT = 50;

    public function __construct(private readonly ServiceFeatures $features, private readonly ServiceService $services, private readonly UptimeMonitor $monitor, private readonly CommandAuthorizer $authorizer) {}

    /** @return list<string> the section names a family knows */
    public static function sectionsFor(string $family): array
    {
        return array_keys(self::SECTIONS[$family] ?? []);
    }

    /** The current document: every section the service's features offer; sections the node cannot report carry null. @return array<string,mixed> */
    public function current(Service $service): array
    {
        $features = $this->features->features($service);
        $on = fn (string $key) => ! empty($features[$key]['enabled']);
        $read = function (string $kind) use ($service) {
            try {
                return $this->features->resources($service, $kind, true, []);
            } catch (Throwable) {
                return null;
            }
        };
        $sections = self::SECTIONS[$service->family] ?? [];
        $enabled = array_values(array_filter(array_keys($sections), fn ($s) => $on($sections[$s])));

        if ($service->family === 'game') {
            $detail = $on('game_settings') ? $read('server_detail') : null;
            $startup = $on('startup') ? $read('startup') : null;
            $schedules = $on('schedule_tools') ? $read('schedules') : null;

            return [
                'name' => is_array($detail) ? (string) ($detail['name'] ?? '') : null,
                'image' => is_array($startup) ? (string) ($startup['docker_image'] ?? '') : null,
                'variables' => is_array($startup) ? collect((array) ($startup['variables'] ?? []))->filter(fn ($v) => ! empty($v['editable']))->mapWithKeys(fn ($v) => [(string) $v['key'] => (string) $v['value']])->all() : null,
                'schedules' => is_array($schedules) ? array_values(array_map(fn (array $s) => ['name' => $s['name'], 'cron' => $s['cron'], 'active' => (bool) $s['active'], 'actions' => array_values(array_map(fn ($t) => ['action' => $t['action'], 'payload' => $t['payload']], $s['tasks'])), 'remote_id' => $s['remote_id']], $schedules)) : null,
                'features' => $enabled,
            ];
        }
        if ($service->family === 'cloud') {
            $firewall = $on('firewall') ? $read('firewall') : null;

            return ['firewall' => is_array($firewall) ? ['enabled' => (bool) ($firewall['enabled'] ?? true), 'rules' => array_values((array) ($firewall['rules'] ?? []))] : null, 'features' => $enabled];
        }
        if ($service->family === 'mail') { // audit §5g-3, §5h-6: the routing and the mailboxes of a mail domain as one document
            $pairs = fn ($rows) => is_array($rows) ? array_values(array_map(fn (array $r) => ['source' => strtolower(trim((string) ($r['source'] ?? ''))), 'destination' => strtolower(trim((string) ($r['destination'] ?? ''))), 'remote_id' => (string) ($r['remote_id'] ?? '')], $rows)) : null;
            $catchall = $on('catchall') ? $read('mail_catchall') : null;
            $boxes = $on('mailboxes') ? $read('mailboxes') : null;
            $mailboxes = is_array($boxes) ? array_values(array_map(fn (array $b) => ['address' => strtolower(trim((string) ($b['address'] ?? ''))), 'name' => (string) ($b['name'] ?? ''), 'quota_mb' => isset($b['quota_mb']) ? (int) $b['quota_mb'] : null, 'remote_id' => (string) ($b['remote_id'] ?? '')], $boxes)) : null;
            $autoresponders = null;
            $spam = null;
            if (is_array($mailboxes)) {
                $perBox = function (string $kind, string $remoteId) use ($service) {
                    try {
                        return $this->features->resources($service, $kind, true, ['remote_id' => $remoteId]);
                    } catch (Throwable) {
                        return null;
                    }
                };
                if ($on('autoresponder')) {
                    $autoresponders = [];
                    foreach (array_slice($mailboxes, 0, self::MAIL_DETAIL_LIMIT) as $box) {
                        $a = $perBox('mail_autoresponder', $box['remote_id']);
                        if (is_array($a)) {
                            $autoresponders[$box['address']] = ['enabled' => (bool) ($a['enabled'] ?? false), 'subject' => (string) ($a['subject'] ?? ''), 'text' => (string) ($a['text'] ?? ''), 'start' => $a['start'] ?? null, 'end' => $a['end'] ?? null];
                        }
                    }
                }
                if ($on('spam')) {
                    $spam = ['policies' => [], 'mailboxes' => []];
                    foreach (array_slice($mailboxes, 0, self::MAIL_DETAIL_LIMIT) as $box) {
                        $s = $perBox('mail_spam', $box['remote_id']);
                        if (! is_array($s)) {
                            continue;
                        }
                        if ($spam['policies'] === []) {
                            $spam['policies'] = array_values(array_map(fn (array $p) => ['id' => (string) ($p['remote_id'] ?? ''), 'name' => (string) ($p['name'] ?? '')], (array) ($s['policies'] ?? [])));
                        }
                        $policyId = $s['mailbox']['policy_id'] ?? null;
                        $spam['mailboxes'][$box['address']] = $policyId !== null && $policyId !== '' ? (string) $policyId : null;
                    }
                }
            }

            return [
                'forwards' => $pairs($on('forwards') ? $read('mail_forwards') : null),
                'catchall' => is_array($catchall) ? strtolower((string) ($catchall['destination'] ?? '')) : null,
                'aliases' => $pairs($on('aliases') ? $read('aliases') : null),
                'mailboxes' => $mailboxes,
                'autoresponders' => $autoresponders,
                'spam' => $spam,
                'features' => $enabled,
            ];
        }

        $php = $on('php') ? $read('php') : null;
        $proxies = $on('proxy') ? $read('proxies') : null;
        $index = $on('default_docs') ? $read('default_docs') : null;
        $redirect = $on('redirects') ? $read('redirect') : null;
        $security = $on('security') ? $read('security') : null;
        $cron = $on('cron') ? $read('cron') : null;
        $monitors = $on('monitoring') ? $this->monitor->status($service) : null;
        $first = is_array($monitors) ? ($monitors[0] ?? null) : null;
        $monitoring = is_array($monitors) ? ['id' => $first['id'] ?? null, 'enabled' => collect($monitors)->contains(fn ($m) => ! empty($m['enabled'])), 'url' => $first['url'] ?? null, 'interval_seconds' => $first['interval_seconds'] ?? null, 'monitors' => count($monitors)] : null;

        return [
            'php' => is_array($php) ? ($php['current'] ?? null) : null,
            'proxies' => is_array($proxies) ? array_values(array_map(fn (array $p) => ['name' => $p['name'], 'target' => $p['target'], 'path' => $p['path'] ?? '/', 'cache' => (bool) ($p['cache'] ?? false)], $proxies)) : null,
            'index' => is_array($index) ? array_values((array) ($index['names'] ?? [])) : null,
            'redirect' => is_array($redirect) ? ['target' => (string) ($redirect['target'] ?? ''), 'type' => (string) ($redirect['type'] ?? '301')] : null,
            'security' => is_array($security) ? array_intersect_key($security, array_flip(['deny', 'allow', 'bots', 'hotlink', 'hotlink_allow', 'hsts', 'headers'])) : null,
            'cron' => is_array($cron) ? array_values(array_map(fn (array $c) => ['schedule' => (string) ($c['schedule'] ?? ''), 'command' => (string) ($c['command'] ?? ''), 'remote_id' => (string) ($c['remote_id'] ?? '')], $cron)) : null,
            'monitoring' => $monitoring,
            'features' => $enabled,
        ];
    }

    /**
     * Applies the document: only sections present in `$spec` are considered; each that differs from the node's
     * state becomes one action (or, for cron and schedules, one action per job to add or remove).
     *
     * @param  array<string,mixed>  $spec
     * @return array{operations:list<array{section:string,action:string,operation_id:string}>, unchanged:list<string>, skipped:list<array{section:string,reason:string}>}
     */
    public function apply(Service $service, array $spec, CommandContext $context, string $idempotencyKey): array
    {
        $known = self::sectionsFor($service->family);
        if ($known === []) {
            throw new DomainError('spec_unsupported', 'This service family has no declarative spec.', 422);
        }
        $unknown = array_diff(array_keys($spec), $known);
        if ($unknown !== []) {
            throw new DomainError('spec_section_unknown', 'Unknown spec section(s): '.implode(', ', $unknown).'. Known: '.implode(', ', $known).'.', 422, ['field' => 'spec']);
        }
        $current = $this->current($service);
        $out = ['operations' => [], 'unchanged' => [], 'skipped' => []];
        $enabled = $current['features'];
        // The spec command asked `service.manage` once, for the whole document; each action inside it has a permission of its own
        // (TASK-0029 D29.7, audit C13 secondary gate). A `svc_manage` guest could otherwise schedule a console command through a
        // spec that the actions endpoint refuses them, so every step asks the bus's own decision — permission at the service,
        // step-up, the AI rule — and the run re-checks that same permission (H315). No spec action takes four eyes
        // (ServiceActionPermissionMapTest), so no approval is ever consumed here. A refused step is reported, not rolled back.
        $act = function (string $section, string $action, array $params, string $suffix = '') use ($service, $context, $idempotencyKey, &$out) {
            $key = "{$idempotencyKey}:{$section}{$suffix}";
            $command = new ServiceActionCommand($service->organization_id, $key, ['service_id' => $service->id, 'project_id' => $service->project_id, 'action' => $action, 'params' => $params]);
            $decision = $this->authorizer->authorize($command, $context);
            if (! $decision->allowed) {
                $out['skipped'][] = ['section' => $section, 'reason' => ($decision->requirement === 'step_up' ? 'step_up_required' : 'forbidden').':'.$action];

                return;
            }
            // PUT /spec is in the token's `services` family and is checked as `service.manage`, which `services:power` covers; the
            // bus never sees token scopes, so each step on a token session asks the token's own scope for its permission — the
            // one map the /actions path asks (TokenScopes, TASK-0030 C13-H2c; replaced TASK-0029's console-only refusal at the
            // 0029–0031 integration). A console step needs `services:console`, a step the map does not open is refused
            if (! $this->tokenMay($context, $command->permission())) {
                $out['skipped'][] = ['section' => $section, 'reason' => 'token_scope:'.$action];

                return;
            }
            // the step's audit row keeps the spec's context (the session's grant, as the /actions path records it): no spec action
            // needs a fresh step-up of its own, so $decision carries no method to add (ServiceActionPermissionMapTest pins that)
            $operation = $this->services->requestAction($service, $action, $context, $key, $params, chained: true, authorizedPermission: $command->permission());
            $out['operations'][] = ['section' => $section, 'action' => $action, 'operation_id' => $operation->id];
        };
        foreach ($spec as $section => $wanted) {
            if (! in_array($section, $enabled, true)) {
                $out['skipped'][] = ['section' => $section, 'reason' => 'feature_unavailable'];

                continue;
            }
            switch ($section) {
                case 'php':
                    $wanted = trim((string) $wanted);
                    $wanted === (string) $current['php'] ? $out['unchanged'][] = 'php' : $act('php', 'php.set', ['version' => $wanted]);
                    break;
                case 'proxies':
                    $items = array_values(array_map(fn ($p) => ['name' => strtolower(trim((string) ($p['name'] ?? ''))), 'target' => rtrim(trim((string) ($p['target'] ?? '')), '/'), 'path' => '/'.trim((string) ($p['path'] ?? '/'), '/'), 'cache' => (bool) ($p['cache'] ?? false)], (array) $wanted));
                    $have = array_values(array_map(fn (array $p) => ['name' => strtolower($p['name']), 'target' => rtrim($p['target'], '/'), 'path' => '/'.trim($p['path'], '/'), 'cache' => (bool) $p['cache']], (array) $current['proxies']));
                    self::sameSet($items, $have) ? $out['unchanged'][] = 'proxies' : $act('proxies', 'proxies.set', ['items' => $items]);
                    break;
                case 'index':
                    $names = array_values(array_map('strval', (array) $wanted));
                    $names === (array) $current['index'] ? $out['unchanged'][] = 'index' : $act('index', 'index.set', ['names' => $names]);
                    break;
                case 'redirect':
                    $target = trim((string) (is_array($wanted) ? ($wanted['target'] ?? '') : $wanted));
                    $type = is_array($wanted) ? (string) ($wanted['type'] ?? '301') : '301';
                    $target === (string) ($current['redirect']['target'] ?? '') && ($target === '' || $type === (string) ($current['redirect']['type'] ?? '301')) ? $out['unchanged'][] = 'redirect' : $act('redirect', 'redirect.set', ['target' => $target, 'type' => $type]);
                    break;
                case 'security':
                    $rules = array_intersect_key((array) $wanted, array_flip(['deny', 'allow', 'bots', 'hotlink', 'hotlink_allow', 'hsts', 'headers']));
                    $have = (array) $current['security'];
                    $differs = false;
                    foreach ($rules as $key => $value) {
                        $a = is_array($value) ? array_values(array_map('strval', $value)) : (bool) $value;
                        $b = isset($have[$key]) ? (is_array($have[$key]) ? array_values(array_map('strval', $have[$key])) : (bool) $have[$key]) : null;
                        if ($a !== $b) {
                            $differs = true;
                        }
                    }
                    $differs ? $act('security', 'security.set', ['rules' => array_merge($have, $rules)]) : $out['unchanged'][] = 'security';
                    break;
                case 'cron':
                    $wantedJobs = array_values(array_map(fn ($c) => ['schedule' => preg_replace('/\s+/', ' ', trim((string) ($c['schedule'] ?? ''))), 'command' => trim((string) ($c['command'] ?? ''))], (array) $wanted));
                    $haveJobs = (array) $current['cron'];
                    $sig = fn (array $c) => preg_replace('/\s+/', ' ', trim($c['schedule'])).' | '.trim($c['command']);
                    $wantedSigs = array_map($sig, $wantedJobs);
                    $haveSigs = array_map($sig, $haveJobs);
                    $changed = false;
                    foreach ($haveJobs as $i => $job) {
                        if (! in_array($haveSigs[$i], $wantedSigs, true) && $job['remote_id'] !== '') {
                            $act('cron', 'cron.delete', ['remote_id' => $job['remote_id']], ':del:'.$job['remote_id']);
                            $changed = true;
                        }
                    }
                    foreach ($wantedJobs as $i => $job) {
                        if (! in_array($wantedSigs[$i], $haveSigs, true)) {
                            $act('cron', 'cron.create', $job, ':add:'.$i);
                            $changed = true;
                        }
                    }
                    if (! $changed) {
                        $out['unchanged'][] = 'cron';
                    }
                    break;
                case 'monitoring':
                    $enabledWanted = (bool) (is_array($wanted) ? ($wanted['enabled'] ?? true) : $wanted);
                    $have = (array) $current['monitoring'];
                    $url = is_array($wanted) ? trim((string) ($wanted['url'] ?? '')) : '';
                    $interval = is_array($wanted) && isset($wanted['interval_seconds']) ? (int) $wanted['interval_seconds'] : null;
                    $same = $enabledWanted === (bool) ($have['enabled'] ?? false) && ($url === '' || $url === (string) ($have['url'] ?? '')) && ($interval === null || $interval === (int) ($have['interval_seconds'] ?? 0));
                    if ($same) {
                        $out['unchanged'][] = 'monitoring';
                        break;
                    }
                    $monitor = $this->monitor->configure($service, array_filter(['id' => $have['id'] ?? null, 'enabled' => $enabledWanted, 'url' => $url ?: null, 'interval_seconds' => $interval], fn ($v) => $v !== null), $context);
                    $out['operations'][] = ['section' => 'monitoring', 'action' => 'monitoring.set', 'operation_id' => (string) $monitor->id];
                    break;
                    // ── mail domains ─────────────────────────────────────────────────────────
                case 'forwards':
                case 'aliases':
                    $norm = fn ($r) => ['source' => strtolower(trim((string) (is_array($r) ? ($r['source'] ?? '') : ''))), 'destination' => strtolower(trim((string) (is_array($r) ? ($r['destination'] ?? '') : '')))];
                    $sig = fn (array $r) => $r['source'].' → '.$r['destination'];
                    $wantedRows = array_values(array_map($norm, (array) $wanted));
                    $haveRows = (array) $current[$section];
                    $wantedSigs = array_map($sig, $wantedRows);
                    $haveSigs = array_map(fn (array $r) => $sig($norm($r)), $haveRows);
                    [$create, $delete] = $section === 'forwards' ? ['forward.create', 'forward.delete'] : ['alias.create', 'alias.delete'];
                    $changed = false;
                    foreach ($haveRows as $i => $row) {
                        if (! in_array($haveSigs[$i], $wantedSigs, true) && ($row['remote_id'] ?? '') !== '') {
                            $act($section, $delete, ['remote_id' => (string) $row['remote_id']], ':del:'.$row['remote_id']);
                            $changed = true;
                        }
                    }
                    foreach ($wantedRows as $i => $row) {
                        if (! in_array($wantedSigs[$i], $haveSigs, true)) {
                            $act($section, $create, $row, ':add:'.$i);
                            $changed = true;
                        }
                    }
                    if (! $changed) {
                        $out['unchanged'][] = $section;
                    }
                    break;
                case 'catchall':
                    $destination = strtolower(trim((string) (is_array($wanted) ? ($wanted['destination'] ?? '') : (string) $wanted)));
                    $destination === (string) $current['catchall'] ? $out['unchanged'][] = 'catchall' : $act('catchall', 'catchall.set', ['destination' => $destination]);
                    break;
                case 'mailboxes': // §5h-6: rows are created or updated, never deleted by a document (a mailbox holds data)
                    $have = collect((array) $current['mailboxes'])->keyBy('address');
                    $seen = [];
                    $changed = false;
                    foreach (array_values((array) $wanted) as $i => $row) {
                        $row = is_array($row) ? $row : ['address' => (string) $row];
                        $address = strtolower(trim((string) ($row['address'] ?? '')));
                        if ($address === '') {
                            continue;
                        }
                        $seen[] = $address;
                        $box = $have->get($address);
                        if ($box === null) {
                            if (empty($row['password'])) {
                                $out['skipped'][] = ['section' => 'mailboxes', 'reason' => "password_required:{$address}"];

                                continue;
                            }
                            $act('mailboxes', 'mailbox.create', array_filter(['address' => $address, 'password' => (string) $row['password'], 'name' => isset($row['name']) ? (string) $row['name'] : null, 'quota_mb' => isset($row['quota_mb']) ? (int) $row['quota_mb'] : null], fn ($v) => $v !== null), ':add:'.$i);
                            $changed = true;

                            continue;
                        }
                        $changes = [];
                        if (isset($row['name']) && trim((string) $row['name']) !== (string) $box['name']) {
                            $changes['name'] = trim((string) $row['name']);
                        }
                        if (isset($row['quota_mb']) && (int) $row['quota_mb'] !== (int) ($box['quota_mb'] ?? 0)) {
                            $changes['quota_mb'] = (int) $row['quota_mb'];
                        }
                        if (! empty($row['password'])) {
                            $changes['password'] = (string) $row['password']; // a password in the document rotates it
                        }
                        if ($changes !== []) {
                            $act('mailboxes', 'mailbox.update', ['remote_id' => $box['remote_id']] + $changes, ':upd:'.$box['remote_id']);
                            $changed = true;
                        }
                    }
                    foreach ($have as $address => $box) {
                        if (! in_array($address, $seen, true)) {
                            $out['skipped'][] = ['section' => 'mailboxes', 'reason' => "mailbox_extra:{$address}"];
                        }
                    }
                    if (! $changed) {
                        $out['unchanged'][] = 'mailboxes';
                    }
                    break;
                case 'autoresponders':
                    $have = collect((array) $current['mailboxes'])->keyBy('address');
                    $currentAr = (array) $current['autoresponders'];
                    $changed = false;
                    foreach ((array) $wanted as $address => $settings) {
                        $address = strtolower(trim((string) $address));
                        $box = $have->get($address);
                        if ($box === null) {
                            $out['skipped'][] = ['section' => 'autoresponders', 'reason' => "mailbox_unknown:{$address}"];

                            continue;
                        }
                        $settings = is_array($settings) ? $settings : ['enabled' => (bool) $settings];
                        $want = ['enabled' => filter_var($settings['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN), 'subject' => trim((string) ($settings['subject'] ?? '')), 'text' => (string) ($settings['text'] ?? ''), 'start' => $settings['start'] ?? null, 'end' => $settings['end'] ?? null];
                        $has = (array) ($currentAr[$address] ?? []);
                        $bothOff = $has !== [] && ! $want['enabled'] && ! (bool) ($has['enabled'] ?? false); // an autoresponder that is off is the same whatever text it once had
                        $same = $bothOff || ($has !== [] && (bool) ($has['enabled'] ?? false) === $want['enabled'] && (string) ($has['subject'] ?? '') === $want['subject'] && (string) ($has['text'] ?? '') === $want['text'] && ($has['start'] ?? null) == $want['start'] && ($has['end'] ?? null) == $want['end']);
                        if ($same) {
                            continue;
                        }
                        $act('autoresponders', 'autoresponder.set', ['remote_id' => $box['remote_id']] + $want, ':'.$box['remote_id']);
                        $changed = true;
                    }
                    if (! $changed) {
                        $out['unchanged'][] = 'autoresponders';
                    }
                    break;
                case 'spam':
                    $have = collect((array) $current['mailboxes'])->keyBy('address');
                    $policies = collect((array) ($current['spam']['policies'] ?? []));
                    $currentSpam = (array) ($current['spam']['mailboxes'] ?? []);
                    $rows = is_array($wanted) && isset($wanted['mailboxes']) && is_array($wanted['mailboxes']) ? $wanted['mailboxes'] : (array) $wanted;
                    $changed = false;
                    foreach ($rows as $address => $policy) {
                        $address = strtolower(trim((string) $address));
                        $box = $have->get($address);
                        if ($box === null) {
                            $out['skipped'][] = ['section' => 'spam', 'reason' => "mailbox_unknown:{$address}"];

                            continue;
                        }
                        $policyId = is_numeric($policy) ? (string) (int) $policy : (string) ($policies->first(fn (array $p) => strcasecmp($p['name'], (string) $policy) === 0)['id'] ?? '');
                        if ($policyId === '' || $policyId === '0') {
                            $out['skipped'][] = ['section' => 'spam', 'reason' => "policy_unknown:{$policy}"];

                            continue;
                        }
                        if ((string) ($currentSpam[$address] ?? '') === $policyId) {
                            continue;
                        }
                        $act('spam', 'spam.policy', ['remote_id' => $box['remote_id'], 'policy_id' => $policyId], ':'.$box['remote_id']);
                        $changed = true;
                    }
                    if (! $changed) {
                        $out['unchanged'][] = 'spam';
                    }
                    break;
                    // ── game servers ─────────────────────────────────────────────────────────
                case 'name':
                    $name = trim((string) $wanted);
                    $name === (string) $current['name'] || $name === '' ? $out['unchanged'][] = 'name' : $act('name', 'rename', ['name' => $name]);
                    break;
                case 'image':
                    $image = trim((string) $wanted);
                    $image === (string) $current['image'] || $image === '' ? $out['unchanged'][] = 'image' : $act('image', 'image.set', ['image' => $image]);
                    break;
                case 'variables':
                    $have = (array) $current['variables'];
                    $changed = false;
                    foreach ((array) $wanted as $key => $value) {
                        $key = strtoupper(trim((string) $key));
                        if (! array_key_exists($key, $have)) {
                            $out['skipped'][] = ['section' => 'variables', 'reason' => "variable_unknown:{$key}"];

                            continue;
                        }
                        if ((string) $value !== (string) $have[$key]) {
                            $act('variables', 'variable.set', ['key' => $key, 'value' => (string) $value], ':'.$key);
                            $changed = true;
                        }
                    }
                    if (! $changed) {
                        $out['unchanged'][] = 'variables';
                    }
                    break;
                case 'schedules':
                    $norm = fn (array $s) => ['name' => trim((string) ($s['name'] ?? '')), 'cron' => preg_replace('/\s+/', ' ', trim((string) ($s['cron'] ?? ''))), 'actions' => array_values(array_map(fn ($a) => ['action' => (string) ($a['action'] ?? 'command'), 'payload' => (string) ($a['payload'] ?? '')], (array) ($s['actions'] ?? [])))];
                    $sig = fn (array $s) => $s['name'].' | '.$s['cron'].' | '.json_encode($s['actions']);
                    $wantedJobs = array_values(array_map($norm, (array) $wanted));
                    $haveJobs = array_values(array_map($norm, (array) $current['schedules']));
                    $wantedSigs = array_map($sig, $wantedJobs);
                    $haveSigs = array_map($sig, $haveJobs);
                    $changed = false;
                    foreach ((array) $current['schedules'] as $i => $job) {
                        if (! in_array($haveSigs[$i], $wantedSigs, true) && ($job['remote_id'] ?? '') !== '') {
                            $act('schedules', 'schedule.delete', ['remote_id' => (string) $job['remote_id']], ':del:'.$job['remote_id']);
                            $changed = true;
                        }
                    }
                    foreach ($wantedJobs as $i => $job) {
                        if (! in_array($wantedSigs[$i], $haveSigs, true)) {
                            $act('schedules', 'schedule.create', $job, ':add:'.$i);
                            $changed = true;
                        }
                    }
                    if (! $changed) {
                        $out['unchanged'][] = 'schedules';
                    }
                    break;
                    // ── VPS ──────────────────────────────────────────────────────────────────
                case 'firewall':
                    $rules = array_values(array_map(fn ($r) => array_intersect_key((array) $r + ['type' => 'in', 'enable' => true], array_flip(['action', 'type', 'proto', 'dport', 'source', 'enable', 'comment'])), (array) (is_array($wanted) ? ($wanted['rules'] ?? []) : [])));
                    $enabledWanted = is_array($wanted) ? (bool) ($wanted['enabled'] ?? true) : (bool) $wanted;
                    $have = (array) $current['firewall'];
                    $sameRules = self::sameSet(array_map(fn ($r) => ['action' => strtoupper((string) ($r['action'] ?? '')), 'type' => (string) ($r['type'] ?? 'in'), 'proto' => $r['proto'] ?? null, 'dport' => $r['dport'] ?? null, 'source' => $r['source'] ?? null], $rules), array_map(fn ($r) => ['action' => strtoupper((string) ($r['action'] ?? '')), 'type' => (string) ($r['type'] ?? 'in'), 'proto' => $r['proto'] ?? null, 'dport' => $r['dport'] ?? null, 'source' => $r['source'] ?? null], (array) ($have['rules'] ?? [])));
                    $sameRules && $enabledWanted === (bool) ($have['enabled'] ?? true) ? $out['unchanged'][] = 'firewall' : $act('firewall', 'firewall.apply', ['rules' => $rules, 'enabled' => $enabledWanted]);
                    break;
            }
        }

        return $out;
    }

    /**
     * Whether the API token behind `$context` carries the scope `$permission` needs (TokenScopes). The portal's own session is
     * not a token and is decided by the bus alone. Fails closed: a token session whose token is gone, or a permission the map
     * does not open to tokens, is refused — the same answer ApiContext::assertTokenScope gives on the /actions path.
     */
    private function tokenMay(CommandContext $context, ?string $permission): bool
    {
        $session = (string) $context->sessionId;
        if (! str_starts_with($session, 'token:')) {
            return true;
        }
        // the key is a bigint: anything but digits is a token that does not exist (PostgreSQL refuses to compare it and the
        // whole spec apply answered 500 instead of skipping the step)
        $id = substr($session, strlen('token:'));
        $token = ctype_digit($id) ? PersonalAccessToken::query()->find((int) $id) : null;
        $needed = TokenScopes::for($permission);

        return $token instanceof PersonalAccessToken && $needed !== null && $token->can($needed);
    }

    /** @param  list<array<string,mixed>>  $a @param  list<array<string,mixed>>  $b */
    private static function sameSet(array $a, array $b): bool
    {
        $norm = fn (array $list) => collect($list)->map(fn ($i) => json_encode($i))->sort()->values()->all();

        return $norm($a) === $norm($b);
    }
}
