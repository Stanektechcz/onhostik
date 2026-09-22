<?php

declare(strict_types=1);

namespace Onhost\Domain\Support\Assistant;

/**
 * What the assistant may put on a button — and in whose words.
 *
 * The model proposed ANY action a service offers, with parameters and a button label of its own making; the customer then
 * confirmed a dialog that showed the label alone. A model that followed an injected instruction (a ticket, a file name, a page
 * it was asked to look at) could offer "Vyčistit cache" — and the click ran a shell command, saved a file, forwarded the mail
 * somewhere else or put a stranger's SSH key on the server. A confirmation protects nobody who cannot see what they confirm.
 *
 *  · safe by default: an action that is not listed here is never proposed — new actions start outside;
 *  · listed are actions that can be undone or repeated and whose parameters are a choice, not a payload: no command, no file
 *    content, no credential, no destination (URL, address, host), no deletion, nothing that asks for a fresh step-up;
 *  · a parameter that is not listed never travels, a listed one has to be one of its values;
 *  · the label is composed here from the action and its parameters. The model's words stay in the conversation.
 */
final class AssistantProposals
{
    /** action → its parameters: a list = one of these values, 'bool', or a pattern. A trailing `!` on the key = required. */
    public const ACTIONS = [
        'power' => ['power_action!' => ['start', 'reboot', 'shutdown']], // never stop / reset / kill: those lose what is in memory
        'backup' => [],
        'snapshot' => ['name!' => '/^[A-Za-z0-9][A-Za-z0-9_-]{0,39}$/'],
        'deploy.run' => [],
        'wp.update' => ['what' => ['core', 'plugins', 'themes', 'all']],
        'wp.cache' => ['enabled' => 'bool'],
        'staging.refresh' => [],
        'staging.push' => [],
        'cdn.purge' => [],
        'ssl.issue' => [],
        'https.force' => ['enabled' => 'bool'],
        'php.set' => ['version!' => '/^\d\.\d$/'],
        // what the platform learned to do since — each one undoable or repeatable, its parameters a reference to the service's own
        // records (checked again by the platform when the button is pressed) or a choice
        'restore.test' => ['backup_id!' => '/^bkp_[A-Za-z0-9]{10,40}$/'], // the dumps go into a database of their own, are compared and dropped: the site is not touched
        'staging.create' => [],
        'deploy.rollback' => ['deployment_id!' => '/^dpl_[A-Za-z0-9]{10,40}$/'], // back to a release that ran before; deploy.run brings the newest one again
        'rescue.stop' => [], // getting out of a rescue session must always be possible
        'node.action' => ['remote_id!' => '/^[A-Za-z0-9][A-Za-z0-9_.-]{0,63}$/', 'op!' => ['start', 'stop', 'restart']], // never `delete`
        'http3.set' => ['enabled' => 'bool'],
        'schedule.toggle' => ['remote_id!' => '/^\d{1,10}$/', 'active' => 'bool'],
        'database.export' => ['remote_id!' => '/^[A-Za-z0-9][A-Za-z0-9:_.-]{0,119}$/'], // a dump for the customer to download
        'mailbox.backup' => ['remote_id!' => '/^\d{1,10}$/'],
        'ssl.wildcard' => [], // for the service's own domain
    ];

    /** What the model is told about a parameter that is a reference or a pattern: where it finds a valid value. */
    private const HINTS = [
        'snapshot.name' => 'letters, digits, - and _',
        'php.set.version' => 'like 8.3',
        'restore.test.backup_id' => 'id of a finished backup (get_service_resource kind backups)',
        'deploy.rollback.deployment_id' => 'id of an earlier deployment (kind deployments)',
        'node.action.remote_id' => 'the app name (kind node_projects)',
        'schedule.toggle.remote_id' => 'the schedule id (kind schedules)',
        'database.export.remote_id' => 'the database (kind databases)',
        'mailbox.backup.remote_id' => 'the mailbox id (kind mailboxes)',
    ];

    /** The list as the model reads it in the tool's description — made from ACTIONS, so the two can never tell different stories. */
    public static function describe(): string
    {
        $out = [];
        foreach (self::ACTIONS as $action => $rules) {
            $params = [];
            foreach ($rules as $key => $rule) {
                $name = rtrim($key, '!');
                $shape = match (true) {
                    $rule === 'bool' => 'true|false',
                    is_array($rule) => implode('|', $rule),
                    default => self::HINTS["{$action}.{$name}"] ?? 'a value',
                };
                $params[] = 'params.'.$name.' '.$shape.(str_ends_with($key, '!') ? '' : ', optional');
            }
            $out[] = $action.($params === [] ? '' : ' ('.implode('; ', $params).')');
        }

        return implode(', ', $out);
    }

