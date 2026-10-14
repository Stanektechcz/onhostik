<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Advisories\AdvisoryCases;
use Onhost\Domain\Advisories\Commands\AdvisoryCommand;
use Onhost\Domain\Advisories\Models\Advisory;
use Onhost\Domain\Advisories\Models\AdvisoryCase;
use Onhost\Domain\Advisories\Models\AdvisoryMatch;
use Onhost\Domain\Advisories\Models\AdvisorySource;
use Onhost\Domain\Advisories\Models\InventoryItem;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

/**
 * Security news for technical support (SN): the advisories the feeds brought in, the cases where one hits software we run or
 * host, and the decisions about a case (triage, approve the fix plan, tell the customers, draft a blog post). Reads take
 * `security.advisory.read`; the decisions go through AdvisoryCommand (approve and notify: step-up, four eyes over the limit).
 */
final class SecurityAdvisoryController extends ApiController
{
    public function advisories(Request $request): JsonResponse
    {
        $this->api->authorize($request, 'security.advisory.read', CommandScope::global());
        $query = Advisory::query()->orderByDesc('source_modified_at')->orderByDesc('created_at');
        if ($request->filled('severity')) {
            $query->where('severity', (string) $request->query('severity'));
        }
        if ($request->boolean('kev')) {
            $query->where('kev', true);
        }
        if ($request->filled('q')) {
            $q = mb_substr((string) $request->query('q'), 0, 80);
            $query->where(fn ($w) => $w->where('key', 'like', "%{$q}%")->orWhere('title', 'like', "%{$q}%"));
        }
        $page = $query->paginate(min(100, max(1, (int) $request->query('per_page', 50))));

        return response()->json(['data' => collect($page->items())->map(fn (Advisory $a) => $a->present())->all(), 'meta' => ['total' => $page->total(), 'page' => $page->currentPage()]]);
    }

    public function cases(Request $request): JsonResponse
    {
        $this->api->authorize($request, 'security.advisory.read', CommandScope::global());
        $query = AdvisoryCase::query()->with('advisory')->orderByDesc('created_at');
        $state = (string) $request->query('state', 'open');
        if ($state === 'open') {
            $query->whereIn('state', AdvisoryCase::OPEN_STATES);
        } elseif (in_array($state, AdvisoryCase::STATES, true)) {
            $query->where('state', $state);
        }

        return response()->json(['data' => $query->limit(200)->get()->map(fn (AdvisoryCase $c) => $this->presentCase($c))->all(), 'meta' => ['single_approver_max' => AdvisoryCommand::singleApproverMax()]]);
    }

    public function show(Request $request, string $case): JsonResponse
    {
        $this->api->authorize($request, 'security.advisory.read', CommandScope::global());
        $model = AdvisoryCase::query()->with('advisory')->find($case) ?? throw DomainError::notFound('advisory_case');
        $matches = AdvisoryMatch::query()->where('case_id', $model->id)->orderBy('class')->orderBy('id')->limit(2000)->get();
        $services = Service::query()->whereIn('id', $matches->where('subject_type', InventoryItem::SERVICE)->pluck('subject_id')->unique())->get()->keyBy('id');
        $instances = ProviderInstance::query()->whereIn('id', $matches->where('subject_type', InventoryItem::INSTANCE)->pluck('subject_id')->unique())->get()->keyBy('id');
        $label = fn (AdvisoryMatch $m): ?string => match ($m->subject_type) {
            InventoryItem::SERVICE => ($s = $services->get($m->subject_id)) ? (string) ($s->hostname ?: $s->name) : null,
            InventoryItem::INSTANCE => ($i = $instances->get($m->subject_id)) ? (string) $i->key : null,
            default => 'ONhost',
        };

        return response()->json(['data' => $this->presentCase($model) + [
            'matches' => $matches->map(fn (AdvisoryMatch $m) => $m->present($label($m)))->all(),
            'targets' => ['safe' => AdvisoryCases::targets($model, false), 'with_risky' => AdvisoryCases::targets($model, true)],
            'single_approver_max' => AdvisoryCommand::singleApproverMax(),
        ]]);
    }

