<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Providers\Contracts\AsyncHandle;
use Onhost\Providers\Contracts\AsyncStatus;
use Onhost\Providers\Contracts\InfrastructureProvider;
use Onhost\Providers\Contracts\ProviderResult;
use Onhost\Providers\Contracts\ResourceSpec;
use Throwable;

/**
 * Does the node really do the one thing it is there for? (H479)
 *
 * A node can pass every check on paper — it answers, it knows its size, its disk has room — and still not be able to
 * make a site, a server or a machine. The only proof is to make one: create a throw-away resource on exactly this
 * node, confirm the panel shows it, remove it, and confirm it is gone. The card is explicit that creating is not
 * enough — **a test resource that could not be removed fails the node**, because a node that leaves things behind is
 * a node that will leave a customer's cancelled service behind too.
 *
 * What is created is what the owner wrote down for the role (`onhost.provisioning.qualification.synthetic.<role>`):
 * the smallest template, image, egg or PHP version that role sells. Nothing is guessed here — a role with no template
 * is reported as `not_configured` and nothing is created at all. That is also the switch: until the owner writes a
 * template on a test range, this never touches a panel.
 *
 * The resource is removed whatever happens (a `finally`), and a removal that fails is named with its reference so it
 * can be found and deleted by hand; `onhost:doctor` lists any node whose last synthetic run left something behind.
 */
final class SyntheticService
{
    /** How long a create or a remove may take before the run gives up on it. */
    public const TIMEOUT_SECONDS = 600;

    public const POLL_SECONDS = 5;

    /** What the adapter calls the resource each role sells. */
    public const KINDS = ['compute' => 'vm', 'web' => 'website', 'managed' => 'website', 'game' => 'game_server', 'mail' => 'mail_domain'];

    /** @var callable(int):void|null test seam: how the run waits between polls */
    public static $sleeper = null;

    public function __construct(private readonly ProviderRegistry $providers) {}

    /** The attributes the owner configured for this node's role, or null when nothing may be created on it. */
    public function template(Node $node): ?array
    {
        $template = config('onhost.provisioning.qualification.synthetic.'.$node->role);

        return is_array($template) && $template !== [] ? $template : null;
    }

    /**
     * Create one resource on the node, see it, remove it, see it gone.
     *
     * @return array{status:string, detail:string, created:?string, removed:bool, leftover:?string, seconds:int, at:string}
     */
    public function run(Node $node): array
    {
        $started = microtime(true);
        $out = fn (string $status, string $detail, ?string $created = null, bool $removed = false, ?string $leftover = null) => [
            'status' => $status, 'detail' => $detail, 'created' => $created, 'removed' => $removed, 'leftover' => $leftover,
            'seconds' => (int) round(microtime(true) - $started), 'at' => now()->toIso8601String(),
        ];
        $template = $this->template($node);
        if ($template === null) {
            return $out('not_configured', "no synthetic template is configured for the role {$node->role}; nothing was created");
        }
        $kind = self::KINDS[$node->role] ?? null;
        $instance = $node->provider_instance_id !== null ? ProviderInstance::query()->find($node->provider_instance_id) : null;
        if ($kind === null || $instance === null) {
            return $out('failed', 'the node has no role a resource can be made for, or no provider instance');
        }
        $adapter = $this->providers->forInstance($instance);
        if (! $adapter instanceof InfrastructureProvider) {
            return $out('failed', "the panel of {$instance->key} cannot create resources");
        }
        $stamp = now()->format('ymdHis');
        $spec = new ResourceSpec('synthetic-'.$node->id, $kind, "qualify:{$node->id}:{$stamp}",
            $template + ['name' => 'onhost-qual-'.$stamp, 'domain' => $template['domain'] ?? "qual-{$stamp}.onhost.invalid", 'synthetic' => true],
            $node->name, $node->region_code, null);

        $ref = null;
        $removed = false;
        try {
            $created = $this->settle($adapter, $adapter->provision($spec));
            $ref = $created->ref;
            if ($ref === null) {
                return $out('failed', 'the panel accepted the request and named nothing it made');
            }
            $made = $adapter->getActualState($ref); // what the panel shows after the create …
            if (! $made->exists) {
                return $out('failed', 'the panel said it made '.$ref->remoteId.' and does not show it', $ref->remoteId);
            }
            $this->settle($adapter, $adapter->terminate($ref));
            $after = $adapter->getActualState($ref); // … and after the removal: two readings, two different moments
            $removed = ! $after->exists;

            return $removed
                ? $out('ok', 'created and removed '.$ref->remoteId, $ref->remoteId, true)
                : $out('failed', 'created '.$ref->remoteId.' and it is still there after the removal', $ref->remoteId, false, $ref->remoteId);
        } catch (Throwable $e) {
            return $out('failed', mb_substr($e->getMessage(), 0, 200), $ref?->remoteId, false, $ref?->remoteId);
        } finally {
            if ($ref !== null && ! $removed) {
                try {
                    $this->settle($adapter, $adapter->terminate($ref)); // one more try: nothing made for a test is left on a node on purpose
                } catch (Throwable) {
                    // named as `leftover` in the result; the doctor lists it
                }
            }
        }
    }

    /** Wait for an asynchronous answer, within the time a create or a remove may take. */
    private function settle(InfrastructureProvider $adapter, ProviderResult $result): ProviderResult
    {
        if (! $result->isAsync() || ! $result->async instanceof AsyncHandle) {
            return $result;
        }
        $deadline = time() + self::TIMEOUT_SECONDS;
        while (true) {
            $status = $adapter->awaitStatus($result->async);
            if ($status->state === AsyncStatus::SUCCEEDED) {
                return $result;
            }
            if ($status->state === AsyncStatus::FAILED) {
                throw new \RuntimeException('the panel reported the task as failed: '.(string) $status->message);
            }
            if (time() >= $deadline) {
                throw new \RuntimeException('the panel did not finish within '.self::TIMEOUT_SECONDS.' s');
            }
            (self::$sleeper ?? fn (int $s) => sleep($s))(self::POLL_SECONDS);
        }
    }
}
