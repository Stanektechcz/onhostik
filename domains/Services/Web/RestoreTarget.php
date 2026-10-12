<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\VerifiedRestoreRun;
use Onhost\Platform\Commands\CommandContext;

/**
 * Where a verified restore (W2-4) puts a backup: an isolated copy of the site, made for one run and removed after it — never the
 * live site. `StagingRestoreTarget` builds it with the staging facility on the production node; tests bind a double.
 *
 * Every method answers with facts the protocol can carry; none throws for an expected refusal — it says so in its answer, so a
 * failed step still reaches the cleanup and the protocol.
 */
interface RestoreTarget
{
    /**
     * Start making the copy. @return array{service_id:?string, domain:?string, error:?string}
     */
    public function prepare(Service $production, VerifiedRestoreRun $run, CommandContext $context): array;

    /** true when the copy is ready, false when making it failed for good, null while it is still being made. */
    public function ready(VerifiedRestoreRun $run): ?bool;

    /**
     * Restore the set into the copy and keep the copy from acting on the world (mail, payments, webhooks, cron) as far as the
     * platform can. Secrets used on the way (the copy's database passwords) stay in memory and are never returned.
     *
     * @return array{
     *   files: array{status:string, detail:string},
     *   databases: array{status:string, detail:string, rows:list<array<string,mixed>>},
     *   created: list<array{remote_id:string, name:string}>,
     *   suppression: array{applied:list<string>, not_applied:list<string>},
     *   probe_allowed: bool, probe_refusal: string, wordpress: bool
     * }
     */
    public function restore(VerifiedRestoreRun $run, Service $production, Backup $backup): array;

    /** One HTTP request to the copy. @return array{status:?int, detail:string} */
    public function probe(VerifiedRestoreRun $run): array;

    /**
     * A login with the test identity the customer configured; never called without one.
     *
     * @param  array{user:string, password:string}  $identity
     * @return array{status:string, detail:string}
     */
    public function login(VerifiedRestoreRun $run, array $identity): array;

    /** Ask for the copy and its databases to be removed. @return array{requested:bool, detail:string} */
    public function cleanup(VerifiedRestoreRun $run): array;

    /** true when nothing of the copy is left, false when something definitely is, null while the removal is still running. */
    public function cleanedUp(VerifiedRestoreRun $run): ?bool;
}
