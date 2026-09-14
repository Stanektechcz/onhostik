<?php

declare(strict_types=1);

namespace Onhost\Platform\Files;

use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * What happens to an upload the scanner did not pass (audit §5r-4): an infected file is audited and reported to
 * security (`files.infected`), an unscanned one (clamd down while the scan is enforced) is refused with a retry hint.
 */
final class UploadGuard
{
    /** @param array{result:string, signature:?string, at:string} $scan */
    public static function refused(array $scan, string $subjectType, string $subjectId, string $name, CommandContext $context): never
    {
        if ($scan['result'] === VirusScanner::INFECTED) {
            app(AuditRecorder::class)->record($context, 'files.upload.infected', 'denied', ['subject' => $subjectType, 'name' => mb_substr($name, 0, 120), 'signature' => $scan['signature']], $subjectType, $subjectId);
            app(OutboxPublisher::class)->publish(GenericEvent::of('files.infected', $subjectType, $subjectId, ['name' => mb_substr($name, 0, 120), 'signature' => $scan['signature'], 'subject' => $subjectType, 'actor' => $context->actorId], $context->organizationId));

            throw new DomainError('upload_infected', 'The file contains malware ('.$scan['signature'].') and was deleted.', 422, ['field' => 'file', 'signature' => $scan['signature']]);
        }

        throw new DomainError('upload_scan_unavailable', 'The virus scanner is not reachable right now; try the upload again in a few minutes.', 503, ['field' => 'file', 'retryable' => true]);
    }
}
