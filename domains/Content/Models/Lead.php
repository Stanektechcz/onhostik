<?php

declare(strict_types=1);

namespace Onhost\Domain\Content\Models;

use Onhost\Platform\Eloquent\Model;

/** Inbound request from a public form: migration, consultation, enterprise, contact, tender, reseller. */
final class Lead extends Model
{
    protected static string $idPrefix = 'lead';

    protected $table = 'leads';

    public const KINDS = ['migration', 'consultation', 'enterprise', 'contact', 'tender', 'reseller'];

    public const STATES = ['new', 'contacted', 'qualified', 'won', 'lost'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }
}