    /** Feed freshness and what the inventory knows: a feed that has not succeeded for a day is a blind spot. */
    public function health(Request $request): JsonResponse
    {
        $this->api->authorize($request, 'security.advisory.read', CommandScope::global());
        $sources = AdvisorySource::query()->orderBy('key')->get()->map(fn (AdvisorySource $s) => [
            'key' => $s->key, 'last_run_at' => $s->last_run_at?->toIso8601String(), 'last_success_at' => $s->last_success_at?->toIso8601String(),
            'last_error' => $s->last_error, 'fetched' => (int) $s->fetched, 'stale' => $s->last_success_at === null || $s->last_success_at->lt(now()->subDay()),
        ])->all();
        $inventory = InventoryItem::query()->toBase()->selectRaw('ecosystem, count(*) as items, max(seen_at) as seen')->groupBy('ecosystem')->orderBy('ecosystem')->get()
            ->map(fn ($r) => ['ecosystem' => (string) $r->ecosystem, 'items' => (int) $r->items, 'seen_at' => $r->seen === null ? null : (string) $r->seen])->all();

        return response()->json(['data' => ['feeds' => $sources, 'configured' => array_values((array) config('onhost.advisories.feeds', [])), 'inventory' => $inventory]]);
    }

    public function triage(Request $request, string $case): JsonResponse
    {
        $data = $request->validate(['decision' => ['required', 'string', 'in:relevant,dismissed'], 'note' => ['nullable', 'string', 'max:1000']]);

        return $this->dispatch(new AdvisoryCommand($this->idempotencyKey($request, "advisory.triage:{$case}"), ['op' => 'case.triage', 'case_id' => $case, 'decision' => $data['decision'], 'note' => $data['note'] ?? null]), $this->api->context($request, null, $data['note'] ?? null));
    }

    /**
     * Approve the fix plan the console showed (`plan_revision`). The number of sites is counted here, never taken from the
     * client, so the four eyes are asked for exactly what will run; the approval binds that body.
     */
    public function approve(Request $request, string $case): JsonResponse
    {
        $data = $request->validate(['include_risky' => ['sometimes', 'boolean'], 'plan_revision' => ['required', 'integer', 'min:0'], 'note' => ['nullable', 'string', 'max:1000']]);
        $model = AdvisoryCase::query()->find($case) ?? throw DomainError::notFound('advisory_case');
        $risky = (bool) ($data['include_risky'] ?? false);
        $payload = ['op' => 'case.approve', 'case_id' => $model->id, 'include_risky' => $risky, 'plan_revision' => (int) $data['plan_revision'], 'targets' => AdvisoryCases::targets($model, $risky), 'note' => $data['note'] ?? null];
        $approvals = array_values(array_filter((array) $request->input('approval_ids', []), 'is_string'));
        sort($approvals);
        $key = "advisory.approve:{$model->id}:".($approvals === [] ? 'r'.$payload['plan_revision'].($risky ? 'b' : 'a') : substr(hash('sha256', implode(',', $approvals)), 0, 32));

        return $this->dispatch(new AdvisoryCommand($key, $payload), $this->api->context($request, null, $data['note'] ?? null));
    }

    public function notify(Request $request, string $case): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        return $this->dispatch(new AdvisoryCommand("advisory.notify:{$case}", ['op' => 'case.notify', 'case_id' => $case, 'note' => $data['note'] ?? null]), $this->api->context($request, null, $data['note'] ?? null));
    }

    public function draftPost(Request $request, string $case): JsonResponse
    {
        return $this->dispatch(new AdvisoryCommand($this->eachRequestKey($request, "advisory.post:{$case}"), ['op' => 'case.draft_post', 'case_id' => $case]), $this->api->context($request), 201);
    }

    /** @return array<string,mixed> */
    private function presentCase(AdvisoryCase $case): array
    {
        return [
            'id' => $case->id, 'state' => $case->state, 'severity' => $case->severity, 'kev' => $case->kev, 'matches_count' => $case->matches_count, 'plan_revision' => $case->plan_revision,
            'include_risky' => $case->include_risky, 'wave' => $case->wave, 'decided_by' => $case->decided_by, 'decision_note' => $case->decision_note, 'decided_at' => $case->decided_at?->toIso8601String(),
            'halted_reason' => $case->halted_reason, 'announced_at' => $case->announced_at?->toIso8601String(), 'post_id' => $case->post_id, 'resolved_at' => $case->resolved_at?->toIso8601String(),
            'created_at' => $case->created_at?->toIso8601String(), 'advisory' => $case->advisory?->present(),
        ];
    }
}
