<?php

declare(strict_types=1);

namespace App\Domains\Marketplace\Models;

use App\Domains\Provisioning\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $service_id
 * @property int $marketplace_app_id
 * @property string $status
 * @property string|null $version
 * @property int|null $price_halere_paid
 * @property Carbon|null $installed_at
 */
class AppInstallation extends Model
{
    protected $fillable = [
        'service_id',
        'marketplace_app_id',
        'status',
        'version',
        'price_halere_paid',
        'installed_at',
    ];

    protected function casts(): array
    {
        return [
            'price_halere_paid' => 'integer',
            'installed_at'      => 'datetime',
        ];
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<MarketplaceApp, $this> */
    public function marketplaceApp(): BelongsTo
    {
        return $this->belongsTo(MarketplaceApp::class);
    }

    public function isInstalled(): bool
    {
        return $this->status === 'installed';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'installing' => 'Instaluje se',
            'installed'  => 'Nainstalováno',
            'failed'     => 'Chyba',
            'removed'    => 'Odstraněno',
            default      => 'Čeká',
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'installed'  => 'success',
            'installing' => 'warning',
            'failed'     => 'danger',
            'removed'    => 'secondary',
            default      => 'info',
        };
    }
}
