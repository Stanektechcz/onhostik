<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;

/**
 * Staff READING a customer's data is an event too. Every write goes through the command bus and leaves a trail; a look at
 * a customer's account — the members, the credit, the documents, a ticket's conversation, the mail queue — left none, so
 * "who opened this customer's account last week" had no answer, for us or for the customer.
 *
 * One event per person and thing per quarter of an hour (a console that refreshes itself does not flood the trail). An
 * event scoped to an organization is part of THAT organization's audit trail: the customer sees that support opened
 * their account, when, and from which screen — the same way they see what support changed.
 */
final class StaffReadAudit
{
    private const WINDOW_SECONDS = 900;

    public function __construct(private readonly AuditRecorder $audit, private readonly CacheRepository $cache) {}

    /** @param array<string,mixed> $detail */
    public function record(Request $request, CommandContext $context, string $what, ?string $organizationId = null, ?string $resourceType = null, ?string $resourceId = null, array $detail = []): void
    {
        if ($context->actorId === null) {
            return;
        }
        $key = 'onhost:staff-read:'.sha1(implode('|', [$context->actorId, $what, (string) $organizationId, (string) $resourceType, (string) $resourceId]));
        if (! $this->cache->add($key, 1, self::WINDOW_SECONDS)) {
            return;
        }
        $this->audit->record($organizationId !== null ? $context->withScope($organizationId) : $context, 'staff.read.'.$what, 'succeeded', $detail + ['screen' => mb_substr($request->path(), 0, 120)], $resourceType, $resourceId);
    }
}
