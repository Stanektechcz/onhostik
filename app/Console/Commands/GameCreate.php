<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Commands\ProvisioningCommand;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;

/**
 * Staff quick action from the terminal (audit §5o): a game server for a customer without an order — the same path the
 * console's "Založit herní server" takes (`service.create` through the command bus), so the audit trail and the
 * provisioning queue are the usual ones. Example: `onhost:game:create demo@onhost.cz --egg=minecraft-spigot --game-version=1.21.8`.
 */
final class GameCreate extends Command
{
    protected $signature = 'onhost:game:create {organization : organization id, slug or an owner e-mail} {--plan=game-8 : catalogue plan key} {--egg=minecraft-spigot : template key mapped on the game panel} {--game-version= : game version, e.g. 1.21.8 (MINECRAFT_VERSION); --version is taken by artisan itself} {--label= : the customer-facing label} {--region=cz1} {--product=game}';

    protected $description = 'Create and provision a game server for an organization (staff quick action, audit §5o)';

    public function handle(CommandBus $bus): int
    {
        $needle = (string) $this->argument('organization');
        $organization = Organization::query()->whereKey($needle)->orWhere('slug', $needle)->first();
        if ($organization === null && str_contains($needle, '@')) {
            $user = User::query()->where('email', strtolower($needle))->first();
            $organization = $user !== null ? Organization::query()->where('owner_user_id', $user->id)->orderBy('created_at')->first() : null;
        }
        if ($organization === null) {
            $this->error("Organization not found: {$needle}");

            return self::FAILURE;
        }
        $config = array_filter(['egg' => (string) $this->option('egg'), 'version' => (string) $this->option('game-version'), 'label' => (string) $this->option('label'), 'region' => (string) $this->option('region')], fn ($v) => $v !== '');
        try {
            $result = $bus->dispatch(new ProvisioningCommand('game.create:'.$organization->id.':'.now()->format('U.u'), ['op' => 'service.create', 'organization_id' => $organization->id, 'product_key' => (string) $this->option('product'), 'plan_key' => (string) $this->option('plan'), 'config' => $config]), CommandContext::system('game.create'));
        } catch (DomainError $e) {
            $this->error($e->error.': '.$e->getMessage());

            return self::FAILURE;
        }
        $service = (array) ($result['service'] ?? []);
        $this->info("service {$service['id']} ({$service['name']}) for {$organization->name} · state {$service['state']} · operation {$result['operation_id']}");
        $this->line('Watch it: php artisan onhost:provisioning:tick · staff console → Herní panel → Provisioning fronta');

        return self::SUCCESS;
    }
}
