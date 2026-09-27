<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Identity\Models\PersonalAccessToken;
use Onhost\Domain\Identity\Models\User;

/**
 * TASK-0039 (permission program P0-09, IF-5): the API tokens whose behaviour the token principal view changes, for the notice
 * the owners get before the release (program §2.11 — existing customers are never changed en masse silently).
 *
 *  1. tokens bound to NO organization (older rows, CLI-made): they keep their person's organization bindings until the operator
 *     switches `onhost.token_organization_required` on — after this list and the notice; then they are refused (`token_unbound`);
 *  2. tokens bound to one organization that were USED on another in the last `--days` (audit rows carry `token:<id>` as the
 *     session): from this release on such a request is refused (`token_organization_mismatch`) — the people to tell.
 *
 * Read-only in every mode: it prints, it never revokes. `--dry-run` is accepted for the runbook's uniform syntax.
 */
final class TokensUnbound extends Command
{
    protected $signature = 'operator:tokens:unbound {--dry-run : list only (the only mode: the refusal is the switch ONHOST_TOKEN_ORGANIZATION_REQUIRED)} {--days=90 : how far back to look for tokens used on another organization}';

    protected $description = 'List API tokens bound to no organization, and tokens used on another organization than their own (read-only)';

    public function handle(): int
    {
        $unbound = PersonalAccessToken::query()->whereNull('organization_id')->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->orderBy('id')->get();
        $people = User::query()->whereIn('id', $unbound->pluck('tokenable_id')->all())->pluck('email', 'id');
        $this->line('Tokens bound to no organization (refused once ONHOST_TOKEN_ORGANIZATION_REQUIRED=true)');
        $this->table(['token', 'name', 'person', 'e-mail', 'last used', 'expires'], $unbound->map(fn (PersonalAccessToken $t) => [
            (string) $t->getKey(), (string) $t->name, (string) $t->tokenable_id,
            (string) ($people[(string) $t->tokenable_id] ?? '—'), $t->last_used_at?->toDateTimeString() ?? '—', $t->expires_at?->toDateString() ?? 'never',
        ])->all());

        $crossed = $this->usedElsewhere(max(1, (int) $this->option('days')));
        $this->line('Tokens used on another organization than their own (refused from this release: token_organization_mismatch)');
        $this->table(['token', 'own organization', 'used on', 'requests', 'last', 'e-mail'], $crossed);

        $this->line(sprintf('Read-only: %d unbound tokens, %d tokens used across organizations. Nothing was changed.', $unbound->count(), count($crossed)));

        return self::SUCCESS;
    }

    /** @return list<list<string>> */
    private function usedElsewhere(int $days): array
    {
        $rows = DB::table('audit_events')->where('session_id', 'like', 'token:%')->whereNotNull('organization_id')->where('created_at', '>=', now()->subDays($days))
            ->select('session_id', 'organization_id', DB::raw('count(*) as requests'), DB::raw('max(created_at) as last_at'))->groupBy('session_id', 'organization_id')->get();
        $ids = $rows->map(fn ($r) => substr((string) $r->session_id, 6))->filter(fn (string $id) => ctype_digit($id))->unique()->values()->all();
        $tokens = PersonalAccessToken::query()->whereIn('id', $ids)->whereNotNull('organization_id')->get()->keyBy(fn (PersonalAccessToken $t) => (string) $t->getKey());
        $people = User::query()->whereIn('id', $tokens->pluck('tokenable_id')->all())->pluck('email', 'id');
        $out = [];
        foreach ($rows as $row) {
            $token = $tokens->get(substr((string) $row->session_id, 6));
            if ($token === null || (string) $token->organization_id === (string) $row->organization_id) {
                continue;
            }
            $out[] = [(string) $token->getKey(), (string) $token->organization_id, (string) $row->organization_id, (string) $row->requests, (string) $row->last_at, (string) ($people[(string) $token->tokenable_id] ?? '—')];
        }

        return $out;
    }
}
