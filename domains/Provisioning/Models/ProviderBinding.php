<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Models;

use Onhost\Platform\Eloquent\Model;
use Onhost\Providers\Contracts\ResourceRef;

/** Maps an ONhost service to a remote object; `remote_id` is never a public identifier. */
final class ProviderBinding extends Model
{
    protected static string $idPrefix = 'pb';

    protected $table = 'provider_bindings';

    protected function casts(): array
    {
        return ['ownership' => 'array', 'meta' => 'array', 'last_reconciled_at' => 'datetime'];
    }

    public function ref(): ResourceRef
    {
        return new ResourceRef($this->remote_type, $this->remote_id, $this->remote_node, $this->meta ?? [], $this->service_id);
    }
}
