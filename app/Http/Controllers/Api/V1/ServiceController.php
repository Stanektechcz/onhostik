<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Presenters\Presenters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Onhost\Domain\Billing\ChargebackService;
use Onhost\Domain\Billing\Commands\ChargebackCommand;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Workflows\ServiceActionWorkflow;
use Onhost\Domain\Services\Commands\IssueConsoleTokenCommand;
use Onhost\Domain\Services\Commands\ServiceActionCommand;
use Onhost\Domain\Services\Commands\WebToolsCommand;
use Onhost\Domain\Services\DestructivePreview;
use Onhost\Domain\Services\Mail\MailboxPasswordLinks;
use Onhost\Domain\Services\Metering\WebDiskTotal;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\SshKeyGrant;
use Onhost\Domain\Services\PlanChangeService;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\ServiceHealthCheck;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Services\ServiceSpecService;
use Onhost\Domain\Services\ServiceSummary;
use Onhost\Domain\Services\SshKeyLedger;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Files\FileStore;
use Onhost\Platform\Files\UploadGuard;
use Onhost\Platform\Files\VirusScanner;
use Symfony\Component\HttpFoundation\Response;

final class ServiceController extends ApiController
{
    public function index(Request $request, Authorizer $authorizer): JsonResponse
    {
        $organization = $this->api->organization($request);
        $query = Service::query()->where('organization_id', $organization->id);
        if ($this->api->can($request, 'service.read', CommandScope::organization($organization->id))) {
            $this->api->authorize($request, 'service.read', CommandScope::organization($organization->id));
        } else {
            // a member whose organization role does not read services still sees the projects they have a role in — and the
            // single services that were shared with them (a guest sees exactly those)
            $projects = $authorizer->projectIdsWhere($this->api->user($request), 'service.read', $organization->id);
            $shared = $authorizer->resourceIdsWhere($this->api->user($request), 'service.read', $organization->id);
            if ($projects === [] && $shared === []) {
                $this->api->authorize($request, 'service.read', CommandScope::organization($organization->id));
            }
            $this->api->assertTokenScope($request, 'service.read');
            $query->where(fn ($q) => $q->whereIn('project_id', $projects)->orWhereIn('id', $shared));
        }
        foreach (['family', 'product_key', 'state', 'project_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, (string) $request->query($filter));
            }
        }

        $summary = app(ServiceSummary::class);

        return $this->api->paginate($request, $query, fn (Service $s) => Presenters::service($s) + $summary->billingFor($s));
    }

    public function show(Request $request, string $service): JsonResponse
    {
        $model = $this->resolve($request, $service);
        $manages = $this->api->can($request, 'service.manage', CommandScope::resource($model->id, $model->organization_id, $model->project_id)); // a secret a run generated is shown to whoever manages the service, never to a reader
        $operations = Operation::query()->where('service_id', $model->id)->orderByDesc('queued_at')->limit(10)->get()->map(fn (Operation $o) => Presenters::operation($o, false, $manages))->all();
        $bindings = array_map(fn (ProviderBinding $b) => ['type' => $b->remote_type, 'node' => $b->remote_node, 'adapter_version' => $b->adapter_version, 'last_reconciled_at' => $b->last_reconciled_at?->toIso8601String()], $model->bindings()->get()->all());

        return response()->json(['data' => Presenters::service($model) + ['operations' => $operations, 'bindings' => $bindings, 'actual' => $model->actual_spec, 'summary' => app(ServiceSummary::class)->for($model)]]);
    }

    /** Generic action endpoint plus the shorthand routes (power/resize/backup/…) mapped onto it. */
    /**
     * What a destructive action would really do — the service by name, the things that would go, what hangs on them and
     * how far back the customer could come (H414). The answer carries a fingerprint of the target; sending it back with
     * the action makes the platform refuse a confirmation that no longer describes what would happen.
     */
    public function preview(Request $request, DestructivePreview $preview, string $service, string $action): JsonResponse
    {
        $model = $this->resolve($request, $service);
        $this->api->assertTokenScope($request, ServiceActionCommand::permissionFor($action));

        return $this->ok($preview->of($model, $action, (array) $request->input('params', [])));
    }

