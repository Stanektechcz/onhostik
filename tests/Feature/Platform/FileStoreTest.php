<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Compliance\ComplianceService;
use Onhost\Domain\Compliance\Models\DataRequest;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Files\FileStore;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
 * One disk behind customer files (audit §5q-4): evidence and exports live on `ONHOST_FILES_DISK`; a download from an
 * S3-compatible disk is a short signed link, from the local disk a stream; the retention pass deletes old evidence
 * and orphaned exports.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('serves files as signed links from S3 and as streams from the local disk, and prunes by retention', function () {
    $files = app(FileStore::class);
    expect($files->diskName())->toBe('local');
    Storage::fake('local');
    Storage::disk('local')->put('marketplace-evidence/mo_1/202609/report-x.pdf', '%PDF-1.4 local');
    $stream = $files->download('marketplace-evidence/mo_1/202609/report-x.pdf', 'report.pdf', 'application/pdf');
    expect($stream)->toBeInstanceOf(StreamedResponse::class)->and($stream->headers->get('content-type'))->toBe('application/pdf')->and($stream->headers->get('content-disposition'))->toContain('report.pdf');

    // the S3 disk signs
    config()->set('onhost.storage.disk', 's3');
    config()->set('onhost.storage.signed_ttl_minutes', 5);
    $s3 = Storage::fake('s3');
    $s3->buildTemporaryUrlsUsing(fn (string $path, DateTimeInterface $expiration, array $options) => 'https://bucket.s3.test/'.$path.'?X-Amz-Expires='.($expiration->getTimestamp() - now()->getTimestamp()).'&ct='.rawurlencode((string) ($options['ResponseContentType'] ?? '')));
    $s3->put('marketplace-evidence/mo_1/202609/report-y.pdf', '%PDF-1.4 s3');
    expect($files->diskName())->toBe('s3');
    $link = $files->download('marketplace-evidence/mo_1/202609/report-y.pdf', 'report.pdf', 'application/pdf', ['X-Robots-Tag' => 'noindex']);
    expect($link)->toBeInstanceOf(RedirectResponse::class)->and($link->getStatusCode())->toBe(302)->and($link->getTargetUrl())->toBe('https://bucket.s3.test/marketplace-evidence/mo_1/202609/report-y.pdf?X-Amz-Expires=300&ct=application%2Fpdf')
        ->and($link->headers->get('cache-control'))->toContain('no-store')->and($link->headers->get('x-robots-tag'))->toBe('noindex');

    // the data export and the evidence download endpoints follow the disk
    [$owner, $org] = $this->customerWithOrganization();
    $compliance = app(ComplianceService::class);
    $request = DataRequest::query()->create(['organization_id' => $org->id, 'kind' => 'export', 'state' => 'ready', 'file_path' => 'exports/'.$org->id.'/dr_test.json', 'ready_at' => now(), 'expires_at' => now()->addDays(30), 'requested_by' => $owner->id, 'meta' => []]);
    $s3->put('exports/'.$org->id.'/dr_test.json', '{"ok":true}');
    $url = $compliance->downloadLink($request, $this->contextFor($owner, $org))['url'];
    $this->get($url)->assertRedirect()->assertHeader('location', 'https://bucket.s3.test/exports/'.$org->id.'/dr_test.json?X-Amz-Expires=300&ct=application%2Fjson');

    // retention: evidence older than the configured months goes, fresh evidence and live exports stay, an orphaned export goes
    config()->set('onhost.storage.evidence_retention_months', 12);
    $s3->put('marketplace-evidence/mo_2/202409/old.pdf', 'old');
    touch($s3->path('marketplace-evidence/mo_2/202409/old.pdf'), now()->subMonths(13)->getTimestamp());
    $s3->put('exports/'.$org->id.'/orphan.json', '{}');
    touch($s3->path('exports/'.$org->id.'/orphan.json'), now()->subDays(3)->getTimestamp());
    $s3->put('marketplace-evidence/tmp/keep.pdf', 'tmp');
    touch($s3->path('marketplace-evidence/tmp/keep.pdf'), now()->subMonths(20)->getTimestamp());
    Artisan::call('onhost:files:prune');
    expect(app(AutomationLedger::class)->last('files.prune')['stats'])->toMatchArray(['evidence_deleted' => 1, 'exports_deleted' => 1, 'disk' => 's3']);
    expect($s3->exists('marketplace-evidence/mo_2/202409/old.pdf'))->toBeFalse()->and($s3->exists('marketplace-evidence/mo_1/202609/report-y.pdf'))->toBeTrue()->and($s3->exists('exports/'.$org->id.'/dr_test.json'))->toBeTrue()->and($s3->exists('exports/'.$org->id.'/orphan.json'))->toBeFalse()->and($s3->exists('marketplace-evidence/tmp/keep.pdf'))->toBeTrue();
    expect(app(AutomationLedger::class)->rule('files.prune')['switchable'])->toBeTrue();
    expect(fn () => $compliance->redeemLink($request, 'nope'))->toThrow(DomainError::class);
    expect(CommandContext::system('test')->actorType)->toBe('system');
});
