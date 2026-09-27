<?php

declare(strict_types=1);

namespace Onhost\Providers\Pterodactyl;

/**
 * Whose panel user a Pterodactyl account is (TASK-0033, permission program IF-6 / D12, exploit PA-01).
 *
 * The platform creates one panel user per organization with the raw organization id as `external_id`. A user belongs to
 * an organization only when the panel's own answer carries exactly that id: the panel compares external ids under a
 * case- and trailing-space-insensitive MySQL collation, an e-mail is whatever a customer typed, and a panel
 * administrator is never a customer account. Anything else is refused and left to an operator (`onhost:game:panel-identity`).
 */
final class PanelIdentity
{
    /** The panel user carries exactly the organization's id. */
    public const OWNED = 'owned';

    /** Another organization's id, or a spelling of this one the platform never wrote (a partial match). */
    public const FOREIGN = 'foreign';

    /** No external id at all: a user the platform did not create (legacy, hand-made, found by e-mail once). */
    public const UNMARKED = 'unmarked';

    /** A panel administrator: its password and collaborators are never customer business. */
    public const ADMINISTRATOR = 'administrator';

    /** The panel does not know the user. */
    public const MISSING = 'missing';

    /**
     * The panel names another owner of the server than the platform recorded (moved by hand on the panel, or changed
     * there): never managed until an operator makes both agree (review round 1 of TASK-0033).
     */
    public const MOVED = 'moved';

    public function __construct(
        public readonly string $verdict,
        public readonly string $userId,
        public readonly ?string $externalId,
        /** the owner the platform's binding recorded, set only when it differs from the panel's ($userId) */
        public readonly ?string $recordedUserId = null,
    ) {}

    /** The live owner's identity when the platform recorded another owner: refused whatever the live user is. */
    public function movedFrom(string $recordedUserId): self
    {
        return new self(self::MOVED, $this->userId, $this->externalId, $recordedUserId);
    }

    /** @param array<string, mixed>|null $attributes the panel's user attributes, null when the panel has no such user */
    public static function judge(?array $attributes, string $organizationId, string $userId = ''): self
    {
        if ($attributes === null) {
            return new self(self::MISSING, $userId, null);
        }
        $userId = (string) ($attributes['id'] ?? $userId);
        $raw = $attributes['external_id'] ?? null;
        $externalId = is_scalar($raw) && (string) $raw !== '' ? (string) $raw : null;
        if (! empty($attributes['root_admin'])) {
            return new self(self::ADMINISTRATOR, $userId, $externalId);
        }
        if ($externalId === null) {
            return new self(self::UNMARKED, $userId, null);
        }

        // byte for byte: `ORG_…`, `org_… ` or any other spelling the panel's collation lets through is not this organization
        return new self($organizationId !== '' && $externalId === $organizationId ? self::OWNED : self::FOREIGN, $userId, $externalId);
    }

    public function owned(): bool
    {
        return $this->verdict === self::OWNED;
    }
}
