<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains;

use Onhost\Platform\StateMachine\StateMachine;

/** Domain lifecycle (blueprint §46.4). Renewal-due buckets are derived from expires_at, not stored. */
final class DomainStateMachine
{
    public const PENDING_REGISTRATION = 'PENDING_REGISTRATION';

    public const PENDING_REGISTRY = 'PENDING_REGISTRY';

    public const ACTIVE = 'ACTIVE';

    public const EXPIRED = 'EXPIRED';

    public const GRACE = 'GRACE';

    public const REDEMPTION = 'REDEMPTION';

    public const TRANSFER_IN_PENDING = 'TRANSFER_IN_PENDING';

    public const TRANSFER_OUT_PENDING = 'TRANSFER_OUT_PENDING';

    public const TRANSFERRED_OUT = 'TRANSFERRED_OUT';

    public const FAILED = 'FAILED';

    public const DELETED = 'DELETED';

    public static function machine(): StateMachine
    {
        return new StateMachine('domain', [
            self::PENDING_REGISTRATION => ['label' => 'Čeká na registraci', 'next' => [self::PENDING_REGISTRY, self::ACTIVE, self::FAILED], 'tone' => 'warn', 'ui' => 'provisioning'],
            self::PENDING_REGISTRY => ['label' => 'Čeká na registr', 'next' => [self::ACTIVE, self::FAILED], 'tone' => 'warn', 'ui' => 'provisioning'],
            self::TRANSFER_IN_PENDING => ['label' => 'Probíhá transfer k nám', 'next' => [self::ACTIVE, self::FAILED], 'tone' => 'warn', 'ui' => 'provisioning'],
            self::ACTIVE => ['label' => 'Aktivní', 'next' => [self::EXPIRED, self::TRANSFER_OUT_PENDING, self::DELETED], 'tone' => 'ok', 'ui' => 'aktivni'],
            self::EXPIRED => ['label' => 'Expirováno', 'next' => [self::GRACE, self::ACTIVE, self::REDEMPTION, self::DELETED], 'tone' => 'hot', 'ui' => 'pozastaveno'],
            self::GRACE => ['label' => 'Ochranná lhůta', 'next' => [self::ACTIVE, self::REDEMPTION, self::DELETED], 'tone' => 'hot', 'ui' => 'pozastaveno'],
            self::REDEMPTION => ['label' => 'Redemption', 'next' => [self::ACTIVE, self::DELETED], 'tone' => 'hot', 'ui' => 'pozastaveno'],
            self::TRANSFER_OUT_PENDING => ['label' => 'Probíhá transfer pryč', 'next' => [self::ACTIVE, self::TRANSFERRED_OUT], 'tone' => 'warn', 'ui' => 'pozastaveno'],
            self::TRANSFERRED_OUT => ['label' => 'Převedeno jinam', 'next' => [], 'tone' => 'off', 'ui' => 'zruseno'],
            self::FAILED => ['label' => 'Selhalo', 'next' => [self::PENDING_REGISTRATION, self::ACTIVE, self::DELETED], 'tone' => 'hot', 'ui' => 'provisioning'],
            self::DELETED => ['label' => 'Smazáno', 'next' => [], 'tone' => 'off', 'ui' => 'zruseno'],
        ]);
    }

    /** Registry status strings (WAPI domain-info) -> ONhost state. */
    public static function fromRegistryStatus(string $status, ?\DateTimeInterface $expiresAt): string
    {
        $s = strtolower(trim($status));

        return match (true) {
            str_contains($s, 'transfer') => self::TRANSFER_OUT_PENDING,
            str_contains($s, 'redemption') => self::REDEMPTION,
            str_contains($s, 'expired') || str_contains($s, 'grace') => self::GRACE,
            str_contains($s, 'pending') => self::PENDING_REGISTRY,
            in_array($s, ['active', 'ok', 'registered', ''], true) => ($expiresAt !== null && $expiresAt < new \DateTimeImmutable('now')) ? self::EXPIRED : self::ACTIVE,
            default => self::ACTIVE,
        };
    }
}