    /**
     * The parameters of a proposal: only the listed ones, each one of its values — or null when a required one is missing or a
     * given one is not valid (the proposal is refused; nothing is guessed).
     *
     * @param  array<string,mixed>  $given
     * @return array<string,mixed>|null
     */
    public static function params(string $action, array $given): ?array
    {
        $out = [];
        foreach (self::ACTIONS[$action] ?? [] as $key => $rule) {
            $required = str_ends_with($key, '!');
            $key = rtrim($key, '!');
            if (! array_key_exists($key, $given) || $given[$key] === null || $given[$key] === '') {
                if ($required) {
                    return null;
                }

                continue;
            }
            $value = $given[$key];
            if ($rule === 'bool') {
                $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($bool === null) {
                    return null;
                }
                $out[$key] = $bool;
            } elseif (is_array($rule)) {
                if (! is_string($value) || ! in_array($value, $rule, true)) {
                    return null;
                }
                $out[$key] = $value;
            } else {
                if (! is_scalar($value) || preg_match($rule, (string) $value) !== 1) {
                    return null;
                }
                $out[$key] = (string) $value;
            }
        }

        return $out;
    }

    /** @param array<string,mixed> $params */
    public static function label(string $action, array $params, string $service, string $locale): string
    {
        $en = $locale === 'en';
        $on = ($params['enabled'] ?? true) !== false;
        $what = match ($action) {
            'power' => match ((string) ($params['power_action'] ?? '')) {
                'start' => $en ? 'Start' : 'Zapnout', 'shutdown' => $en ? 'Shut down' : 'Vypnout', default => $en ? 'Restart' : 'Restartovat',
            },
            'backup' => $en ? 'Back up' : 'Zálohovat',
            'snapshot' => ($en ? 'Take snapshot ' : 'Vytvořit snapshot ').($params['name'] ?? ''),
            'deploy.run' => $en ? 'Deploy from the repository' : 'Nasadit z repozitáře',
            'wp.update' => ($en ? 'Update WordPress' : 'Aktualizovat WordPress').' ('.($params['what'] ?? 'all').')',
            'wp.cache' => $on ? ($en ? 'Turn the object cache on' : 'Zapnout objektovou cache') : ($en ? 'Turn the object cache off' : 'Vypnout objektovou cache'),
            'staging.refresh' => $en ? 'Refresh staging from production' : 'Obnovit staging z produkce',
            'staging.push' => $en ? 'Replace production with staging' : 'Přepsat produkci stagingem',
            'cdn.purge' => $en ? 'Purge the CDN cache' : 'Vyčistit CDN cache',
            'ssl.issue' => $en ? 'Issue the certificate' : 'Vystavit certifikát',
            'https.force' => $on ? ($en ? 'Force HTTPS' : 'Vynutit HTTPS') : ($en ? 'Stop forcing HTTPS' : 'Zrušit vynucení HTTPS'),
            'php.set' => ($en ? 'Switch PHP to ' : 'Přepnout PHP na ').($params['version'] ?? ''),
            'restore.test' => $en ? 'Test restoring the backup' : 'Otestovat obnovu zálohy',
            'staging.create' => $en ? 'Create a staging copy of the site' : 'Vytvořit staging (kopii webu)',
            'deploy.rollback' => $en ? 'Roll back to the earlier deployment' : 'Vrátit předchozí nasazení',
            'rescue.stop' => $en ? 'End rescue mode' : 'Ukončit záchranný režim',
            'node.action' => match ((string) ($params['op'] ?? '')) {
                'start' => $en ? 'Start app ' : 'Spustit aplikaci ', 'stop' => $en ? 'Stop app ' : 'Zastavit aplikaci ', default => $en ? 'Restart app ' : 'Restartovat aplikaci ',
            }.($params['remote_id'] ?? ''),
            'http3.set' => $on ? ($en ? 'Turn HTTP/3 on' : 'Zapnout HTTP/3') : ($en ? 'Turn HTTP/3 off' : 'Vypnout HTTP/3'),
            'schedule.toggle' => (($params['active'] ?? true) !== false ? ($en ? 'Turn schedule on #' : 'Zapnout plán #') : ($en ? 'Turn schedule off #' : 'Vypnout plán #')).($params['remote_id'] ?? ''),
            'database.export' => ($en ? 'Export database ' : 'Exportovat databázi ').($params['remote_id'] ?? ''),
            'mailbox.backup' => ($en ? 'Back up mailbox #' : 'Zálohovat schránku #').($params['remote_id'] ?? ''),
            'ssl.wildcard' => $en ? 'Issue a wildcard certificate' : 'Vystavit wildcard certifikát',
            default => $action,
        };

        return mb_substr(trim($what).' · '.$service, 0, 80);
    }
}
