<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/** Shared Apps runtime (RKE2). Tenant boundary = namespace + restricted PSA + quotas + default-deny (§9). */
interface KubernetesProvider extends InfrastructureProvider
{
    /** Namespace with PSA labels, ResourceQuota, LimitRange, default-deny NetworkPolicy and SA hardening. */
    public function ensureTenantNamespace(string $namespace, array $quota, array $labels = []): ProviderResult;

    /** @param array<string,mixed> $release image digest, env refs, replicas, ports, command, healthcheck, domains */
    public function deployRelease(string $namespace, string $app, array $release): ProviderResult;

    public function rollbackRelease(string $namespace, string $app, string $imageDigest): ProviderResult;

    public function scale(string $namespace, string $app, int $replicas): ProviderResult;

    /** @param array<string,string> $data */
    public function upsertSecret(string $namespace, string $name, array $data): ProviderResult;

    /** @return list<string> */
    public function podLogs(string $namespace, string $app, int $tailLines = 200): array;

    /** @return array{replicas:int,ready:int,updated:int,available:int,conditions:list<array<string,mixed>>} */
    public function rolloutStatus(string $namespace, string $app): array;

    /** Submit an isolated rootless BuildKit build job in the build plane namespace. */
    public function submitBuild(string $buildId, array $buildSpec): ProviderResult;
}
