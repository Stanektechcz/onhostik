<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Penpot\PaperParentCommand;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;

/**
 * The paper parent of the staging Penpot E2E (J2, TASK-0154; owner's document GENERALKA-Penpot-D-G, steps F2 and G1): one
 * service row of a sandbox organisation the Penpot can be ordered for, without anything behind it. Never in production.
 */
final class PenpotPaperParent extends Command
{
    protected $signature = 'onhost:penpot:paper-parent
        {organization : the sandbox organisation (slug)}
        {--product=web-hosting : a web/managed product key}
        {--plan=start : the plan key of that product}
        {--end= : end this paper parent (service id) instead of creating one}
        {--reason= : why (kept in the audit)}';

    protected $description = 'Staging only: create (or end) the sandbox "paper parent" service of the Penpot E2E test — no panel, no money';

    public function handle(CommandBus $bus): int
    {
        $organization = Organization::query()->where('slug', (string) $this->argument('organization'))->first();
        if ($organization === null) {
            $this->error('organization_not_found: no organisation with this slug');

            return self::FAILURE;
        }
        $end = $this->option('end');
        $payload = is_string($end) && $end !== ''
            ? ['op' => 'end', 'organization_id' => $organization->id, 'service_id' => $end]
            : ['op' => 'create', 'organization_id' => $organization->id, 'product_key' => (string) $this->option('product'), 'plan' => (string) $this->option('plan')];
        $payload['reason'] = Str::limit((string) $this->option('reason'), 200, '');

        try {
            $result = $bus->dispatch(new PaperParentCommand('penpot-paper-parent:'.$payload['op'].':'.$organization->id.':'.Str::ulid(), $payload), CommandContext::system('cli:penpot:paper-parent')->withScope($organization->id));
        } catch (DomainError $e) {
            $this->error("{$e->error}: {$e->getMessage()}");

            return self::FAILURE;
        }
        $this->line(sprintf('%s %s%s', $result['service_id'], $result['state'], isset($result['created']) ? ($result['created'] ? ' (created)' : ' (already there)') : ''));

        return self::SUCCESS;
    }
}
