<?php

declare(strict_types=1);

namespace Onhost\Providers\Kubernetes;

/** Hardened tenant manifests (blueprint §9.1, §37.1, §37.2). Nothing privileged is ever emitted. */
final class ManifestFactory
{
    public static function namespace(string $name, array $labels = []): array
    {
        return ['apiVersion' => 'v1', 'kind' => 'Namespace', 'metadata' => ['name' => $name, 'labels' => array_merge([
            'pod-security.kubernetes.io/enforce' => 'restricted', 'pod-security.kubernetes.io/audit' => 'restricted', 'pod-security.kubernetes.io/warn' => 'restricted', 'onhost.cz/managed' => 'true',
        ], $labels)]];
    }

    /** @param array{cpu_request:string,cpu_limit:string,memory:string,pods:int,ephemeral:string,pids?:int} $q */
    public static function resourceQuota(string $namespace, array $q): array
    {
        return ['apiVersion' => 'v1', 'kind' => 'ResourceQuota', 'metadata' => ['name' => 'quota', 'namespace' => $namespace], 'spec' => ['hard' => [
            'requests.cpu' => $q['cpu_request'], 'limits.cpu' => $q['cpu_limit'], 'requests.memory' => $q['memory'], 'limits.memory' => $q['memory'], 'pods' => (string) $q['pods'],
            'requests.ephemeral-storage' => $q['ephemeral'], 'limits.ephemeral-storage' => $q['ephemeral'], 'services.loadbalancers' => '0', 'services.nodeports' => '0', 'persistentvolumeclaims' => '2',
        ]]];
    }

    public static function limitRange(string $namespace, array $q): array
    {
        return ['apiVersion' => 'v1', 'kind' => 'LimitRange', 'metadata' => ['name' => 'limits', 'namespace' => $namespace], 'spec' => ['limits' => [
            ['type' => 'Container', 'default' => ['cpu' => '500m', 'memory' => '512Mi', 'ephemeral-storage' => '1Gi'], 'defaultRequest' => ['cpu' => '100m', 'memory' => '128Mi', 'ephemeral-storage' => '256Mi'], 'max' => ['cpu' => $q['cpu_limit'], 'memory' => $q['memory'], 'ephemeral-storage' => $q['ephemeral']]],
        ]]];
    }

    public static function defaultDeny(string $namespace): array
    {
        return ['apiVersion' => 'networking.k8s.io/v1', 'kind' => 'NetworkPolicy', 'metadata' => ['name' => 'default-deny', 'namespace' => $namespace], 'spec' => ['podSelector' => (object) [], 'policyTypes' => ['Ingress', 'Egress']]];
    }

    public static function allowIngress(string $namespace, string $ingressNamespace): array
    {
        return ['apiVersion' => 'networking.k8s.io/v1', 'kind' => 'NetworkPolicy', 'metadata' => ['name' => 'allow-ingress-controller', 'namespace' => $namespace], 'spec' => ['podSelector' => (object) [], 'policyTypes' => ['Ingress'], 'ingress' => [['from' => [['namespaceSelector' => ['matchLabels' => ['kubernetes.io/metadata.name' => $ingressNamespace]]]]]]]];
    }

