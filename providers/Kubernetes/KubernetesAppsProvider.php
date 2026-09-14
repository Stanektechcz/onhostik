<?php

declare(strict_types=1);

namespace Onhost\Providers\Kubernetes;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\ProviderHttp\ProviderHttpClient;
use Onhost\Platform\ProviderHttp\ProviderRequest;
use Onhost\Platform\ProviderHttp\ProviderResponse;
use Onhost\Providers\Contracts\ActionPlan;
use Onhost\Providers\Contracts\ActualState;
use Onhost\Providers\Contracts\AsyncHandle;
use Onhost\Providers\Contracts\AsyncStatus;
use Onhost\Providers\Contracts\KubernetesProvider;
use Onhost\Providers\Contracts\ProviderHealth;
use Onhost\Providers\Contracts\ProviderResult;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\ResourceSpec;
use Onhost\Providers\Contracts\TlsOptions;
use Onhost\Providers\Contracts\Usage;

/**
 * RKE2/Kubernetes executor for ONhost Apps (blueprint §9). Tenant boundary =
 * namespace with restricted Pod Security, ResourceQuota, LimitRange, default-deny
 * NetworkPolicy and a non-mounting default service account. Builds run in the
 * separate build namespace as rootless BuildKit jobs. The control plane uses a
 * ServiceAccount bearer token scoped to tenant namespaces by RBAC.
 */
final class KubernetesAppsProvider implements KubernetesProvider
{
    public function __construct(
        private readonly ProviderInstance $instance,
        private readonly array $credentials,
        private readonly ProviderHttpClient $http,
        private readonly CacheRepository $cache,
    ) {
        $this->http->configureBucket($instance->key, (int) ($instance->rate_limits['per_minute'] ?? 600), 60, 0.1);
    }

    public static function providerKey(): string
    {
        return 'kubernetes';
    }

    public static function adapterVersion(): string
    {
        return '1.0.0';
    }

    public static function supportedVendorVersions(): array
    {
        return ['v1.33+rke2', 'v1.34+rke2', 'v1.35+rke2', 'v1.36+rke2'];
    }

    public function capabilities(): array
    {
        return ['namespace.tenant' => true, 'deploy.rolling' => true, 'deploy.rollback' => true, 'secrets' => true, 'logs' => true, 'metrics' => 'metrics_server', 'build.buildkit' => true, 'ingress' => (string) $this->instance->option('ingress_class', 'traefik'), 'privileged' => false, 'host_network' => false];
    }

    public function health(): ProviderHealth
    {
        $started = hrtime(true);
        try {
            $version = $this->request('GET', '/version', 'version');
            $nodes = $this->request('GET', '/api/v1/nodes', 'nodes');
            $ready = 0;
            foreach ((array) ($nodes['items'] ?? []) as $node) {
                foreach ((array) ($node['status']['conditions'] ?? []) as $c) {
                    if (($c['type'] ?? '') === 'Ready' && ($c['status'] ?? '') === 'True') {
                        $ready++;
                    }
                }
            }
            $ms = (int) ((hrtime(true) - $started) / 1_000_000);
            $this->cache->put("onhost:k8s:version:{$this->instance->id}", (string) ($version['gitVersion'] ?? ''), 3600);

            return new ProviderHealth($ready > 0, (string) ($version['gitVersion'] ?? null), $ms, ['nodes' => count($nodes['items'] ?? []), 'ready' => $ready]);
        } catch (ProviderException $e) {
            return ProviderHealth::down($e->getMessage(), (int) ((hrtime(true) - $started) / 1_000_000));
        }
    }

    public function vendorVersion(): ?string
    {
        $v = $this->cache->get("onhost:k8s:version:{$this->instance->id}");

        return is_string($v) && $v !== '' ? $v : null;
    }