    public function action(Request $request, string $service, ?string $action = null): JsonResponse
    {
        $model = $this->resolve($request, $service);
        $data = $request->validate(['action' => [$action === null ? 'required' : 'nullable', 'string', 'in:'.implode(',', ServiceActionWorkflow::ACTIONS)], 'params' => ['nullable', 'array'], 'reason' => ['nullable', 'string', 'max:250'], 'confirm' => ['nullable', 'string', 'size:64']]);
        $action ??= $data['action'];
        $params = (array) ($data['params'] ?? []);
        if ($action === 'power') {
            $params['power_action'] = $params['power_action'] ?? $request->input('power_action', $request->input('signal'));
        }
        if ($data['reason'] ?? null) {
            $params['reason'] = $data['reason'];
        }
        if (($data['confirm'] ?? null) !== null) { // a confirmation that no longer describes what would happen is refused, not carried out (H414)
            app(DestructivePreview::class)->assertFresh($model, $action, $params, (string) $data['confirm']);
        }
        $organization = Organization::query()->find($model->organization_id);

        return $this->dispatch(new ServiceActionCommand($model->organization_id, $this->idempotencyKey($request, "service.{$action}"), ['service_id' => $model->id, 'project_id' => $model->project_id, 'action' => $action, 'params' => $params]), $this->api->context($request, $organization, $data['reason'] ?? null), 202);
    }

