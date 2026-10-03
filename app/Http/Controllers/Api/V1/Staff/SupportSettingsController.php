<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Support\Commands\SupportConfigCommand;
use Onhost\Domain\Support\Models\SlaPolicy;
use Onhost\Domain\Support\Models\SupportMacro;
use Onhost\Domain\Support\Models\SupportQueue;
use Onhost\Domain\Support\SupportConfigService;
use Onhost\Domain\Support\TicketStateMachine;
use Onhost\Platform\Commands\CommandScope;

/**
 * `/v1/staff/support/{macros|queues|sla-policies}`: the desk's macros, queues and SLA policies, managed by
 * support.queue.manage (readiness audit 2026-10, P1-3 — they were seeded once and fixed). Each route names its kind with a
 * route default; writes go through SupportConfigCommand.
 */
final class SupportSettingsController extends ApiController
{
    private const PERMISSION = 'support.queue.manage';

    private const MODELS = ['macro' => SupportMacro::class, 'queue' => SupportQueue::class, 'sla_policy' => SlaPolicy::class];

    public function index(Request $request): JsonResponse
    {
        $this->api->authorize($request, self::PERMISSION, CommandScope::global());
        $kind = $this->kind($request);
        $class = self::MODELS[$kind];

        return response()->json(['data' => $class::query()->orderBy('key')->get()->map(fn ($m) => SupportConfigService::present($kind, $m))->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->api->authorize($request, self::PERMISSION, CommandScope::global());
        $kind = $this->kind($request);
        $data = $request->validate($this->rules($kind, true));

        return $this->dispatch(new SupportConfigCommand($this->onceKey($request, "support.{$kind}.create"), ['kind' => $kind, 'op' => 'create', 'data' => $data]), $this->api->context($request), 201);
    }

    public function update(Request $request): JsonResponse
    {
        $this->api->authorize($request, self::PERMISSION, CommandScope::global());
        $kind = $this->kind($request);
        $id = (string) $request->route('id');
        $data = $request->validate($this->rules($kind, false));

        return $this->dispatch(new SupportConfigCommand($this->onceKey($request, "support.{$kind}.update:{$id}"), ['kind' => $kind, 'op' => 'update', 'id' => $id, 'data' => $data]), $this->api->context($request));
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->api->authorize($request, self::PERMISSION, CommandScope::global());
        $kind = $this->kind($request);
        $id = (string) $request->route('id');

        return $this->dispatch(new SupportConfigCommand($this->onceKey($request, "support.{$kind}.delete:{$id}"), ['kind' => $kind, 'op' => 'delete', 'id' => $id]), $this->api->context($request));
    }

    private function kind(Request $request): string
    {
        $kind = (string) $request->route('kind');

        return isset(self::MODELS[$kind]) ? $kind : abort(404);
    }

    /** @return array<string, list<string>> */
    private function rules(string $kind, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';
        $key = $creating ? ['required', 'string', 'regex:/^[a-z0-9][a-z0-9._-]{1,39}$/'] : ['sometimes', 'string', 'max:60'];
        $states = implode(',', array_keys(TicketStateMachine::machine()->toArray()));

        return match ($kind) {
            'macro' => [
                'key' => $creating ? ['required', 'string', 'regex:/^[a-z0-9][a-z0-9._-]{1,59}$/'] : $key, 'name' => [$required, 'string', 'max:120'], 'category' => ['sometimes', 'nullable', 'string', 'max:40'],
                'body' => [$required, 'array'], 'body.cs' => ['required_with:body', 'string', 'max:20000'], 'body.en' => ['sometimes', 'nullable', 'string', 'max:20000'],
                'actions' => ['sometimes', 'nullable', 'array'], 'actions.state' => ['sometimes', 'nullable', 'string', 'in:'.$states],
            ],
            'queue' => [
                'key' => $key, 'name' => [$required, 'string', 'max:120'], 'skills' => [$creating ? 'present' : 'sometimes', 'array', 'max:40'], 'skills.*' => ['string', 'regex:/^[A-Z0-9_]{2,40}$/'],
                'escalates_to' => ['sometimes', 'nullable', 'string', 'max:40'], 'state' => ['sometimes', 'in:active,archived'],
            ],
            'sla_policy' => [
                'key' => $key, 'name' => [$required, 'string', 'max:120'], 'targets' => [$required, 'array'],
                ...$this->targetRules(), 'business_hours_only' => ['sometimes', 'boolean'], 'business_hours' => ['sometimes', 'nullable', 'array'],
                'business_hours.days' => ['required_with:business_hours', 'array', 'min:1', 'max:7'], 'business_hours.days.*' => ['integer', 'between:1,7'],
                'business_hours.from' => ['required_with:business_hours', 'date_format:H:i'], 'business_hours.to' => ['required_with:business_hours', 'date_format:H:i'], 'business_hours.tz' => ['required_with:business_hours', 'timezone:all'],
            ],
            default => [],
        };
    }

    /** @return array<string, list<string>> every priority with every clock, in minutes (one year at most) */
    private function targetRules(): array
    {
        $rules = [];
        foreach (['p1', 'p2', 'p3', 'p4'] as $priority) {
            $rules["targets.{$priority}"] = ['required_with:targets', 'array'];
            foreach (['ack', 'first', 'next', 'resolve'] as $clock) {
                $rules["targets.{$priority}.{$clock}"] = ['required_with:targets', 'integer', 'between:1,525600'];
            }
        }

        return $rules;
    }
}
