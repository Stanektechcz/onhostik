# Kubernetes (RKE2) apps executor

**Files:** `providers/Kubernetes/KubernetesAppsProvider.php`, `providers/Kubernetes/ManifestFactory.php`
· **Contract test:** `tests/Contract/KubernetesContractTest.php`

Talks to the RKE2 API server with a service-account token from `env://KUBERNETES_<KEY>` (namespace-scoped
RBAC: create namespaces, apply workloads, read pods/logs; no cluster-admin). Options: `ingress_class`,
`cluster_issuer` (cert-manager), `registry` (internal image registry), `storage_class`, `apps_domain`.

## Manifests (`ManifestFactory`)

Per service, one namespace `onhost-<service ulid>` with hardened defaults: ResourceQuota and LimitRange from
the plan entitlements, default-deny NetworkPolicy (+ egress to DNS and the registry), Pod Security
`restricted`, non-root containers, read-only root filesystem, no privilege escalation, seccomp `RuntimeDefault`.
Workload = Deployment + Service + Ingress (TLS via cert-manager) + optional PVC; secrets from the customer's
environment are stored as Kubernetes Secrets, never in the control plane.

## Operations

| Contract | Method | Kubernetes action |
| --- | --- | --- |
| AppsProvider | `create` | server-side apply of namespace, quota, policies, empty Deployment |
| | `deploy(source)` | BuildKit rootless Job (`buildkitd` in the build namespace) that builds the customer repo/Dockerfile or buildpack, pushes to the registry, then updates the Deployment image; `awaitStatus` watches Job then rollout status |
| | `rollback(revision)` | `kubectl rollout undo` equivalent (patch to the previous ReplicaSet template) |
| | `scale` / `resize` | patch replicas / resources within the quota |
| | `suspend` / `resume` | scale to 0 / back |
| | `logs` | `GET …/pods/{pod}/log?tailLines` (streamed by the live adapter format) |
| | `destroy` | delete namespace (foreground) |

Preview environments (`#/nasazeni`): one namespace per PR with an ingress host under `web_preview_suffix`.

Errors: 401/403 → `AUTH`; 409 on apply → retried with server-side apply force on our field manager; 422 →
`VALIDATION`; quota exceeded → `CAPACITY`; API unreachable → `TRANSIENT`.
