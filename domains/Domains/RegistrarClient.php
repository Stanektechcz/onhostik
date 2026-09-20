<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains;

use Illuminate\Support\Str;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Domains\Models\RegistrarOperation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\Redaction\Redactor;
use Onhost\Providers\Contracts\ProviderResult;
use Onhost\Providers\Contracts\RegistrarProvider;

/**
 * Registrar façade used by the domain services and workflows. Several registrars
 * (WEDOS, Subreg, …) can be active at once: a domain is always handled by the
 * registrar that holds it (`domains.registrar_provider`), new registrations and
 * transfers go to the registrar chosen by `RegistrarSelector`. Every mutating
 * command is written to registrar_operations with its clTRID before it is sent
 * and finalised afterwards, so a timeout can always be resolved (S34).
 */
final class RegistrarClient
{
    public function __construct(private readonly ProviderRegistry $providers, private readonly Redactor $redactor) {}

    /** All usable registrar instances in preference order (`onhost.domains.registrar.preference`, then key). @return list<ProviderInstance> */
    public function instances(): array
    {
        $preference = array_values((array) config('onhost.domains.registrar.preference', []));
        $rank = fn (ProviderInstance $i) => (($p = array_search($i->provider, $preference, true)) === false ? count($preference) : $p);
        $all = ProviderInstance::query()->platform()->where('state', 'active')->orderBy('provider')->orderBy('key')->get()
            ->filter(fn (ProviderInstance $i) => $i->isUsable() && $i->supports('registrar') && is_a((string) $this->providers->adapterClass($i->provider), RegistrarProvider::class, true))
            ->sortBy(fn (ProviderInstance $i) => sprintf('%02d:%s', $rank($i), $i->key))
            ->values();

        return $all->all();
    }

    /** @return list<string> distinct provider keys of the usable registrar instances */
    public function providerKeys(): array
    {
        return array_values(array_unique(array_map(fn (ProviderInstance $i) => $i->provider, $this->instances())));
    }

    /** Provider key used when nothing else decides (first usable registrar, else the configured preference). */
    public function defaultProviderKey(): string
    {
        $keys = $this->providerKeys();
        if ($keys !== []) {
            return $keys[0];
        }
        $preference = (array) config('onhost.domains.registrar.preference', ['wedos']);

        return (string) ($preference[0] ?? 'wedos');
    }

    public function instance(?string $provider = null): ProviderInstance
    {
        if ($provider === null || $provider === '' || $provider === 'auto') {
            $instances = $this->instances();
            if ($instances === []) {
                throw new DomainError('registrar_unavailable', 'No enabled registrar instance is configured.', 503);
            }

            return $instances[0];
        }
        $instance = $this->providers->findInstance($provider, null, 'registrar');
        if ($instance === null) {
            throw new DomainError('registrar_unavailable', "No enabled registrar instance for {$provider}.", 503);
        }

        return $instance;
    }

    public function adapter(?string $provider = null): RegistrarProvider
    {
        return $this->adapterFor($this->instance($provider));
    }

    public function adapterFor(ProviderInstance $instance): RegistrarProvider
    {
        $adapter = $this->providers->forInstance($instance);
        if (! $adapter instanceof RegistrarProvider) {
            throw new DomainError('registrar_invalid', get_class($adapter).' is not a RegistrarProvider.', 500);
        }

        return $adapter;
    }

    /** The registrar holding the domain. */
    public function forDomain(Domain $domain): RegistrarProvider
    {
        return $this->adapter($domain->registrar_provider ?: null);
    }

    public function instanceForDomain(Domain $domain): ProviderInstance
    {
        return $this->instance($domain->registrar_provider ?: null);
    }

    /** `onhost:v4:<command>:<op id or ulid>` — the correlation/idempotency anchor for every registrar (§45.6). */
    public static function clTrid(string $command, ?string $operationId = null): string
    {
        return 'onhost:v4:'.$command.':'.($operationId ?? strtolower((string) Str::ulid()));
    }

    /**
     * Run a mutating registrar command with an operation record.
     *
     * @param  callable(RegistrarProvider, string $clTrid): ProviderResult  $call
     */
    public function mutate(string $command, ?Domain $domain, array $request, callable $call, ?string $operationId = null, ?string $organizationId = null, bool $testMode = false, ?RegistrarProvider $adapter = null): ProviderResult
    {
        $adapter ??= $domain === null ? $this->adapter() : $this->forDomain($domain);
        $clTrid = self::clTrid($command, $operationId);
        // The id is fixed per operation and command, and the column is unique: a command a step had every right to send again (the
        // registry never received the first one) died on the database instead of reaching the registrar. Every attempt is a row
        // of its own — `…:r2`, `…:r3` — so the log shows what was sent when, and the first id stays what it was.
        $earlier = RegistrarOperation::query()->where('cltrid', $clTrid)->orWhere('cltrid', 'like', $clTrid.':r%')->count();
        if ($earlier > 0) {
            $clTrid .= ':r'.($earlier + 1);
        }
        $record = RegistrarOperation::query()->create([
            'domain_id' => $domain?->id, 'organization_id' => $organizationId ?? $domain?->organization_id, 'operation_id' => $operationId, 'command' => $command, 'cltrid' => $clTrid,
            'registrar_provider' => $adapter::providerKey(),
            'state' => RegistrarOperation::SENT, 'request' => $this->redactor->redact($request), 'test_mode' => $testMode, 'sent_at' => now(),
        ]);
        try {
            $result = $call($adapter, $clTrid);
        } catch (ProviderException $e) {
            $record->forceFill([
                'state' => in_array($e->errorCode, [ProviderErrorCode::TRANSIENT, ProviderErrorCode::UNKNOWN], true) ? RegistrarOperation::UNKNOWN : RegistrarOperation::FAILED,
                'vendor_code' => $e->vendorCode, 'vendor_message' => mb_substr($e->getMessage(), 0, 250), 'normalized_error' => (string) ($e->context['normalized'] ?? $e->errorCode->value), 'completed_at' => now(),
                'response' => $this->redactor->redact(['context' => $e->context]),
            ])->save();
            throw $e;
        }
        $record->forceFill([
            'state' => $result->isAsync() ? RegistrarOperation::PENDING_REGISTRY : RegistrarOperation::SUCCEEDED,
            'response' => $this->redactor->redact(['detail' => $result->data, 'ref' => $result->ref?->toArray(), 'already_existed' => $result->alreadyExisted]),
            'completed_at' => $result->isAsync() ? null : now(),
        ])->save();

        return $result;
    }

    /** Resolve an operation left in UNKNOWN/PENDING_REGISTRY after a timeout: was the command actually applied? */
    public function settle(RegistrarOperation $operation, string $state, ?array $response = null): RegistrarOperation
    {
        $operation->forceFill(['state' => $state, 'completed_at' => now(), 'response' => $response === null ? $operation->response : $this->redactor->redact($response)])->save();

        return $operation;
    }
}