    public function ensureTenantNamespace(string $namespace, array $quota, array $labels = []): ProviderResult
    {
        $this->apply('/api/v1/namespaces', $namespace, ManifestFactory::namespace($namespace, $labels), 'ns');
        $this->apply("/api/v1/namespaces/{$namespace}/resourcequotas", 'quota', ManifestFactory::resourceQuota($namespace, $quota), 'quota');
        $this->apply("/api/v1/namespaces/{$namespace}/limitranges", 'limits', ManifestFactory::limitRange($namespace, $quota), 'limitrange');
        $this->apply("/apis/networking.k8s.io/v1/namespaces/{$namespace}/networkpolicies", 'default-deny', ManifestFactory::defaultDeny($namespace), 'netpol.deny');
        $this->apply("/apis/networking.k8s.io/v1/namespaces/{$namespace}/networkpolicies", 'allow-ingress-controller', ManifestFactory::allowIngress($namespace, (string) $this->instance->option('ingress_namespace', 'kube-system')), 'netpol.ingress');
        $this->apply("/apis/networking.k8s.io/v1/namespaces/{$namespace}/networkpolicies", 'allow-dns-egress', ManifestFactory::allowDnsEgress($namespace), 'netpol.dns');
        $this->apply("/api/v1/namespaces/{$namespace}/serviceaccounts", 'default', ManifestFactory::defaultServiceAccount($namespace), 'sa');

        return ProviderResult::completed(new ResourceRef('namespace', $namespace, null, ['quota' => $quota]), ['namespace' => $namespace]);
    }

    public function provision(ResourceSpec $spec): ProviderResult
    {
        $namespace = (string) $spec->get('namespace', 'tenant-'.strtolower(str_replace('_', '-', $spec->serviceId)));
        $ent = (array) $spec->get('entitlements', []);

        return $this->ensureTenantNamespace($namespace, [
            'cpu_request' => (string) ($ent['cpu_request'] ?? '0.5'), 'cpu_limit' => (string) ($ent['cpu_limit'] ?? '2'), 'memory' => (int) ($ent['ram_mb'] ?? 1024).'Mi',
            'pods' => (int) (($ent['replicas'] ?? 1) * 4 + (int) ($ent['workers'] ?? 0) * 2 + 4), 'ephemeral' => (int) ($ent['ephemeral_gb'] ?? 5).'Gi', 'pids' => (int) ($spec->get('limits.pids', 256)),
        ], ['onhost.cz/service' => $spec->serviceId, 'onhost.cz/organization' => (string) $spec->organizationId, 'onhost.cz/tier' => (string) $spec->get('sla_class', 'standard')]);
    }

    public function deployRelease(string $namespace, string $app, array $release): ProviderResult
    {
        $this->apply("/api/v1/namespaces/{$namespace}/services", $app, ManifestFactory::service($namespace, $app, (int) $release['port']), 'svc');
        $this->apply("/apis/apps/v1/namespaces/{$namespace}/deployments", $app, ManifestFactory::deployment($namespace, $app, $release), 'deploy');
        foreach ((array) ($release['domains'] ?? []) as $domain) {
            $this->apply("/apis/networking.k8s.io/v1/namespaces/{$namespace}/ingresses", $app.'-'.md5($domain), ManifestFactory::ingress($namespace, $app, $domain, (int) $release['port'], (string) $this->instance->option('ingress_class', 'traefik'), (string) $this->instance->option('cluster_issuer', 'letsencrypt')), 'ingress');
        }
        $pdb = (int) ($release['replicas'] ?? 1) > 1;
        if ($pdb) {
            $this->apply("/apis/policy/v1/namespaces/{$namespace}/poddisruptionbudgets", $app, ManifestFactory::pdb($namespace, $app), 'pdb');
        }

        return ProviderResult::accepted(new AsyncHandle('k8s_rollout', "{$namespace}/{$app}", null, ['digest' => $release['image_digest'] ?? null], 5, 900), new ResourceRef('deployment', "{$namespace}/{$app}", null, ['namespace' => $namespace, 'app' => $app]));
    }

    public function rollbackRelease(string $namespace, string $app, string $imageDigest): ProviderResult
    {
        $current = $this->request('GET', "/apis/apps/v1/namespaces/{$namespace}/deployments/{$app}", 'deploy.get');
        $containers = $current['spec']['template']['spec']['containers'] ?? [];
        if ($containers === []) {
            throw new ProviderException('kubernetes', ProviderErrorCode::NOT_FOUND, "Deployment {$namespace}/{$app} has no containers");
        }
        $image = preg_replace('/@sha256:[a-f0-9]{64}$|:[^@\/]+$/', '', (string) $containers[0]['image']).'@'.$imageDigest;
        $this->request('PATCH', "/apis/apps/v1/namespaces/{$namespace}/deployments/{$app}", 'deploy.rollback', ['spec' => ['template' => ['spec' => ['containers' => [['name' => $containers[0]['name'], 'image' => $image]]]]]], contentType: 'application/strategic-merge-patch+json');

        return ProviderResult::accepted(new AsyncHandle('k8s_rollout', "{$namespace}/{$app}", null, ['digest' => $imageDigest], 5, 600));
    }