    public static function allowDnsEgress(string $namespace): array
    {
        return ['apiVersion' => 'networking.k8s.io/v1', 'kind' => 'NetworkPolicy', 'metadata' => ['name' => 'allow-dns-egress', 'namespace' => $namespace], 'spec' => ['podSelector' => (object) [], 'policyTypes' => ['Egress'], 'egress' => [
            ['to' => [['namespaceSelector' => ['matchLabels' => ['kubernetes.io/metadata.name' => 'kube-system']]]], 'ports' => [['protocol' => 'UDP', 'port' => 53], ['protocol' => 'TCP', 'port' => 53]]],
            ['to' => [['ipBlock' => ['cidr' => '0.0.0.0/0', 'except' => ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '169.254.169.254/32']]]], 'ports' => [['protocol' => 'TCP', 'port' => 443], ['protocol' => 'TCP', 'port' => 80]]],
        ]]];
    }

    public static function defaultServiceAccount(string $namespace): array
    {
        return ['apiVersion' => 'v1', 'kind' => 'ServiceAccount', 'metadata' => ['name' => 'default', 'namespace' => $namespace], 'automountServiceAccountToken' => false];
    }

    public static function service(string $namespace, string $app, int $port): array
    {
        return ['apiVersion' => 'v1', 'kind' => 'Service', 'metadata' => ['name' => $app, 'namespace' => $namespace, 'labels' => ['app.kubernetes.io/name' => $app]], 'spec' => ['type' => 'ClusterIP', 'selector' => ['app.kubernetes.io/name' => $app], 'ports' => [['name' => 'http', 'port' => 80, 'targetPort' => $port]]]];
    }

    /** @param array{image_ref:string,image_digest?:string,port:int,replicas?:int,env?:array<string,string>,secret_name?:string,command?:list<string>,healthcheck_path?:string,cpu_request?:string,cpu_limit?:string,memory?:string} $r */
    public static function deployment(string $namespace, string $app, array $r): array
    {
        $image = isset($r['image_digest']) ? preg_replace('/(@sha256:[a-f0-9]{64}|:[^@\/]+)$/', '', $r['image_ref']).'@'.$r['image_digest'] : $r['image_ref'];
        $env = [];
        foreach ((array) ($r['env'] ?? []) as $k => $v) {
            $env[] = ['name' => (string) $k, 'value' => (string) $v];
        }
        $container = [
            'name' => 'app', 'image' => $image, 'imagePullPolicy' => 'IfNotPresent', 'ports' => [['containerPort' => (int) $r['port'], 'name' => 'http']], 'env' => $env,
            'resources' => ['requests' => ['cpu' => $r['cpu_request'] ?? '100m', 'memory' => $r['memory_request'] ?? '128Mi', 'ephemeral-storage' => '256Mi'], 'limits' => ['cpu' => $r['cpu_limit'] ?? '1', 'memory' => $r['memory'] ?? '512Mi', 'ephemeral-storage' => '1Gi']],
            'securityContext' => ['allowPrivilegeEscalation' => false, 'runAsNonRoot' => true, 'runAsUser' => 10001, 'readOnlyRootFilesystem' => (bool) ($r['read_only_root'] ?? false), 'capabilities' => ['drop' => ['ALL']], 'seccompProfile' => ['type' => 'RuntimeDefault']],
            'readinessProbe' => ['httpGet' => ['path' => $r['healthcheck_path'] ?? '/', 'port' => 'http'], 'initialDelaySeconds' => 5, 'periodSeconds' => 10, 'failureThreshold' => 3],
            'livenessProbe' => ['httpGet' => ['path' => $r['healthcheck_path'] ?? '/', 'port' => 'http'], 'initialDelaySeconds' => 30, 'periodSeconds' => 20, 'failureThreshold' => 3],
            'volumeMounts' => [['name' => 'tmp', 'mountPath' => '/tmp']],
        ];
        if (! empty($r['command'])) {
            $container['command'] = (array) $r['command'];
        }
        if (! empty($r['secret_name'])) {
            $container['envFrom'] = [['secretRef' => ['name' => $r['secret_name']]]];
        }
        $replicas = (int) ($r['replicas'] ?? 1);

        return ['apiVersion' => 'apps/v1', 'kind' => 'Deployment', 'metadata' => ['name' => $app, 'namespace' => $namespace, 'labels' => ['app.kubernetes.io/name' => $app], 'annotations' => ['onhost.cz/replicas' => (string) $replicas, 'onhost.cz/digest' => (string) ($r['image_digest'] ?? '')]], 'spec' => [
            'replicas' => $replicas, 'revisionHistoryLimit' => 5, 'progressDeadlineSeconds' => 600,
            'strategy' => ['type' => 'RollingUpdate', 'rollingUpdate' => ['maxUnavailable' => $replicas > 1 ? 0 : 1, 'maxSurge' => 1]],
            'selector' => ['matchLabels' => ['app.kubernetes.io/name' => $app]],
            'template' => ['metadata' => ['labels' => ['app.kubernetes.io/name' => $app]], 'spec' => [
                'automountServiceAccountToken' => false, 'enableServiceLinks' => false, 'hostNetwork' => false, 'hostPID' => false, 'hostIPC' => false,
                'securityContext' => ['runAsNonRoot' => true, 'seccompProfile' => ['type' => 'RuntimeDefault'], 'fsGroup' => 10001],
                'topologySpreadConstraints' => [['maxSkew' => 1, 'topologyKey' => 'kubernetes.io/hostname', 'whenUnsatisfiable' => 'ScheduleAnyway', 'labelSelector' => ['matchLabels' => ['app.kubernetes.io/name' => $app]]]],
                'containers' => [$container], 'volumes' => [['name' => 'tmp', 'emptyDir' => ['sizeLimit' => '512Mi']]],
            ]],
        ]];
    }

    public static function ingress(string $namespace, string $app, string $domain, int $port, string $ingressClass, string $clusterIssuer): array
    {
        return ['apiVersion' => 'networking.k8s.io/v1', 'kind' => 'Ingress', 'metadata' => ['name' => $app.'-'.md5($domain), 'namespace' => $namespace, 'annotations' => ['cert-manager.io/cluster-issuer' => $clusterIssuer]], 'spec' => [
            'ingressClassName' => $ingressClass, 'tls' => [['hosts' => [$domain], 'secretName' => 'tls-'.md5($domain)]],
            'rules' => [['host' => $domain, 'http' => ['paths' => [['path' => '/', 'pathType' => 'Prefix', 'backend' => ['service' => ['name' => $app, 'port' => ['number' => 80]]]]]]]],
        ]];
    }

    public static function pdb(string $namespace, string $app): array
    {
        return ['apiVersion' => 'policy/v1', 'kind' => 'PodDisruptionBudget', 'metadata' => ['name' => $app, 'namespace' => $namespace], 'spec' => ['minAvailable' => 1, 'selector' => ['matchLabels' => ['app.kubernetes.io/name' => $app]]]];
    }

    /** @param array<string,string> $data */
    public static function secret(string $namespace, string $name, array $data): array
    {
        return ['apiVersion' => 'v1', 'kind' => 'Secret', 'metadata' => ['name' => $name, 'namespace' => $namespace], 'type' => 'Opaque', 'data' => array_map(fn ($v) => base64_encode((string) $v), $data)];
    }

    /**
     * Rootless BuildKit job: git context, no production secrets, quotas, timeout, push to the private registry
     * with SBOM/provenance attestations (blueprint §9.2).
     *
     * @param  array{git_url:string,git_ref:string,image_ref:string,dockerfile?:string,build_args?:array<string,string>,cpu?:string,memory?:string,timeout_seconds?:int}  $b
     */
    public static function buildJob(string $namespace, string $buildId, array $b, string $registry, string $buildkitImage): array
    {
        $args = [];
        foreach ((array) ($b['build_args'] ?? []) as $k => $v) {
            $args[] = '--opt';
            $args[] = "build-arg:{$k}={$v}";
        }
        $cmd = array_merge([
            'buildctl-daemonless.sh', 'build', '--frontend', 'dockerfile.v0', '--opt', 'context='.$b['git_url'].'#'.$b['git_ref'], '--opt', 'filename='.($b['dockerfile'] ?? 'Dockerfile'),
            '--output', "type=image,name={$b['image_ref']},push=true", '--attest', 'type=sbom', '--attest', 'type=provenance,mode=max', '--metadata-file', '/tmp/metadata.json',
        ], $args);

        return ['apiVersion' => 'batch/v1', 'kind' => 'Job', 'metadata' => ['name' => "build-{$buildId}", 'namespace' => $namespace, 'labels' => ['onhost.cz/build' => $buildId]], 'spec' => [
            'backoffLimit' => 0, 'ttlSecondsAfterFinished' => 3600, 'activeDeadlineSeconds' => (int) ($b['timeout_seconds'] ?? 1800),
            'template' => ['metadata' => ['labels' => ['onhost.cz/build' => $buildId], 'annotations' => ['container.apparmor.security.beta.kubernetes.io/buildkitd' => 'unconfined']], 'spec' => [
                'restartPolicy' => 'Never', 'automountServiceAccountToken' => false, 'securityContext' => ['runAsUser' => 1000, 'runAsGroup' => 1000, 'fsGroup' => 1000, 'seccompProfile' => ['type' => 'Unconfined']],
                'containers' => [[
                    'name' => 'buildkitd', 'image' => $buildkitImage, 'command' => $cmd,
                    'env' => [['name' => 'BUILDKITD_FLAGS', 'value' => '--oci-worker-no-process-sandbox'], ['name' => 'DOCKER_CONFIG', 'value' => '/home/user/.docker']],
                    'resources' => ['requests' => ['cpu' => '500m', 'memory' => '1Gi'], 'limits' => ['cpu' => $b['cpu'] ?? '2', 'memory' => $b['memory'] ?? '4Gi', 'ephemeral-storage' => '20Gi']],
                    'securityContext' => ['allowPrivilegeEscalation' => false, 'runAsNonRoot' => true, 'capabilities' => ['drop' => ['ALL']]],
                    'volumeMounts' => [['name' => 'registry-auth', 'mountPath' => '/home/user/.docker', 'readOnly' => true], ['name' => 'workspace', 'mountPath' => '/home/user/.local/share/buildkit']],
                ]],
                'volumes' => [['name' => 'registry-auth', 'secret' => ['secretName' => 'registry-push-credentials']], ['name' => 'workspace', 'emptyDir' => ['sizeLimit' => '20Gi']]],
            ]],
        ]];
    }

    public static function cpuToMilli(string $value): int
    {
        return str_ends_with($value, 'm') ? (int) rtrim($value, 'm') : (int) round(((float) $value) * 1000);
    }

    public static function memToBytes(string $value): int
    {
        $units = ['Ki' => 1024, 'Mi' => 1024 ** 2, 'Gi' => 1024 ** 3, 'Ti' => 1024 ** 4, 'K' => 1000, 'M' => 1000 ** 2, 'G' => 1000 ** 3];
        foreach ($units as $suffix => $mult) {
            if (str_ends_with($value, $suffix)) {
                return (int) (((float) substr($value, 0, -strlen($suffix))) * $mult);
            }
        }

        return (int) $value;
    }
}