    /**
     * A binary file for a game server (audit §5r-3): the multipart upload is staged on the file store, scanned
     * (§5r-4 — an infected file is refused and deleted), then `gfile.upload` sends it to the daemon through the
     * panel's signed URL like every other audited action.
     */
    public function uploadFile(Request $request, FileStore $files, VirusScanner $scanner, string $service): JsonResponse
    {
        $model = $this->resolve($request, $service);
        $max = max(1, (int) config('onhost.game.upload_max_mb', 100)) * 1024;
        $data = $request->validate(['file' => ['required', 'file', 'max:'.$max], 'directory' => ['nullable', 'string', 'max:500'], 'reason' => ['nullable', 'string', 'max:250']]);
        $upload = $data['file'];
        $tmp = 'game-uploads/tmp/'.Str::lower(Str::random(24));
        $stream = fopen($upload->getRealPath(), 'rb');
        $files->disk()->writeStream($tmp, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
        $scan = $scanner->scanPath($tmp);
        if (! $scanner->allows($scan['result'])) {
            $files->disk()->delete($tmp);
            UploadGuard::refused($scan, 'service', $model->id, (string) $upload->getClientOriginalName(), $this->api->context($request));
        }
        $organization = Organization::query()->find($model->organization_id);
        $params = ['directory' => (string) ($data['directory'] ?? '/'), 'name' => (string) $upload->getClientOriginalName(), 'tmp_path' => $tmp, 'size' => (int) $upload->getSize(), 'scan' => $scan['result']];

        return $this->dispatch(new ServiceActionCommand($model->organization_id, $this->idempotencyKey($request, 'service.gfile.upload:'.$tmp), ['service_id' => $model->id, 'project_id' => $model->project_id, 'action' => 'gfile.upload', 'params' => $params]), $this->api->context($request, $organization, $data['reason'] ?? null), 202);
    }

    public function consoleToken(Request $request, string $service): JsonResponse
    {
        $model = $this->resolve($request, $service);

        return $this->dispatch(new IssueConsoleTokenCommand($model->organization_id, 'console:'.$model->id.':'.now()->timestamp, ['service_id' => $model->id]), $this->api->context($request, Organization::query()->find($model->organization_id)));
    }

    public function usage(Request $request, ServiceService $services, string $service): JsonResponse
    {
        $model = $this->resolve($request, $service);
        $usage = $services->usage($model);

        // the plan's total (files + databases + mail) as the usage watch last stored it (TASK-0023)
        return response()->json(['data' => ['metrics' => $usage->metrics, 'observed_at' => $usage->observedAt, 'disk_total' => WebDiskTotal::held($model)]]);
    }

    public function operations(Request $request, string $service): JsonResponse
    {
        $model = $this->resolve($request, $service);

        $manages = $this->api->can($request, 'service.manage', CommandScope::resource($model->id, $model->organization_id, $model->project_id));

        return $this->api->paginate($request, Operation::query()->where('service_id', $model->id), fn (Operation $o) => Presenters::operation($o, false, $manages), 'queued_at');
    }

    public function backups(Request $request, string $service): JsonResponse
    {
        $model = $this->resolve($request, $service, 'backup.read');

        return $this->api->paginate($request, Backup::query()->where('service_id', $model->id), fn (Backup $b) => ['id' => $b->id, 'kind' => $b->kind, 'state' => $b->state, 'size_bytes' => $b->size_bytes, 'started_at' => $b->started_at?->toIso8601String(), 'finished_at' => $b->finished_at?->toIso8601String(), 'verified_at' => $b->verified_at?->toIso8601String(), 'verify_status' => $b->verify_status, 'protected' => (bool) $b->protected, 'offsite' => (bool) $b->offsite, 'retention_until' => $b->retention_until?->toIso8601String(), 'restorable' => $b->state === 'completed' && $b->remote_id !== null]);
    }

    /** The declarative spec of a web service (audit §5e-6): every configurable section as one document. */
    public function spec(Request $request, ServiceSpecService $specs, string $service): JsonResponse
    {
        $model = $this->resolve($request, $service);

        return response()->json(['data' => $specs->current($model)]);
    }

    /** Converges the service on the document: only the sections given, only the actions that change something. */
    public function applySpec(Request $request, string $service): JsonResponse
    {
        $model = $this->resolve($request, $service, 'service.manage');
        $request->validate(['spec' => ['required', 'array'], 'spec.php' => ['nullable', 'string', 'max:10'], 'spec.proxies' => ['nullable', 'array', 'max:20'], 'spec.index' => ['nullable', 'array', 'max:12'], 'spec.redirect' => ['nullable'], 'spec.security' => ['nullable', 'array'], 'spec.cron' => ['nullable', 'array', 'max:50'], 'spec.monitoring' => ['nullable'],
            'spec.name' => ['nullable', 'string', 'max:60'], 'spec.image' => ['nullable', 'string', 'max:200'], 'spec.variables' => ['nullable', 'array', 'max:60'], 'spec.schedules' => ['nullable', 'array', 'max:30'], 'spec.firewall' => ['nullable'],
            'spec.forwards' => ['nullable', 'array', 'max:200'], 'spec.catchall' => ['nullable'], 'spec.aliases' => ['nullable', 'array', 'max:200'],
            'spec.mailboxes' => ['nullable', 'array', 'max:200'], 'spec.autoresponders' => ['nullable', 'array', 'max:200'], 'spec.spam' => ['nullable', 'array', 'max:200']]);
        $spec = (array) $request->input('spec', []); // the whole document (unknown sections are refused by the service, not silently dropped)

        return $this->dispatch(new WebToolsCommand($model->organization_id, $this->idempotencyKey($request, 'service.spec'), ['service_id' => $model->id, 'op' => 'spec.apply', 'params' => ['spec' => $spec]]), $this->api->context($request, Organization::query()->find($model->organization_id)));
    }

    /** A one-time, signed link on which the mailbox user sets their own password (audit §5i-5); the account owner passes it on. */
    public function mailboxPasswordLink(Request $request, MailboxPasswordLinks $links, ServiceFeatures $features, string $service): JsonResponse
    {
        $model = $this->resolve($request, $service, 'service.manage');
        $data = $request->validate(['remote_id' => ['required', 'string', 'max:120']]);
        $mailbox = collect($features->resources($model, 'mailboxes'))->firstWhere('remote_id', (string) $data['remote_id']);
        if ($mailbox === null) {
            throw DomainError::notFound('mailbox');
        }

        return response()->json(['data' => $links->create($model, (string) $data['remote_id'], (string) $mailbox['address'], $this->api->context($request, Organization::query()->find($model->organization_id)))], 201);
    }

    /** The chargeback state of a service: the open or last request, the share in force and what a cancellation now would return. */
    public function chargeback(Request $request, ChargebackService $chargebacks, string $service): JsonResponse
    {
        $model = $this->resolve($request, $service);
        $latest = $chargebacks->latest($model);

        return response()->json(['data' => ['request' => $latest ? $chargebacks->present($latest) : null, 'estimate' => $chargebacks->estimate($model), 'percent' => $chargebacks->percent()]]);
    }

    /** The customer asks support to leave the service early with part of the unused period returned as credit. */
    public function requestChargeback(Request $request, string $service): JsonResponse
    {
        $model = $this->resolve($request, $service, 'service.manage');
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);

        return $this->dispatch(new ChargebackCommand($model->organization_id, $this->idempotencyKey($request, "chargeback.request:{$model->id}"), ['op' => 'request', 'service_id' => $model->id, 'reason' => $data['reason']]), $this->api->context($request, Organization::query()->find($model->organization_id)), 201);
    }