    public function scale(string $namespace, string $app, int $replicas): ProviderResult
    {
        $this->request('PATCH', "/apis/apps/v1/namespaces/{$namespace}/deployments/{$app}/scale", 'deploy.scale', ['spec' => ['replicas' => $replicas]], contentType: 'application/merge-patch+json');

        return ProviderResult::accepted(new AsyncHandle('k8s_rollout', "{$namespace}/{$app}", null, ['replicas' => $replicas], 5, 600));
    }

    public function upsertSecret(string $namespace, string $name, array $data): ProviderResult
    {
        $this->apply("/api/v1/namespaces/{$namespace}/secrets", $name, ManifestFactory::secret($namespace, $name, $data), 'secret');

        return ProviderResult::completed(new ResourceRef('secret', "{$namespace}/{$name}"), ['keys' => array_keys($data)]);
    }

    public function podLogs(string $namespace, string $app, int $tailLines = 200): array
    {
        $pods = $this->request('GET', "/api/v1/namespaces/{$namespace}/pods", 'pods.list', [], ['labelSelector' => "app.kubernetes.io/name={$app}"]);
        $lines = [];
        foreach ((array) ($pods['items'] ?? []) as $pod) {
            $name = (string) $pod['metadata']['name'];
            $body = $this->raw('GET', "/api/v1/namespaces/{$namespace}/pods/{$name}/log", 'pods.log', [], ['tailLines' => $tailLines, 'timestamps' => 'true'])->rawBody;
            foreach (preg_split('/\r?\n/', trim($body)) ?: [] as $line) {
                if ($line !== '') {
                    $lines[] = "[{$name}] {$line}";
                }
            }
        }

        return array_slice($lines, -$tailLines);
    }

    public function rolloutStatus(string $namespace, string $app): array
    {
        $d = $this->request('GET', "/apis/apps/v1/namespaces/{$namespace}/deployments/{$app}", 'deploy.status');
        $s = (array) ($d['status'] ?? []);

        return ['replicas' => (int) ($d['spec']['replicas'] ?? 0), 'ready' => (int) ($s['readyReplicas'] ?? 0), 'updated' => (int) ($s['updatedReplicas'] ?? 0), 'available' => (int) ($s['availableReplicas'] ?? 0), 'conditions' => (array) ($s['conditions'] ?? []), 'generation' => (int) ($d['metadata']['generation'] ?? 0), 'observed' => (int) ($s['observedGeneration'] ?? 0)];
    }

    public function submitBuild(string $buildId, array $buildSpec): ProviderResult
    {
        $ns = (string) $this->instance->option('build_namespace', 'onhost-build');
        $this->request('POST', "/apis/batch/v1/namespaces/{$ns}/jobs", 'build.job', ManifestFactory::buildJob($ns, $buildId, $buildSpec, (string) $this->instance->option('registry', 'registry.onhost.internal'), (string) $this->instance->option('buildkit_image', 'moby/buildkit:rootless')));

        return ProviderResult::accepted(new AsyncHandle('k8s_job', "{$ns}/build-{$buildId}", null, ['build_id' => $buildId], 10, (int) ($buildSpec['timeout_seconds'] ?? 1800)));
    }

    public function getActualState(ResourceRef $ref): ActualState
    {
        try {
            $ns = $this->request('GET', "/api/v1/namespaces/{$ref->remoteId}", 'ns.get');
            $quota = $this->request('GET', "/api/v1/namespaces/{$ref->remoteId}/resourcequotas/quota", 'quota.get');
        } catch (ProviderException $e) {
            if ($e->errorCode === ProviderErrorCode::NOT_FOUND) {
                return ActualState::missing();
            }
            throw $e;
        }
        $labels = (array) ($ns['metadata']['labels'] ?? []);

        return new ActualState(true, [
            'psa_enforce' => $labels['pod-security.kubernetes.io/enforce'] ?? null, 'quota' => $quota['spec']['hard'] ?? [], 'used' => $quota['status']['used'] ?? [], 'phase' => $ns['status']['phase'] ?? null,
        ], (string) ($ns['status']['phase'] ?? 'Unknown'), now()->toISOString());
    }

