<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Services\Limits\LimitRaises;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;

/**
 * The paid limit raises (owner decision 8, TASK-0022 limit-raise), for the operator.
 *
 *   onhost:limit-raise list [--org=] [--service=]   read-only: every active raise, its number, its billing and whether the panel has it
 *   onhost:limit-raise push {addon} [--apply]        asks the panel again for the numbers of the raised service (a dry run without --apply)
 *
 * A raise whose panel request was refused (a busy service, a frozen provisioning, a panel down) keeps its entitlement and its
 * billing and says `panel: pending`; the doctor names it ("every limit raise is billed or approved"). The push is the same resize
 * the delivery asked for, with every number of the service as it is now — it never changes a number, it only repeats it.
 */
final class LimitRaiseReport extends Command
{
    protected $signature = 'onhost:limit-raise {action=list : list | push} {addon? : the raise to push (its add-on service id)} {--org= : only this organization} {--service= : only the raises of this service} {--apply : push for real (without it: a dry run)}';

    protected $description = 'List the paid limit raises, or push one raise\'s numbers to the panel again (dry run by default)';

    public function handle(LimitRaises $raises): int
    {
        return match ((string) $this->argument('action')) {
            'list' => $this->list(),
            'push' => $this->push($raises),
            default => $this->refuse('Unknown action; use list or push.'),
        };
    }

    private function list(): int
    {
        $query = Service::query()->where('family', 'addon')->where('product_key', LimitRaises::PRODUCT)->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])->orderBy('created_at');
        if ($this->option('org')) {
            $query->where('organization_id', (string) $this->option('org'));
        }
        if ($this->option('service')) {
            $query->where('tags->parent_service_id', (string) $this->option('service'));
        }
        $rows = [];
        foreach ($query->get() as $addon) {
            if (data_get($addon->tags, 'addon.revoked_at') !== null) {
                continue;
            }
            $subscription = Subscription::query()->where('service_id', $addon->id)->first();
            $item = $addon->order_item_id !== null ? OrderItem::query()->find($addon->order_item_id) : null;
            $delta = (array) data_get($addon->tags, 'addon.delta', []);
            $rows[] = [
                $addon->id, (string) data_get($addon->tags, 'parent_service_id', ''), implode(', ', array_map(fn ($k, $v) => "{$k} +{$v}", array_keys($delta), $delta)),
                $subscription === null ? 'none' : $subscription->state.' '.number_format($subscription->amount_minor / 100, 2, ',', ' ').' '.$subscription->currency.'/'.$subscription->period.($subscription->cancel_at_period_end ? ' (ends)' : ''),
                data_get($item?->config, 'limit_raise.waived') !== null ? 'free: '.implode(', ', (array) data_get($item?->config, 'limit_raise.waived.approval_ids', [])) : 'paid',
                (string) data_get($addon->tags, 'addon.panel.state', '—'),
            ];
        }
        if ($rows === []) {
            $this->info('No active limit raise.');

            return self::SUCCESS;
        }
        $this->table(['raise', 'service', 'number', 'subscription', 'price', 'panel'], $rows);

        return self::SUCCESS;
    }

    private function push(LimitRaises $raises): int
    {
        $addon = Service::query()->find((string) $this->argument('addon'));
        if ($addon === null || ! LimitRaises::isRaise($addon) || data_get($addon->tags, 'addon.revoked_at') !== null || ! in_array($addon->state, [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED], true)) {
            return $this->refuse('Not an active limit raise: '.(string) $this->argument('addon'));
        }
        $parent = Service::query()->find((string) data_get($addon->tags, 'parent_service_id', ''));
        if ($parent === null) {
            return $this->refuse('The raised service is gone.');
        }
        $this->line("Raise {$addon->id} of {$parent->id} ({$parent->state}); panel: ".(string) data_get($addon->tags, 'addon.panel.state', '—').' '.(string) data_get($addon->tags, 'addon.panel.error', ''));
        $this->line('Would ask the panel for: '.json_encode((array) $parent->entitlements, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if (! $this->option('apply')) {
            $this->info('Dry run: nothing was sent. Run again with --apply to push.');

            return self::SUCCESS;
        }
        $operation = $raises->pushPanel($parent, $addon, 'limit-raise-push:'.$addon->id.':'.now()->format('YmdHi'));
        if ($operation === null) {
            return $this->refuse('Refused: '.(string) data_get($addon->fresh()?->tags, 'addon.panel.error', ''));
        }
        $this->info("Resize requested: operation {$operation->id} ({$operation->state}).");

        return self::SUCCESS;
    }

    private function refuse(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
