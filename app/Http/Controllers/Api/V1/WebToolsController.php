<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Services\Commands\WebToolsCommand;
use Onhost\Domain\Services\FinalArchive;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\DeploySource;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Services\UsageGuard;
use Onhost\Domain\Services\Web\DeployService;
use Onhost\Domain\Services\Web\UptimeMonitor;
use Onhost\Domain\Services\Web\WebFileStore;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;
use Symfony\Component\HttpFoundation\Response;

/**
 * Web toolkit endpoints that are not plain service actions: uploads and downloads that stream through the control
 * plane, uptime monitors, git deploy configuration and its public webhook, the backup schedule, and the staff
 * single sign-on into the customer's panel. Writes go through WebToolsCommand; node work stays in the workflows.
 */
final class WebToolsController extends ApiController
{
    // ── files ──────────────────────────────────────────────────────────────────────────────────────────

    /** Stage an archive or dump for a later action (database.import, import.run). */
    public function upload(Request $request, WebFileStore $files, string $service): JsonResponse
    {
        $model = $this->resolve($request, $service, 'service.manage');
        $max = (int) config('onhost.web_tools.upload_max_bytes', 2 * 1024 * 1024 * 1024);
        $request->validate(['file' => ['required', 'file', 'max:'.intdiv($max, 1024)]]);
        $file = $request->file('file');
        $id = $files->putUpload($model, (string) $file->getRealPath(), (string) $file->getClientOriginalName());

        return $this->ok(['upload_id' => $id, 'name' => $file->getClientOriginalName(), 'size' => $file->getSize(), 'expires_in_hours' => 6]);
    }

    /** File manager upload straight into the site (one file, up to the plan's upload limit). */
    public function fileUpload(Request $request, ServiceFeatures $features, AuditRecorder $audit, string $service): JsonResponse
    {
        $model = $this->resolve($request, $service, 'service.manage');
        $max = (int) config('onhost.web_tools.upload_max_bytes', 2 * 1024 * 1024 * 1024);
        $data = $request->validate(['file' => ['required', 'file', 'max:'.intdiv($max, 1024)], 'path' => ['nullable', 'string', 'max:500']]);
        $feat = $features->features($model);
        if (empty($feat['files']['enabled']) && empty($feat['file_manager']['enabled'])) {
            throw new DomainError('feature_unavailable', 'The file manager is not available for this service.', 422);
        }
        $dir = trim(str_replace('\\', '/', (string) ($data['path'] ?? '')), '/');
        if (str_contains($dir, '..')) {
            throw new DomainError('action_param_invalid', 'path must stay inside the site root.', 422, ['field' => 'path']);
        }
        UsageGuard::assertRoomFor($model, 'file.save'); // a site with no room left does not take more files
        $file = $request->file('file');
        $name = preg_replace('/[^\w.\-() ]+/u', '_', (string) $file->getClientOriginalName()) ?: 'upload.bin';
        $target = ($dir !== '' ? $dir.'/' : '').$name;
        [$tools, $ref] = $features->toolsFor($model);
        $tools->transport($ref)->upload($target, (string) $file->getRealPath());
        $audit->record($this->api->context($request)->withScope($model->organization_id), 'service.file.upload', 'succeeded', ['path' => $target, 'size' => $file->getSize()], 'service', $model->id);

        return $this->ok(['path' => $target, 'name' => $name, 'size' => $file->getSize()]);
    }

