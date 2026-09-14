<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains\Models;

use Onhost\Platform\Eloquent\Model;

/** Evidence of registry/registrar terms acceptance attached to the registration (blueprint §46.2, §45.4). */
final class DomainConsent extends Model
{
    protected static string $idPrefix = 'dcs';

    protected $table = 'domain_consents';

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }

    /** The `rules` structure sent with domain-create identifying who accepted the terms. */
    public function toRegistryRules(): array
    {
        return array_filter(['person' => $this->person, 'email' => null, 'ip' => $this->ip, 'timestamp' => $this->accepted_at?->toIso8601String(), 'version' => $this->document_version], fn ($v) => $v !== null);
    }
}
