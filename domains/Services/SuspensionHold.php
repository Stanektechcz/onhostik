<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Onhost\Domain\Billing\ServiceReinstatement;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\CommandContext;

/**
 * A suspension belongs to whoever imposed it (Brain card H17). A service the customer paused is the customer's to
 * resume; a service quarantined for abuse, stopped for an unpaid invoice or deactivated because its subscription
 * ended is not — the quarantine has to hold until the case is closed, the stop until the money arrives. Several holds
 * can sit on one service at once (unpaid *and* abusive): lifting one leaves the others, and the service stays down.
 *
 * Holds live in `tags.suspension.holds`, keyed by kind. A service suspended before holds existed is read from its
 * `suspended_reason`, so nothing that is held today becomes resumable by a deploy.
 */
final class SuspensionHold
{
    public const ABUSE = 'abuse';

    public const PAYMENT = 'payment';

    public const REVIEW = 'review';

    public const KINDS = [self::ABUSE, self::PAYMENT, self::REVIEW];

    /** The hold a suspension carries, judged by who imposed it and why; null = the customer's own pause. */
    public static function kindFor(CommandContext $context, string $reason): ?string
    {
        if ($context->actorType === 'user' && $context->actorId !== null && ! (bool) User::query()->whereKey($context->actorId)->value('is_staff')) {
            return null;
        }

        return self::fromReason($reason) ?? self::REVIEW; // staff or the platform decided: only they undo it
    }

    /** @return list<string> */
    public static function holds(Service $service): array
    {
        $stored = array_keys((array) data_get($service->tags, 'suspension.holds', []));
        if ($stored !== [] || data_get($service->tags, 'suspension') !== null) {
            return array_values(array_intersect(self::KINDS, $stored));
        }
        $legacy = $service->suspended_at === null ? null : self::fromReason((string) $service->suspended_reason);

        return $legacy === null ? [] : [$legacy];
    }

    /**
     * @param  array<string,mixed>  $tags
     * @return array<string,mixed>
     */
    public static function with(array $tags, string $kind, string $reason, CommandContext $context): array
    {
        $suspension = (array) ($tags['suspension'] ?? []);
        $suspension['holds'] = (array) ($suspension['holds'] ?? []);
        $suspension['holds'][$kind] ??= ['reason' => mb_substr($reason, 0, 120), 'by' => $context->actorType.($context->actorId !== null ? ':'.$context->actorId : ''), 'at' => now()->toIso8601String()];
        $tags['suspension'] = $suspension;

        return $tags;
    }

    /**
     * @param  array<string,mixed>  $tags
     * @return array<string,mixed>
     */
    public static function without(array $tags, ?string $kind): array
    {
        $suspension = (array) ($tags['suspension'] ?? []);
        $holds = $kind === null ? [] : array_diff_key((array) ($suspension['holds'] ?? []), [$kind => true]);
        $tags['suspension'] = ['holds' => $holds] + $suspension;

        return $tags;
    }

    /**
     * What the customer is told: whether they can switch the service back on, and if not, the way out — never the case
     * number or a staff note.
     *
     * @return ?array{hold:?string, holds:list<string>, customer_can_resume:bool, since:?string, message:?string}
     */
    public static function of(Service $service): ?array
    {
        if ($service->suspended_at === null) {
            return null;
        }
        $holds = self::holds($service);
        $first = $holds[0] ?? null;

        return [
            'hold' => $first, 'holds' => $holds, 'customer_can_resume' => $holds === [], 'since' => $service->suspended_at->toIso8601String(),
            'message' => match ($first) {
                self::ABUSE => 'Služba je pozastavená kvůli porušení podmínek. Odpovězte prosím na tiket, který jsme vám k tomu poslali; obnovit ji může jen náš tým.',
                self::PAYMENT => self::paymentMessage($service),
                self::REVIEW => 'Službu pozastavil náš tým. Napište prosím podpoře — obnovit ji může jen ona.',
                default => null,
            },
        ];
    }

    /**
     * "Po úhradě ji obnovíme" is true of a suspension for an unpaid invoice; of a service that was cancelled for it (or whose
     * subscription ran out) it was not — nothing brought such a service back (TASK-0025). With pay and restore switched on
     * the customer is shown the way back; without it, who can still help.
     */
    private static function paymentMessage(Service $service): string
    {
        if ($service->terminate_at === null) {
            return 'Služba je pozastavená kvůli neuhrazené platbě nebo ukončenému předplatnému. Po úhradě ji obnovíme; pokud to nejde, napište podpoře.';
        }
        $until = $service->terminate_at->format('j. n. Y');

        return app(ServiceReinstatement::class)->enabled()
            ? "Služba je zrušená kvůli neuhrazené platbě nebo ukončenému předplatnému. Do {$until} ji obnovíte zaplacením (Zaplatit a obnovit); pokud to nejde, napište podpoře."
            : "Služba je zrušená kvůli neuhrazené platbě nebo ukončenému předplatnému. Obnovit ji do {$until} může podpora — napište jí prosím.";
    }

    private static function fromReason(string $reason): ?string
    {
        $reason = mb_strtolower(trim($reason));

        return match (true) {
            str_starts_with($reason, 'abuse') => self::ABUSE,
            str_starts_with($reason, 'dunning'), str_contains($reason, 'subscription ended'), str_contains($reason, 'subscription expired'), str_contains($reason, 'unpaid') => self::PAYMENT,
            str_starts_with($reason, 'risk'), str_starts_with($reason, 'security'), str_starts_with($reason, 'legal') => self::REVIEW,
            default => null,
        };
    }
}
