<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Onhost\Domain\Provisioning\Workflows\ServiceActionWorkflow;
use Onhost\Platform\Errors\DomainError;

/**
 * What a customer may say about a core action (Brain card H21). The action workflow reads its parameters from the
 * same bag the platform's own callers fill — the size to resize to, whether the archive before a deletion is skipped,
 * the kind and retention of a backup, where a restore lands. None of that is a customer's to choose. Every way a
 * customer's words reach `ServiceService::requestAction()` — the API, a stored action hook, a Discord command — goes
 * through `filter()` first: a core action keeps only the keys listed here, and one nobody listed keeps nothing.
 * Feature actions are not listed: `ServiceService::featureParams()` rebuilds their parameters from scratch.
 */
final class CustomerActionParams
{
    /** @var array<string, list<string>> */
    private const CORE = [
        'power' => ['power_action', 'reason'],
        'suspend' => ['reason'],
        'resume' => ['reason'],
        'terminate' => ['reason'],
        'purge' => ['reason'],
        'backup' => ['reason'],
        'restore' => ['backup_id', 'reason'],
        'archive.restore' => ['backup_id', 'reason'],
        'snapshot' => ['name', 'description', 'reason'],
        'rollback_snapshot' => ['name', 'reason'],
    ];

    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    public static function filter(string $action, array $params): array
    {
        if (! in_array($action, ServiceActionWorkflow::CORE_ACTIONS, true)) {
            return $params;
        }
        if ($action === 'resize') { // the size of a service is what was paid for: it changes through a plan change order, which resizes after the payment
            throw new DomainError('resize_requires_plan_change', 'The resources of a service follow its plan. Change the plan to change them.', 403, ['hint' => 'order a plan change (cart line with config.upgrade_of)']);
        }

        return array_intersect_key($params, array_flip(self::keysFor($action)));
    }

    /** @return list<string> */
    private static function keysFor(string $action): array
    {
        return self::CORE[$action] ?? [];
    }
}
