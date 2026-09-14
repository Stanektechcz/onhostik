<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Onhost\Platform\Eloquent\Model;

/** Mail domain projection (ISPConfig mail executor). */
final class MailDomain extends Model
{
    protected static string $idPrefix = 'md';

    protected $table = 'mail_domains';

    protected function casts(): array
    {
        return ['remote_client_id' => 'integer', 'remote_id' => 'integer', 'sending_enabled' => 'boolean'];
    }

    public function mailboxes(): HasMany
    {
        return $this->hasMany(Mailbox::class, 'mail_domain_id');
    }
}
