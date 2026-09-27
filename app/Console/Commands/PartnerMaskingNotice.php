<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Partners\Commands\PartnerCommand;
use Onhost\Domain\Partners\Models\Partner;
use Onhost\Domain\Partners\PartnerService;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;

/**
 * Tells every active partner, once, that the portal no longer shows client contacts and who is overdue (TASK-0040,
 * permission program D13, §10 O9 — "mask now and notify partners the same day"). The masking itself is on from the deploy
 * (the disclosure had no DPA behind it); this is the notice. The dry run (the default) lists who would hear it; `--send`
 * publishes `partner.client_data.masked` per partner through the command bus. A partner told already is skipped.
 */
final class PartnerMaskingNotice extends Command
{
    protected $signature = 'onhost:partners:masking-notice
        {--send : tell the listed partners (in the portal and by the mandatory notice mail)}
        {--dry-run : list only (the default)}';

    protected $description = 'Tell active partners once that client contacts and dunning are masked in the partner portal (TASK-0040, program §10 O9)';

    public function handle(PartnerService $partners, CommandBus $bus): int
    {
        $due = Partner::query()->where('state', 'active')->orderBy('created_at')->get()->reject(fn (Partner $p) => $partners->maskingNoticed($p))->values();
        $this->line($due->count().' active partner(s) not told yet.');
        if ($due->isNotEmpty()) {
            $this->table(['partner', 'code', 'organization'], $due->map(fn (Partner $p) => [$p->id, $p->code, $p->organization_id])->all());
        }
        if (! (bool) $this->option('send')) {
            $this->info('Nothing was sent. Tell them with --send (the same day the masking goes live).');

            return self::SUCCESS;
        }
        $failed = 0;
        foreach ($due as $partner) {
            try {
                $bus->dispatch(new PartnerCommand('partner.masking.notice:'.$partner->id, ['op' => 'masking.notice', 'partner_id' => $partner->id]), CommandContext::system('cli:partners:masking-notice'));
            } catch (DomainError $e) {
                $failed++;
                $this->error("{$partner->code}: not told — {$e->getMessage()}");
            }
        }
        $this->info(($due->count() - $failed).' partner(s) told.');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
