<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Onhost\Domain\Services\Commands\ServiceArchiveCommand;
use Onhost\Domain\Services\DeletionPolicy;
use Onhost\Domain\Services\FinalArchive;
use Onhost\Domain\Services\ServiceArchiveService;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;
use Symfony\Component\HttpFoundation\Response;

/**
 * Archives of cancelled services (audit §5ab): what we still hold, how long, and the two ways back — a free restore
 * onto a new paid service, or the archive as one compressed file for the fee staff set in the administration.
 */
final class ArchiveController extends ApiController
{
    public function index(Request $request, ServiceArchiveService $archives, DeletionPolicy $policy): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'backup.read', CommandScope::organization($organization->id));

        return $this->ok([
            'archives' => $archives->forOrganization($organization->id, (string) ($organization->currency ?? 'CZK')),
            'policy' => ['grace_days' => $policy->graceDays(), 'retention_days' => $policy->retentionDays(), 'download_fee_minor' => $policy->downloadFeeMinor((string) ($organization->currency ?? 'CZK')), 'currency' => (string) ($organization->currency ?? 'CZK')],
        ]);
    }

    /** Pays the fee (once per archive) and hands back a short-lived link to the compressed file. */
    public function download(Request $request, string $backup): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->assertTokenScope($request, 'backup.restore');
        $result = (array) $this->bus->dispatch(new ServiceArchiveCommand($organization->id, $this->idempotencyKey($request, 'archive.download:'.$backup), ['op' => 'download', 'backup_id' => $backup]), $this->api->context($request));

        return $this->ok($result + [
            'url' => URL::temporarySignedRoute('archive.file', now()->addMinutes(30), ['organization' => $organization->id, 'backup' => $backup]),
            'expires_in' => 1800,
        ]);
    }

    /** The signed link itself: streams the packaged archive, no session needed (the browser follows it directly). */
    public function file(Request $request, ServiceArchiveService $archives, FinalArchive $final, string $organization, string $backup): Response
    {
        if (! $request->hasValidSignature()) {
            throw new DomainError('link_expired', 'Odkaz ke stažení vypršel; vyžádejte si nový.', 410);
        }
        $row = $archives->archive($backup, $organization);
        if (! (bool) data_get($row->meta, 'download.paid', false)) {
            throw new DomainError('archive_not_paid', 'Stažení archivu není uhrazeno.', 402);
        }
        $package = (array) data_get($row->meta, 'download', []);
        $path = (string) ($package['path'] ?? '');
        $disk = Storage::disk((string) config('onhost.platform_backup.disk', 'local'));
        if ($path === '' || ! $disk->exists($path)) {
            $package = $final->package($row);
            $path = $package['path'];
        }

        return $disk->download($path, (string) ($package['filename'] ?? 'archive.zip'), ['Content-Type' => 'application/zip', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store']);
    }

    /** Free: put the archive back onto a new paid service of the same kind. */
    public function restore(Request $request, string $backup): JsonResponse
    {
        $organization = $this->api->organization($request);
        $data = $request->validate(['service_id' => ['required', 'string', 'max:40']]);

        return $this->dispatch(new ServiceArchiveCommand($organization->id, $this->idempotencyKey($request, 'archive.restore:'.$backup), ['op' => 'restore', 'backup_id' => $backup, 'service_id' => $data['service_id']]), $this->api->context($request));
    }
}
