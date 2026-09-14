<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Mail;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;

/**
 * Mailbox password rotation without a plaintext password anywhere (audit §5i-5): the account owner asks for a
 * one-time link for a mailbox, hands it to the person who uses that mailbox, and that person sets the password on a
 * plain page. The link is signed, lives a day, works once, and the password goes straight into the ordinary
 * `mailbox.update` action (audited, never logged).
 */
final class MailboxPasswordLinks
{
    public const TTL_SECONDS = 86400;

    public function __construct(private readonly CacheRepository $cache, private readonly ServiceService $services, private readonly AuditRecorder $audit) {}

    /** @return array{url:string, expires_at:string, mailbox:string} */
    public function create(Service $service, string $remoteId, string $address, CommandContext $context): array
    {
        if ($service->family !== 'mail' || ! preg_match('/^[A-Za-z0-9:_.-]{1,120}$/', $remoteId)) {
            throw new DomainError('mailbox_link_invalid', 'A password link needs a mailbox of a mail service.', 422, ['field' => 'remote_id']);
        }
        $token = Str::lower(Str::random(40));
        $expires = now()->addSeconds(self::TTL_SECONDS);
        $this->cache->put(self::key($token), ['service_id' => $service->id, 'organization_id' => $service->organization_id, 'remote_id' => $remoteId, 'address' => $address, 'issued_by' => $context->actorId], self::TTL_SECONDS);
        $this->audit->record($context->withScope($service->organization_id, $service->project_id), 'mailbox.password_link', 'succeeded', ['remote_id' => $remoteId, 'address' => $address, 'expires_at' => $expires->toIso8601String()], 'service', $service->id);

        return ['url' => URL::temporarySignedRoute('mailbox.password', $expires, ['token' => $token]), 'expires_at' => $expires->toIso8601String(), 'mailbox' => $address];
    }

    /** @return array{service_id:string, organization_id:string, remote_id:string, address:string}|null */
    public function peek(string $token): ?array
    {
        $row = $this->cache->get(self::key($token));

        return is_array($row) ? $row : null;
    }

    /** Sets the password through the ordinary action and burns the link; the operation is returned for the confirmation page. */
    public function redeem(string $token, string $password): Operation
    {
        $row = $this->peek($token);
        if ($row === null) {
            throw new DomainError('mailbox_link_expired', 'This link has expired or was already used.', 410);
        }
        if (strlen($password) < 12 || strlen($password) > 72) {
            throw new DomainError('action_param_invalid', 'The password must have 12–72 characters.', 422, ['field' => 'password']);
        }
        $service = Service::query()->find((string) $row['service_id']);
        if ($service === null) {
            $this->cache->forget(self::key($token));
            throw new DomainError('mailbox_link_expired', 'The mail service no longer exists.', 410);
        }
        $operation = $this->services->requestAction($service, 'mailbox.update', CommandContext::system('mailbox-password-link'), 'mbpw:'.$token, ['remote_id' => (string) $row['remote_id'], 'password' => $password]);
        $this->cache->forget(self::key($token));

        return $operation;
    }

    private static function key(string $token): string
    {
        return 'onhost:mbpw:'.hash('sha256', $token);
    }
}
