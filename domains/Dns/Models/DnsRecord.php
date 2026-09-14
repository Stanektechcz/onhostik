<?php

declare(strict_types=1);

namespace Onhost\Domain\Dns\Models;

use Onhost\Platform\Eloquent\Model;

final class DnsRecord extends Model
{
    protected static string $idPrefix = 'rr';

    protected $table = 'dns_records';

    protected function casts(): array
    {
        return ['ttl' => 'integer', 'prio' => 'integer', 'protected' => 'boolean'];
    }

    /** @return array{name:string,type:string,content:string,ttl:int,prio:int|null} */
    public function normalized(): array
    {
        return ['name' => $this->name, 'type' => $this->type, 'content' => $this->content, 'ttl' => (int) $this->ttl, 'prio' => $this->prio === null ? null : (int) $this->prio];
    }
}
