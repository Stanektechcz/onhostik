<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Compliance\ComplianceService;
use Onhost\Domain\Compliance\Models\DataRequest;

/*
 * Signed export downloads (audit §5j-7): the export carries the audit trail, a signed link works without a session,
 * a tampered or superseded link is refused, and the link never outlives the export.
 */

beforeEach(fn () => Http::preventStrayRequests());

it('builds the export with the audit trail and serves it through a signed link that a new link revokes', function () {
    Storage::fake('local');
    [$owner, $org] = $this->customerWithOrganization();
    featureWebService($org, 'aapanel');
    $this->actingAs($owner, 'sanctum');
    $h = ['X-Organization' => $org->id];
    $id = $this->withHeaders($h)->postJson('/v1/data-requests', ['kind' => 'export'])->assertStatus(202)->json('data.id');
    $this->withHeaders($h + ['Idempotency-Key' => 'dl-0'])->postJson("/v1/data-requests/{$id}/link")->assertStatus(409); // not ready yet
    expect(app(ComplianceService::class)->processDataRequests()['exported'])->toBe(1);
    $archive = json_decode((string) Storage::disk('local')->get(DataRequest::query()->findOrFail($id)->file_path), true);
    expect($archive)->toHaveKey('audit')->and($archive['audit'])->not->toBeEmpty()->and($archive['audit'][0])->toHaveKeys(['action', 'result', 'created_at']);

    $link = $this->withHeaders($h + ['Idempotency-Key' => 'dl-1'])->postJson("/v1/data-requests/{$id}/link")->assertCreated()->json('data');
    expect($link['url'])->toContain("/export/{$id}/")->toContain('signature=');
    $this->flushHeaders();
    auth()->forgetGuards();
    $response = $this->get($link['url'])->assertOk();
    expect($response->streamedContent())->toContain('"format": "onhost-export/1"');
    $this->get(preg_replace('/signature=[a-f0-9]+/', 'signature=deadbeef', $link['url']))->assertStatus(403);
    $this->get(preg_replace('#/export/'.$id.'/[A-Za-z0-9]+#', "/export/{$id}/wrongtoken", $link['url']))->assertStatus(403); // the signature covers the token

    // a new link supersedes the old one; the link never outlives the export
    $this->actingAs($owner, 'sanctum');
    $second = $this->withHeaders($h + ['Idempotency-Key' => 'dl-2'])->postJson("/v1/data-requests/{$id}/link")->assertCreated()->json('data');
    $this->flushHeaders();
    auth()->forgetGuards();
    $this->get($link['url'])->assertStatus(410);
    $this->get($second['url'])->assertOk();
    $request = DataRequest::query()->findOrFail($id);
    expect(strtotime($second['expires_at']))->toBeLessThanOrEqual($request->expires_at->getTimestamp());
    $request->forceFill(['state' => 'completed', 'file_path' => null])->save();
    $this->get($second['url'])->assertStatus(410);
});
