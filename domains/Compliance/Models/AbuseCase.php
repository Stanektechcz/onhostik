<?php

declare(strict_types=1);

namespace Onhost\Domain\Compliance\Models;

use Onhost\Platform\Eloquent\Model;

/** DSA notice-and-action case (Art. 16/17/20): receipt → triage → customer statement of reasons → action/dismissal → appeal. */
final class AbuseCase extends Model
{
    protected static string $idPrefix = 'abu';

    protected $table = 'abuse_cases';

    public const CATEGORIES = ['illegal_content', 'copyright', 'phishing', 'malware', 'spam', 'csam', 'terrorism', 'life_safety', 'other'];

    public const ART18_CATEGORIES = ['csam', 'terrorism', 'life_safety'];

    public const STATES = ['RECEIVED', 'TRIAGED', 'CUSTOMER_NOTIFIED', 'ACTIONED', 'DISMISSED', 'APPEALED', 'CLOSED'];

    protected function casts(): array
    {
        return ['reporter' => 'array', 'evidence' => 'array', 'art18' => 'boolean', 'customer_notified_at' => 'datetime', 'decided_at' => 'datetime', 'appeal_deadline_at' => 'datetime'];
    }
}
