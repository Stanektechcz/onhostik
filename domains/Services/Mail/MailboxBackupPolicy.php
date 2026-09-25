<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Mail;

use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;

/**
 * How long a mail plan's mailboxes keep their backups (TASK-0024, owner decision 3). Mail Business sells `backup_days` 14,
 * Mail Enterprise 30, and nothing ever told the panel: ISPConfig backs mailboxes up on its own nightly run and keeps
 * `backup_copies` of them only where `backup_interval` is set. Here that number is the plan's `backup_days`, kept daily.
 *
 * It changes what existing customers' mailboxes keep — and fewer copies make the panel delete backups — so all of it
 * sits behind the default-off rule `mail.backup_retention`: while it is off, mailboxes are created and plans are changed
 * exactly as before. While it is on, a new mailbox gets the retention at creation and a paid plan change applies it to
 * the service's own mailboxes (more copies at once, fewer only by an operator's `--allow-prune`). Mailboxes that existed
 * before reach it only through `onhost:mail:backup-retention`, which lists by default and writes only with `--apply`.
 *
 * Only family `mail`: a web plan's `backup_days` means its site backup sets (BackupScheduler), not its mailboxes.
 * What was last applied is kept on the service (`tags.mail_backup`), so the doctor sees who is behind without asking a panel.
 */
final class MailboxBackupPolicy
{
    public const RULE = 'mail.backup_retention';

    public const INTERVAL = 'daily';

    public const DOCTOR_CHECK = 'mail backups: mailboxes keep the backups the plan sells';

    /** The daily copies a plan's mailboxes keep, or null when the plan sells none (or is not a mail plan). @param array<string,mixed> $entitlements */
    public static function copiesFor(string $family, array $entitlements): ?int
    {
        $days = (int) ($entitlements['backup_days'] ?? 0);

        return $family === 'mail' && $days > 0 ? $days : null;
    }

    public static function target(Service $service): ?int
    {
        return self::copiesFor((string) $service->family, (array) $service->entitlements);
    }

    public static function ruleOn(): bool
    {
        return app(AutomationLedger::class)->enabled(self::RULE);
    }

    /**
     * What a new mailbox of the service is created with: the plan's copies while the rule is on, nothing otherwise. Read
     * from the SERVICE, never from the request — a customer does not choose how long the server keeps their backups.
     *
     * @return array{backup_copies?: int}
     */
    public static function onCreate(Service $service): array
    {
        $copies = self::target($service);

        return $copies !== null && self::ruleOn() ? ['backup_copies' => $copies] : [];
    }

    /** @return array<string,mixed>|null what was last applied to the service's mailboxes */
    public static function applied(Service $service): ?array
    {
        $tag = ((array) $service->tags)['mail_backup'] ?? null;

        return is_array($tag) ? $tag : null;
    }

    /** Whether the service's mailboxes are not (known to be) at what its plan sells: never applied, another number, a held downgrade or a refused mailbox. */
    public static function behind(Service $service): bool
    {
        $copies = self::target($service);
        if ($copies === null) {
            return false;
        }
        $tag = self::applied($service);

        return $tag === null || (int) ($tag['copies'] ?? 0) !== $copies || ! empty($tag['failed']) || (int) ($tag['held'] ?? 0) > 0;
    }

    /**
     * A mail domain the platform has just made holds no mailbox yet, and every one made while the rule is on gets the
     * retention at creation — so it starts at its plan. An adopted domain may hold mailboxes from before; it does not.
     */
    public static function stampProvisioned(Service $service, bool $alreadyExisted): void
    {
        $copies = self::target($service);
        if ($alreadyExisted || $copies === null || ! self::ruleOn()) {
            return;
        }
        self::record($service, ['copies' => $copies, 'source' => 'provision']);
    }

    /** @param  array<string,mixed>  $summary */
    public static function record(Service $service, array $summary): void
    {
        $tags = (array) $service->tags;
        $tags['mail_backup'] = ['interval' => self::INTERVAL, 'applied_at' => now()->toIso8601String()] + $summary;
        $service->forceFill(['tags' => $tags])->save();
    }

    /**
     * The doctor's row. Never blocking: a mailbox without the plan's retention still receives and sends mail. Read from the
     * database only, no panel is asked.
     *
     * @return array{area:string, check:string, ok:bool, detail:string, blocking:bool}
     */
    public static function doctorRow(): array
    {
        $sold = 0;
        $behind = 0;
        $held = 0;
        $failed = 0;
        Service::query()->where('family', 'mail')->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])->whereNotNull('provider_instance_id')
            ->select(['id', 'family', 'entitlements', 'tags'])->orderBy('id')
            ->chunk(500, function ($services) use (&$sold, &$behind, &$held, &$failed) {
                foreach ($services as $service) {
                    if (self::target($service) === null) {
                        continue;
                    }
                    $sold++;
                    if (! self::behind($service)) {
                        continue;
                    }
                    $behind++;
                    $tag = self::applied($service) ?? [];
                    $held += (int) ((int) ($tag['held'] ?? 0) > 0);
                    $failed += (int) ! empty($tag['failed']);
                }
            });
        $on = self::ruleOn();
        [$ok, $detail] = match (true) {
            $sold === 0 => [true, 'no mail service is sold backup_days'],
            ! $on => [false, 'rule '.self::RULE.' off: '.$sold.' mail service(s) sold backup_days keep whatever the panel defaults to — onhost:mail:backup-retention shows what it would set; then switch the rule on (docs/runbooks/backups.md)'],
            $behind === 0 => [true, 'rule '.self::RULE.' on · '.$sold.' mail service(s) at their plan'],
            default => [false, $behind.' mail service(s) behind the plan'.($held > 0 ? " ({$held} with a held downgrade — --allow-prune deletes backups)" : '').($failed > 0 ? " ({$failed} with a mailbox the panel refused)" : '').' — onhost:mail:backup-retention, then --apply'],
        };

        return ['area' => 'lifecycle', 'check' => self::DOCTOR_CHECK, 'ok' => $ok, 'detail' => $detail, 'blocking' => false];
    }
}