    /** One-time download of an export prepared by an action (database.export …). */
    public function download(Request $request, WebFileStore $files, string $service, string $token): Response
    {
        $model = $this->resolve($request, $service, 'backup.download'); // a database dump is the customer's data leaving the platform (H344)
        $entry = $files->download($model, $token);
        if ($entry === null) {
            throw new DomainError('download_expired', 'The download is no longer available; export again.', 410);
        }

        return response()->download($entry['path'], $entry['name'], ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store'])->deleteFileAfterSend(true);
    }

    /** Stream a backup archive from the node. */
    public function backupDownload(Request $request, ServiceFeatures $features, AuditRecorder $audit, string $service, string $backup): Response
    {
        $model = $this->resolve($request, $service, 'backup.download'); // seeing the list (`backup.read`) and starting a backup (`service.manage`) do not take the data away (H344)
        $row = Backup::query()->where('service_id', $model->id)->find($backup);
        if ($row === null) {
            throw DomainError::notFound('backup');
        }
        if ($row->kind === 'final') { // the archive of a cancelled service has a door of its own (fee, waiver, audit): ServiceArchiveService
            throw DomainError::notFound('backup');
        }
        if ($row->state === 'completed' && (string) $row->remote_id === '' && FinalArchive::isSet($row)) { // the platform's own set: files, every database dump, checksums and a readme in one zip
            $archives = app(FinalArchive::class);
            $package = $archives->package($row);
            $audit->record($this->api->context($request)->withScope($model->organization_id), 'service.backup.download', 'succeeded', ['backup_id' => $row->id, 'bytes' => $package['bytes'], 'set' => true], 'service', $model->id);

            return $archives->disk()->download($package['path'], 'backup-'.$model->id.'-'.($row->started_at?->format('Ymd-His') ?? $row->id).'.zip', ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store']);
        }
        if ($row->state !== 'completed' || (string) $row->remote_id === '') {
            throw new DomainError('backup_not_downloadable', 'Only completed backups can be downloaded.', 409, ['state' => $row->state]);
        }
        $enabled = $features->features($model);
        if ($model->family === 'game' && ! empty($enabled['backup_tools']['enabled'])) { // game panels hand out a short-lived signed URL of the archive; the browser fetches it directly
            $url = $features->gameTools($features->adapterFor($model))->backupDownloadUrl($features->refFor($model), (string) $row->remote_id);
            $audit->record($this->api->context($request)->withScope($model->organization_id), 'service.backup.download', 'succeeded', ['backup_id' => $row->id, 'signed' => true], 'service', $model->id);

            return redirect()->away($url, 302, ['Cache-Control' => 'no-store']);
        }
        if (empty($enabled['backup_download']['enabled'])) {
            throw new DomainError('feature_unavailable', 'Backup download is not available for this service.', 422);
        }
        [$tools, $ref] = $features->toolsFor($model);
        $local = (string) tempnam(sys_get_temp_dir(), 'ohbk');
        $tools->downloadBackup($ref, (string) $row->remote_id, $local);
        $name = 'backup-'.$model->id.'-'.($row->started_at?->format('Ymd-His') ?? $row->id).'.'.((string) data_get($row->meta, 'extension', 'tar.gz'));
        $audit->record($this->api->context($request)->withScope($model->organization_id), 'service.backup.download', 'succeeded', ['backup_id' => $row->id], 'service', $model->id);

        return response()->download($local, $name, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store'])->deleteFileAfterSend(true);
    }

    // ── monitoring ─────────────────────────────────────────────────────────────────────────────────────

    public function monitoring(Request $request, UptimeMonitor $monitor, string $service): JsonResponse
    {
        $model = $this->resolve($request, $service);

        return $this->ok(array_merge($monitor->status($model), ['samples' => $monitor->samples($model, max(1, min(720, (int) $request->query('hours', 24))))]));
    }

    public function setMonitoring(Request $request, string $service): JsonResponse
    {
        $model = $this->resolve($request, $service, 'service.manage');
        $data = $request->validate(['id' => ['nullable', 'string', 'max:40'], 'name' => ['nullable', 'string', 'max:80'], 'url' => ['nullable', 'string', 'max:500'], 'interval_seconds' => ['nullable', 'integer', 'min:60', 'max:3600'], 'keyword' => ['nullable', 'string', 'max:120'], 'notify' => ['nullable', 'boolean'], 'enabled' => ['nullable', 'boolean'], 'timeout_seconds' => ['nullable', 'integer', 'min:3', 'max:60']]);

        return $this->dispatch(new WebToolsCommand($model->organization_id, $this->idempotencyKey($request, 'tools.monitoring.set'), ['service_id' => $model->id, 'op' => 'monitoring.set', 'params' => $data]), $this->api->context($request));
    }

    public function deleteMonitoring(Request $request, string $service, string $monitor): JsonResponse
    {
        $model = $this->resolve($request, $service, 'service.manage');

        return $this->dispatch(new WebToolsCommand($model->organization_id, $this->idempotencyKey($request, 'tools.monitoring.delete'), ['service_id' => $model->id, 'op' => 'monitoring.delete', 'params' => ['id' => $monitor]]), $this->api->context($request));
    }

    // ── git deploy ─────────────────────────────────────────────────────────────────────────────────────

    public function deploy(Request $request, DeployService $deploy, string $service): JsonResponse
    {
        $model = $this->resolve($request, $service);

        $manages = $this->api->can($request, 'service.manage', CommandScope::resource($model->id, $model->organization_id, $model->project_id)); // a read-only role sees the names of the build environment, not its values (H334)

        return $this->ok(array_merge($deploy->status($model, $manages), ['deployments' => $deploy->deployments($model)]));
    }

    public function configureDeploy(Request $request, string $service): JsonResponse
    {
        $model = $this->resolve($request, $service, 'service.manage');
        $data = $request->validate(['repository' => ['required', 'string', 'max:250'], 'branch' => ['nullable', 'string', 'max:120'], 'build_command' => ['nullable', 'string', 'max:500'], 'deploy_path' => ['nullable', 'string', 'max:200'], 'env' => ['nullable', 'array'], 'hooks' => ['nullable', 'array', 'max:10'], 'hooks.*' => ['string', 'max:2000'], 'auto_deploy' => ['nullable', 'boolean'], 'keep_releases' => ['nullable', 'integer', 'min:2', 'max:20']]);

        return $this->dispatch(new WebToolsCommand($model->organization_id, $this->idempotencyKey($request, 'tools.deploy.configure'), ['service_id' => $model->id, 'op' => 'deploy.configure', 'params' => $data]), $this->api->context($request));
    }

    public function rotateDeploySecret(Request $request, string $service): JsonResponse
    {
        $model = $this->resolve($request, $service, 'service.manage');

        return $this->dispatch(new WebToolsCommand($model->organization_id, $this->idempotencyKey($request, 'tools.deploy.rotate'), ['service_id' => $model->id, 'op' => 'deploy.rotate_secret']), $this->api->context($request));
    }

    public function disconnectDeploy(Request $request, string $service): JsonResponse
    {
        $model = $this->resolve($request, $service, 'service.manage');

        return $this->dispatch(new WebToolsCommand($model->organization_id, $this->idempotencyKey($request, 'tools.deploy.disconnect'), ['service_id' => $model->id, 'op' => 'deploy.disconnect']), $this->api->context($request));
    }

    /** Public: a push notification from GitHub / GitLab / any client that signs with the webhook secret. */
    public function hook(Request $request, DeployService $deploy, ServiceService $services, string $source): JsonResponse
    {
        $row = DeploySource::query()->find($source);
        if ($row === null) {
            throw DomainError::notFound('deploy source');
        }
        $payload = (string) $request->getContent();
        $signature = $request->header('X-Hub-Signature-256') ?? $request->header('X-Onhost-Signature');
        if ($signature === null && ($token = $request->header('X-Gitlab-Token')) !== null) {
            $signature = 'sha256='.hash_hmac('sha256', $payload, (string) $token); // GitLab sends the shared secret itself
        }
        $event = (string) ($request->header('X-GitHub-Event') ?? $request->header('X-Gitlab-Event') ?? 'push');
        $result = $deploy->webhook($row, $payload, $signature, $event, $services);

        return response()->json($result, $result['accepted'] ? 202 : 200);
    }

    // ── backups ────────────────────────────────────────────────────────────────────────────────────────

    public function setBackupSchedule(Request $request, string $service): JsonResponse
    {
        $model = $this->resolve($request, $service, 'service.manage');
        $data = $request->validate(['frequency' => ['nullable', 'string', 'max:10'], 'days' => ['nullable', 'integer', 'min:1', 'max:365'], 'generations' => ['nullable', 'integer', 'min:1', 'max:100'], 'offsite' => ['nullable', 'boolean']]);

        return $this->dispatch(new WebToolsCommand($model->organization_id, $this->idempotencyKey($request, 'tools.backup_schedule'), ['service_id' => $model->id, 'op' => 'backup_schedule.set', 'params' => $data]), $this->api->context($request));
    }

    // ── staff ──────────────────────────────────────────────────────────────────────────────────────────

    /** Staff single sign-on link into the customer's panel account (recorded; expires within a minute). */
    public function panelLogin(Request $request, ServiceFeatures $features, AuditRecorder $audit, string $service): JsonResponse
    {
        $this->api->authorize($request, 'staff.console', CommandScope::global());
        $model = Service::query()->find($service);
        if ($model === null) {
            throw DomainError::notFound('service');
        }
        [$tools, $ref] = $features->toolsFor($model);
        $url = $tools->panelLoginUrl($ref);
        if ($url === null) {
            throw new DomainError('panel_login_unavailable', 'This panel offers no staff login link; use the panel credentials from the instance settings.', 409);
        }
        $audit->record($this->api->context($request)->withScope($model->organization_id), 'staff.panel_login', 'succeeded', ['reason' => (string) $request->input('reason', '')], 'service', $model->id);

        return $this->ok(['url' => $url, 'expires_in_seconds' => 60]);
    }

    private function resolve(Request $request, string $id, string $permission = 'service.read'): Service
    {
        $service = Service::query()->find($id);
        if ($service === null) {
            throw DomainError::notFound('service');
        }
        $this->api->authorize($request, $permission, CommandScope::organization($service->organization_id));

        return $service;
    }
}
