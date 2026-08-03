<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A git repository connected to a hosting service.
 *
 * @property int $id
 * @property int $service_id
 * @property string $provider
 * @property string $repository_url
 * @property string $branch
 * @property string|null $deploy_path
 * @property bool $auto_deploy
 * @property string|null $webhook_token
 * @property string|null $deploy_key_private
 * @property string|null $deploy_key_public
 * @property string|null $post_deploy_commands
 * @property string $status
 * @property string|null $last_commit
 * @property string|null $last_output
 * @property string|null $last_error
 * @property Carbon|null $last_deployed_at
 */
class ServiceGitRepository extends Model
{
    protected $fillable = [
        'service_id',
        'provider',
        'repository_url',
        'branch',
        'deploy_path',
        'auto_deploy',
        'webhook_token',
        'deploy_key_private',
        'deploy_key_public',
        'post_deploy_commands',
        'status',
        'last_commit',
        'last_output',
        'last_error',
        'last_deployed_at',
    ];

    /** The private deploy key is a credential — never expose it in a payload. */
    protected $hidden = ['deploy_key_private', 'webhook_token'];

    protected function casts(): array
    {
        return [
            'auto_deploy'        => 'boolean',
            'last_deployed_at'   => 'datetime',
            // Encrypted at rest: anyone with DB read access would otherwise
            // hold push access to the customer's repository.
            'deploy_key_private' => 'encrypted',
        ];
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function isDeploying(): bool
    {
        return $this->status === 'deploying';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'deploying' => 'Nasazuje se',
            'success'   => 'Nasazeno',
            'failed'    => 'Chyba',
            default     => 'Připraveno',
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'deploying' => 'warning',
            'success'   => 'success',
            'failed'    => 'danger',
            default     => 'secondary',
        };
    }

    /** Short display form of the repository (owner/name), URL kept intact. */
    public function shortName(): string
    {
        $path = (string) preg_replace('#^(https?://[^/]+/|git@[^:]+:)#', '', $this->repository_url);

        return (string) preg_replace('#\.git$#', '', $path);
    }

    /** @return list<string> */
    public function postDeployCommandList(): array
    {
        if ($this->post_deploy_commands === null || trim($this->post_deploy_commands) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $line): string => trim($line),
            preg_split('/\R/', $this->post_deploy_commands) ?: [],
        )));
    }
}