    /** After the approval: the service terminates and the agreed share comes back as credit (a fresh step-up is required). */
    public function cancelWithChargeback(Request $request, string $service): JsonResponse
    {
        $model = $this->resolve($request, $service, 'service.manage');

        return $this->dispatch(new ChargebackCommand($model->organization_id, $this->idempotencyKey($request, "chargeback.cancel:{$model->id}"), ['op' => 'cancel', 'service_id' => $model->id]), $this->api->context($request, Organization::query()->find($model->organization_id)), 202);
    }

    /** The customer moves a scheduled migration inside the window staff gave (audit §5h-3). */
    public function migrationWindow(Request $request, string $service): JsonResponse
    {
        $model = $this->resolve($request, $service, 'service.manage');
        $data = $request->validate(['starts_at' => ['required', 'date']]);

        return $this->dispatch(new WebToolsCommand($model->organization_id, $this->idempotencyKey($request, 'service.migration:'.now()->format('YmdHi')), ['service_id' => $model->id, 'op' => 'migration.window', 'params' => $data]), $this->api->context($request, Organization::query()->find($model->organization_id)));
    }

    /**
     * Automation policy of one service: `auto_upgrade` lets the usage watch order the next plan from credit at 95 % (audit §5e-1),
     * `availability_alerts` (on unless switched off) mails the customer when a server stops on its own (H14).
     */
    public function policy(Request $request, string $service): JsonResponse
    {
        $model = $this->resolve($request, $service, 'service.manage');
        $data = $request->validate(['auto_upgrade' => ['required_without:availability_alerts', 'boolean'], 'availability_alerts' => ['required_without:auto_upgrade', 'boolean']]);

        return $this->dispatch(new WebToolsCommand($model->organization_id, $this->idempotencyKey($request, 'service.policy'), ['service_id' => $model->id, 'op' => 'policy.set', 'params' => $data]), $this->api->context($request, Organization::query()->find($model->organization_id)));
    }

    /** The plans this service may move to, priced per its billing period, with the pro-rated cost of changing now. */
    public function plans(Request $request, PlanChangeService $planChanges, string $service): JsonResponse
    {
        $model = $this->resolve($request, $service);
        $organization = Organization::query()->findOrFail($model->organization_id);

        return response()->json(['data' => $planChanges->options($model, (string) $organization->currency)]);
    }

    /** What the customer can do with this service: feature catalogue + the actions accepted right now (never the vendor). */
    /** SSH keys on the shell accounts of a site (H185): fingerprint, owner, and whether a revocation is still open. Keys are taken off with the `shell.key` action. */
    public function sshKeys(Request $request, SshKeyLedger $keys, string $service): JsonResponse
    {
        $rows = $keys->forService($this->resolve($request, $service));

        return response()->json(['data' => ['keys' => $rows, 'pending_revocations' => count(array_filter($rows, fn (array $r) => $r['state'] === SshKeyGrant::REVOKING))]]);
    }