    public function reconcile(ResourceSpec $spec, ActualState $actual): ActionPlan
    {
        if (! $actual->exists) {
            return new ActionPlan([ActionPlan::drift('existence', 'present', 'missing', 'ONHOST_MANAGED', 'SECURITY_SUSPICIOUS')]);
        }
        $drifts = [];
        if ($actual->get('psa_enforce') !== 'restricted') {
            $drifts[] = ActionPlan::drift('pod_security', 'restricted', $actual->get('psa_enforce'), 'ONHOST_MANAGED', 'SECURITY_SUSPICIOUS');
        }
        $ent = (array) $spec->get('entitlements', []);
        $expectedMem = (int) ($ent['ram_mb'] ?? 1024).'Mi';
        if (isset($ent['ram_mb']) && ($actual->get('quota.limits.memory') ?? null) !== $expectedMem) {
            $drifts[] = ActionPlan::drift('quota.limits.memory', $expectedMem, $actual->get('quota.limits.memory'), 'ONHOST_MANAGED', 'AUTO_REPAIRABLE');
        }

        return new ActionPlan($drifts);
    }

    public function resize(ResourceRef $ref, ResourceSpec $spec): ProviderResult
    {
        return $this->provision($spec->with(['namespace' => $ref->remoteId]));
    }

    public function suspend(ResourceRef $ref): ProviderResult
    {
        $deployments = $this->request('GET', "/apis/apps/v1/namespaces/{$ref->remoteId}/deployments", 'deploy.list');
        foreach ((array) ($deployments['items'] ?? []) as $d) {
            $this->request('PATCH', "/apis/apps/v1/namespaces/{$ref->remoteId}/deployments/{$d['metadata']['name']}/scale", 'deploy.scale', ['spec' => ['replicas' => 0]], contentType: 'application/merge-patch+json');
        }

        return ProviderResult::completed($ref, ['scaled_to_zero' => count($deployments['items'] ?? [])]);
    }

    public function resume(ResourceRef $ref): ProviderResult
    {
        $deployments = $this->request('GET', "/apis/apps/v1/namespaces/{$ref->remoteId}/deployments", 'deploy.list');
        foreach ((array) ($deployments['items'] ?? []) as $d) {
            $replicas = (int) ($d['metadata']['annotations']['onhost.cz/replicas'] ?? 1);
            $this->request('PATCH', "/apis/apps/v1/namespaces/{$ref->remoteId}/deployments/{$d['metadata']['name']}/scale", 'deploy.scale', ['spec' => ['replicas' => max(1, $replicas)]], contentType: 'application/merge-patch+json');
        }

        return ProviderResult::completed($ref, ['resumed' => true]);
    }

    public function terminate(ResourceRef $ref): ProviderResult
    {
        if (! $this->getActualState($ref)->exists) {
            return ProviderResult::completed(null, ['already_deleted' => true], alreadyExisted: true);
        }
        $this->request('DELETE', "/api/v1/namespaces/{$ref->remoteId}", 'ns.delete', ['propagationPolicy' => 'Foreground']);

        return ProviderResult::accepted(new AsyncHandle('k8s_ns_delete', $ref->remoteId, null, [], 10, 900), null);
    }

    public function usage(ResourceRef $ref, ?string $periodStart = null, ?string $periodEnd = null): Usage
    {
        $metrics = $this->request('GET', "/apis/metrics.k8s.io/v1beta1/namespaces/{$ref->remoteId}/pods", 'metrics.pods');
        $cpuMilli = 0;
        $memBytes = 0;
        foreach ((array) ($metrics['items'] ?? []) as $pod) {
            foreach ((array) ($pod['containers'] ?? []) as $c) {
                $cpuMilli += ManifestFactory::cpuToMilli((string) ($c['usage']['cpu'] ?? '0'));
                $memBytes += ManifestFactory::memToBytes((string) ($c['usage']['memory'] ?? '0'));
            }
        }

        return new Usage(['cpu_milli' => $cpuMilli, 'mem_bytes' => $memBytes, 'pods' => count($metrics['items'] ?? [])], now()->toISOString());
    }

