<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Onhost\Platform\Eloquent\Model;

/**
 * A customer's own installation image (TASK-0110, owner decision G-R5): kept for the organization, attached to at most one of
 * its servers at a time as a CD-ROM. See `Onhost\Domain\Services\CustomIso\CustomIsoLibrary`.
 *
 * @property string $id
 * @property string $organization_id
 * @property ?string $project_id
 * @property ?string $uploaded_via_service_id
 * @property ?string $uploaded_by
 * @property string $name
 * @property string $path
 * @property int $size_bytes
 * @property string $sha256
 * @property string $scan_result
 * @property ?Carbon $scanned_at
 * @property string $state
 * @property ?string $attached_service_id
 * @property ?Carbon $attached_at
 * @property ?array<string,mixed> $previous_boot
 * @property ?list<array{instance:string,node:string,volume:string}> $node_copies
 * @property ?Carbon $deleted_at
 * @property ?Carbon $created_at
 */
final class CustomIso extends Model
{
    /** An upload in flight: its bytes are reserved against the organization's quota, it is not an image yet (review H1). */
    public const STAGING = 'staging';

    public const READY = 'ready';

    public const DELETED = 'deleted';

    protected static string $idPrefix = 'iso';

    protected $table = 'custom_isos';

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer', 'scanned_at' => 'datetime', 'attached_at' => 'datetime', 'deleted_at' => 'datetime',
            'previous_boot' => 'array', 'node_copies' => 'array',
        ];
    }

    /**
     * The images a server reaches: its organization's, of its own project (an image belongs to the project of the server it was
     * uploaded through — a role on another project of the organization neither sees nor deletes it).
     *
     * @return Builder<self>
     */
    public static function reachableFrom(Service $service): Builder
    {
        $query = self::query()->where('organization_id', $service->organization_id)->where('state', self::READY);

        return $service->project_id === null ? $query->whereNull('project_id') : $query->where('project_id', $service->project_id);
    }

    /** The file name a hypervisor storage holds it under: the platform's id, never the customer's name. */
    public function remoteFilename(): string
    {
        return 'onhost-ciso-'.$this->id.'.iso';
    }
}