    public function features(Request $request, ServiceFeatures $features, string $service): JsonResponse
    {
        $model = $this->resolve($request, $service);

        // what THIS person can do with it, not only what the service offers: a read-only collaborator is shown no button they cannot press (H412, H413)
        $actor = $request->user();

        return response()->json(['data' => ['features' => $features->features($model, $actor), 'actions' => $features->actions($model, $actor)]]);
    }

    /** "Is it all right?" from the platform's own records: state, backup, certificate, monitoring, failed operations, limits (ServiceHealthCheck). Anybody who may see the service may ask; nothing about money is in it. */
    public function health(Request $request, ServiceHealthCheck $health, string $service): JsonResponse
    {
        return response()->json(['data' => $health->run($this->resolve($request, $service))]);
    }

    /** Live listing of one resource kind (databases, ftp, cron, subdomains, certificate, redirect, php, snapshots, mailboxes, aliases, dkim, firewall). */
    public function resources(Request $request, ServiceFeatures $features, string $service, string $kind): JsonResponse
    {
        // a listing is diagnostics; a listing with its passwords revealed is access (H334)
        $model = $this->resolve($request, $service, $request->boolean('reveal') ? 'service.manage' : 'service.read');

        return response()->json(['data' => $features->resources($model, $kind, $request->boolean('fresh'), ['path' => (string) $request->query('path', ''), 'remote_id' => (string) $request->query('remote_id', ''), 'hours' => max(1, min(720, (int) $request->query('hours', 24))), 'secrets' => $this->api->can($request, 'service.manage', CommandScope::resource($model->id, $model->organization_id, $model->project_id)), 'reveal' => $request->boolean('reveal')]), 'kind' => $kind]);
    }

    /** File manager download (aaPanel-backed sites): the file streams through the control plane, never a panel URL. */
    public function fileDownload(Request $request, ServiceFeatures $features, string $service): Response
    {
        $model = $this->resolve($request, $service, 'service.manage'); // file contents hold the site's credentials (wp-config.php, .env): who may write files may read them, a read-only role may not (H334)
        $data = $request->validate(['path' => ['required', 'string', 'max:500']]);
        $content = $features->fileContents($model, $data['path']);
        if (strlen($content) > 20 * 1024 * 1024) {
            throw new DomainError('file_too_large', 'Files over 20 MB are downloaded over SFTP.', 422);
        }
        $name = basename(str_replace('\\', '/', $data['path']));
        if ($request->boolean('json')) { // the panel's inline editor (text files up to 512 kB)
            if (strlen($content) > 512 * 1024) {
                throw new DomainError('file_too_large', 'Files over 512 kB are edited over SFTP.', 422);
            }

            return response()->json(['data' => ['path' => $data['path'], 'name' => $name, 'size' => strlen($content), 'content' => $content]]);
        }

        return response($content, 200, ['Content-Type' => 'application/octet-stream', 'Content-Disposition' => 'attachment; filename="'.addslashes($name).'"', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store']);
    }

    public function logs(Request $request, ServiceFeatures $features, string $service): JsonResponse
    {
        $model = $this->resolve($request, $service);
        $data = $request->validate(['log' => ['nullable', 'in:access,error'], 'lines' => ['nullable', 'integer', 'min:10', 'max:1000']]);

        return response()->json(['data' => ['log' => $data['log'] ?? 'access', 'lines' => $features->logs($model, $data['log'] ?? 'access', (int) ($data['lines'] ?? 200))]]);
    }

    private function resolve(Request $request, string $id, string $permission = 'service.read'): Service
    {
        $service = Service::query()->find($id);
        if ($service === null) {
            throw DomainError::notFound('service');
        }
        $this->api->authorize($request, $permission, CommandScope::resource($service->id, $service->organization_id, $service->project_id)); // a project role covers the services of that project

        return $service;
    }
}