    public function awaitStatus(AsyncHandle $handle): AsyncStatus
    {
        if ($handle->kind === 'k8s_rollout') {
            [$ns, $app] = explode('/', $handle->handle, 2);
            $status = $this->rolloutStatus($ns, $app);
            foreach ($status['conditions'] as $c) {
                if (($c['type'] ?? '') === 'Progressing' && ($c['reason'] ?? '') === 'ProgressDeadlineExceeded') {
                    return AsyncStatus::failed('rollout deadline exceeded', $status);
                }
            }
            if ($status['observed'] >= $status['generation'] && $status['updated'] === $status['replicas'] && $status['available'] === $status['replicas'] && $status['replicas'] > 0) {
                return AsyncStatus::succeeded($status);
            }

            return AsyncStatus::running("{$status['available']}/{$status['replicas']} available", $status);
        }
        if ($handle->kind === 'k8s_job') {
            [$ns, $name] = explode('/', $handle->handle, 2);
            try {
                $job = $this->request('GET', "/apis/batch/v1/namespaces/{$ns}/jobs/{$name}", 'job.get');
            } catch (ProviderException $e) {
                return $e->errorCode === ProviderErrorCode::NOT_FOUND ? AsyncStatus::unknown('job not found') : throw $e;
            }
            if (! empty($job['status']['succeeded'])) {
                return AsyncStatus::succeeded(['job' => $name]);
            }
            if (! empty($job['status']['failed'])) {
                return AsyncStatus::failed('build job failed', ['conditions' => $job['status']['conditions'] ?? []]);
            }

            return AsyncStatus::running('build running');
        }
        if ($handle->kind === 'k8s_ns_delete') {
            try {
                $this->request('GET', "/api/v1/namespaces/{$handle->handle}", 'ns.get');

                return AsyncStatus::running('namespace terminating');
            } catch (ProviderException $e) {
                return $e->errorCode === ProviderErrorCode::NOT_FOUND ? AsyncStatus::succeeded() : throw $e;
            }
        }

        return AsyncStatus::unknown("unknown handle kind {$handle->kind}");
    }

    // ── transport ────────────────────────────────────────────────────────────

    /** Server-side apply (PATCH with field manager); creates when missing. */
    private function apply(string $collectionPath, string $name, array $manifest, string $action): array
    {
        return $this->request('PATCH', "{$collectionPath}/{$name}", $action, $manifest, ['fieldManager' => 'onhost-control-plane', 'force' => 'true'], 'application/apply-patch+yaml');
    }

    private function request(string $method, string $path, string $action, array $body = [], array $query = [], ?string $contentType = null): array
    {
        $response = $this->raw($method, $path, $action, $body, $query, $contentType);
        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    private function raw(string $method, string $path, string $action, array $body = [], array $query = [], ?string $contentType = null): ProviderResponse
    {
        $token = (string) ($this->credentials['token'] ?? '');
        if ($token === '') {
            throw new ProviderException('kubernetes', ProviderErrorCode::AUTH, 'Kubernetes service account token is not configured');
        }
        $options = TlsOptions::verify($this->instance, 'kubernetes');
        $headers = ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
        $bodyType = 'json';
        $payload = $body === [] ? null : $body;
        if ($contentType !== null) {
            $headers['Content-Type'] = $contentType;
            $bodyType = 'raw';
            $payload = json_encode($body, JSON_UNESCAPED_SLASHES);
        }
        $response = $this->http->send(new ProviderRequest(
            provider: 'kubernetes', instanceKey: $this->instance->key, method: $method, url: rtrim((string) $this->instance->base_url, '/').$path, action: $action,
            headers: $headers, body: $payload, bodyType: $bodyType, query: $query, timeoutSeconds: 20, critical: true, idempotent: $method === 'GET', options: $options,
        ));
        if (in_array($response->status, [401, 403], true)) {
            throw new ProviderException('kubernetes', ProviderErrorCode::AUTH, "Kubernetes API rejected the token for {$action} (HTTP {$response->status})", (string) $response->status);
        }
        if ($response->status === 404) {
            throw new ProviderException('kubernetes', ProviderErrorCode::NOT_FOUND, "Kubernetes object not found for {$action}", '404');
        }
        if ($response->status === 409) {
            throw new ProviderException('kubernetes', ProviderErrorCode::CONFLICT, "Kubernetes {$action}: ".(string) $response->json('message', 'conflict'), '409', retryAfterSeconds: 5);
        }
        if ($response->status === 429) {
            throw new ProviderException('kubernetes', ProviderErrorCode::RATE_LIMIT, 'Kubernetes API rate limited', '429', retryAfterSeconds: $response->retryAfterSeconds() ?? 5);
        }
        if ($response->status === 422 || $response->status === 400) {
            throw new ProviderException('kubernetes', ProviderErrorCode::VALIDATION, "Kubernetes {$action}: ".(string) $response->json('message', 'invalid'), (string) $response->status);
        }
        if ($response->status >= 500) {
            throw new ProviderException('kubernetes', ProviderErrorCode::TRANSIENT, "Kubernetes {$action}: HTTP {$response->status}", (string) $response->status);
        }
        $this->http->recordSuccess($this->instance->key);

        return $response;
    }
}
