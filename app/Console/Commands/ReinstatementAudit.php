<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Onhost\Domain\Billing\Models\DunningCase;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Billing\SubscriptionService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\SuspensionHold;
use Onhost\Platform\Commands\CommandContext;

/**
 * Who pay and restore (TASK-0025) concerns before the owner switches it on — read-only unless one service is named:
 *  (a) cancellations taken back whose subscription stayed CANCELLED: the service runs and nobody bills it;
 *  (b) services in their restore window whose dunning invoice is already paid (paid, and still going to be removed);
 *  (c) services in their restore window held for payment (what the customer is told they can pay to restore);
 *  (d) cancellations taken back whose carried sites were purged meanwhile (only an archive restore through support helps).
 * `--apply --service=<id>` starts the billing of exactly one service of list (a) again from today — never in bulk, never
 * billed back for the time it ran free.
 */
final class ReinstatementAudit extends Command
{
    protected $signature = 'onhost:billing:reinstatement-audit {--service= : with --apply: the one service of the unbilled list to bill again} {--apply : restart the billing of that one service} {--dry-run : list only (the default)}';

    protected $description = 'List services pay and restore concerns (unbilled undone cancellations, paid services awaiting removal, payment holds, lost carried sites); --apply restarts the billing of one';

    private const LIMIT = 500;

    public function handle(SubscriptionService $subscriptions): int
    {
        $leaks = $this->unbilled();
        if ((bool) $this->option('apply')) {
            return $this->apply($leaks, $subscriptions);
        }
        $this->section('(a) obnovené zrušení, předplatné zůstalo zrušené — služba běží bez účtování', $leaks->map(fn (Service $s) => [$s->id, $s->organization_id, $s->name, $s->state, (string) data_get($s->tags, 'deletion_cancelled.cancelled_at', '')])->all(), ['služba', 'organizace', 'název', 'stav', 'zrušení odvoláno']);
        $this->section('(b) v lhůtě na obnovu, dlužná faktura už zaplacená', $this->paidButRemoved(), ['služba', 'organizace', 'název', 'faktura', 'lhůta do']);
        $this->section('(c) v lhůtě na obnovu, blokace kvůli platbě', $this->heldForPayment(), ['služba', 'organizace', 'název', 'důvod', 'lhůta do']);
        $this->section('(d) obnovené zrušení, přidružené weby mezitím odstraněné', $this->lostSites(), ['služba', 'organizace', 'název', 'odstraněné weby']);
        $this->line('Nic nebylo změněno. Účtování jedné služby ze seznamu (a) obnovíte: --apply --service=<id>');

        return self::SUCCESS;
    }

    /** @param Collection<int, Service> $leaks */
    private function apply($leaks, SubscriptionService $subscriptions): int
    {
        $id = trim((string) ($this->option('service') ?? ''));
        if ($id === '') {
            $this->error('--apply needs --service=<id>: the billing is restarted one service at a time, never in bulk');

            return self::FAILURE;
        }
        $service = $leaks->first(fn (Service $s) => $s->id === $id);
        if ($service === null) {
            $this->error("{$id} is not on the list of undone cancellations running unbilled; nothing changed");

            return self::FAILURE;
        }
        $outcome = $subscriptions->restartAfterRestore($service, CommandContext::system('cli:billing:reinstatement-audit')->withScope($service->organization_id));
        $this->info("{$service->id}: billing restarted ({$outcome}); nothing was billed for the time it ran free");
        // auto-renew is never switched on by a restore (TASK-0027): it stays what the customer left it
        if (! (bool) Subscription::query()->where('service_id', $service->id)->where('state', Subscription::ACTIVE)->value('auto_renew')) {
            $this->warn("{$service->id}: auto-renew stays off as the customer left it — the service ends at the next renewal pass unless it is paid for or the owner or billing admin switches auto-renew on");
        }

        return self::SUCCESS;
    }

    /** @return Collection<int, Service> */
    private function unbilled()
    {
        $ids = Subscription::query()->where('state', Subscription::CANCELLED)->whereNotNull('service_id')->limit(self::LIMIT * 4)->pluck('service_id')->all();

        return Service::query()->whereIn('id', $ids)->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])->whereNull('terminate_at')->orderBy('created_at')->get()
            ->filter(fn (Service $s) => $s->family !== 'addon' && data_get($s->tags, 'billing') !== 'included' && is_array(data_get($s->tags, 'deletion_cancelled'))
                && ! Subscription::query()->where('service_id', $s->id)->where('state', '!=', Subscription::CANCELLED)->exists())
            ->take(self::LIMIT)->values();
    }

    /** @return list<list<string>> */
    private function paidButRemoved(): array
    {
        $rows = [];
        $cases = DunningCase::query()->where('state', DunningCase::TERMINATED)->whereNotNull('invoice_id')->whereNotNull('service_id')->limit(self::LIMIT)->get();
        foreach ($cases as $case) {
            $service = $this->inWindow((string) $case->service_id);
            $invoice = Invoice::query()->find($case->invoice_id);
            if ($service !== null && $invoice !== null && $invoice->state === Invoice::PAID) {
                $rows[] = [$service->id, $service->organization_id, (string) $service->name, (string) $invoice->number, $service->terminate_at?->format('Y-m-d') ?? ''];
            }
        }

        return $rows;
    }

    /** @return list<list<string>> */
    private function heldForPayment(): array
    {
        return Service::query()->whereNotNull('terminate_at')->where('terminate_at', '>', now())->where('state', ServiceStateMachine::SUSPENDED)->orderBy('terminate_at')->limit(self::LIMIT)->get()
            ->filter(fn (Service $s) => in_array(SuspensionHold::PAYMENT, SuspensionHold::holds($s), true))
            ->map(fn (Service $s) => [$s->id, $s->organization_id, (string) $s->name, (string) $s->suspended_reason, $s->terminate_at?->format('Y-m-d') ?? ''])->values()->all();
    }

    /** @return list<list<string>> */
    private function lostSites(): array
    {
        $rows = [];
        $sites = Service::withTrashed()->where('state', ServiceStateMachine::TERMINATED)->whereNotNull('tags')->orderByDesc('updated_at')->limit(self::LIMIT * 2)->get()
            ->filter(fn (Service $s) => (string) data_get($s->tags, 'included.ended_by', '') !== '');
        foreach ($sites->groupBy(fn (Service $s) => (string) data_get($s->tags, 'included.ended_by')) as $parentId => $lost) {
            $parent = Service::query()->find((string) $parentId);
            if ($parent !== null && $parent->terminate_at === null && is_array(data_get($parent->tags, 'deletion_cancelled'))) {
                $rows[] = [$parent->id, $parent->organization_id, (string) $parent->name, $lost->map(fn (Service $s) => (string) ($s->hostname ?: $s->name))->implode(', ')];
            }
        }

        return $rows;
    }

    private function inWindow(string $serviceId): ?Service
    {
        $service = Service::query()->find($serviceId);

        return $service !== null && $service->terminate_at !== null && $service->terminate_at->isFuture() && $service->state === ServiceStateMachine::SUSPENDED ? $service : null;
    }

    /**
     * @param  list<list<string>>  $rows
     * @param  list<string>  $headers
     */
    private function section(string $title, array $rows, array $headers): void
    {
        $this->line('');
        $this->line($title.': '.count($rows));
        if ($rows !== []) {
            $this->table($headers, $rows);
        }
    }
}
