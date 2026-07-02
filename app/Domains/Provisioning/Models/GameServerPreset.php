<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pterodactyl egg preset — stores default resource allocation and
 * configuration for a specific game (Minecraft, CS2, Valheim, …).
 *
 * When provisioning a game server service the driver merges the
 * preset's defaults with any per-service overrides stored in
 * Service::$resources.
 *
 * @property int    $id
 * @property string $name
 * @property string $game_slug
 * @property string|null $description
 * @property int    $nest_id
 * @property int    $egg_id
 * @property int    $default_memory_mb
 * @property int    $default_disk_mb
 * @property int    $default_cpu_limit
 * @property int    $default_swap_mb
 * @property int    $default_io_weight
 * @property string|null $docker_image
 * @property string|null $startup
 * @property array<string, string>|null $environment
 * @property bool   $is_active
 * @property int    $sort_order
 */
final class GameServerPreset extends Model
{
    protected $fillable = [
        'name',
        'game_slug',
        'description',
        'nest_id',
        'egg_id',
        'default_memory_mb',
        'default_disk_mb',
        'default_cpu_limit',
        'default_swap_mb',
        'default_io_weight',
        'docker_image',
        'startup',
        'environment',
        'is_active',
        'sort_order',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'environment'        => 'array',
            'is_active'          => 'boolean',
            'nest_id'            => 'integer',
            'egg_id'             => 'integer',
            'default_memory_mb'  => 'integer',
            'default_disk_mb'    => 'integer',
            'default_cpu_limit'  => 'integer',
            'default_swap_mb'    => 'integer',
            'default_io_weight'  => 'integer',
            'sort_order'         => 'integer',
        ];
    }

    /**
     * Returns the preset's resource array suitable for injection into Service::$resources.
     *
     * @return array<string, mixed>
     */
    public function toResourceArray(): array
    {
        $arr = [
            'nest_id'    => $this->nest_id,
            'egg_id'     => $this->egg_id,
            'memory_mb'  => $this->default_memory_mb,
            'disk_mb'    => $this->default_disk_mb,
            'cpu_limit'  => $this->default_cpu_limit,
            'swap_mb'    => $this->default_swap_mb,
            'io_weight'  => $this->default_io_weight,
        ];

        if ($this->docker_image !== null && $this->docker_image !== '') {
            $arr['docker_image'] = $this->docker_image;
        }

        if ($this->startup !== null && $this->startup !== '') {
            $arr['startup'] = $this->startup;
        }

        if (! empty($this->environment)) {
            $arr['environment'] = $this->environment;
        }

        return $arr;
    }
}
