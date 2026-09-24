<?php

declare(strict_types=1);

use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\ServiceIdentityCheck;

/*
 * A web hosting binds more than its site. The mail domain it was given for its mailboxes is a resource of its own,
 * written by the same operation within the same second, and `primaryBinding()` ordered by `created_at` alone — so
 * which of the two came back was the database's choice. SQLite answered with the row written first; PostgreSQL was
 * free to answer with either, and did (two nightly runs of the whole suite failed on it).
 *
 * Everything that asks "which resource IS this service?" hangs on that answer: the proof before a deletion
 * (`ServiceIdentityCheck`, which then refused to archive a service it could not recognise), the reference an action
 * is sent to the panel with, the reconciler, the features the panel offers. A web service's primary binding is its
 * web resource — the service's own kind decides, not the order the rows happen to come back in.
 */

it('answers with the service\'s own resource, not with the mail domain bound beside it', function () {
    [, $org] = $this->customerWithOrganization();
    $site = featureWebService($org, 'ispconfig');
    $web = $site->bindings()->first();
    $mail = ProviderBinding::query()->create([
        'service_id' => $site->id, 'provider_instance_id' => $site->provider_instance_id, 'remote_type' => 'mail_domain', 'remote_id' => '7',
        'remote_node' => '1', 'meta' => ['domain' => 'shop.cz'], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "mail:{$site->id}", 'adapter_version' => '1.0.0',
    ]);
    // written before the site's own row: the strongest form of what the database was free to answer anyway
    $mail->forceFill(['created_at' => $web->created_at->copy()->subSecond()])->save();

    $primary = $site->fresh()->primaryBinding();
    expect($primary?->remote_type)->toBe('web_domain')
        ->and($primary?->remote_id)->toBe($web->remote_id);

    // and the consequence: the proof before a deletion recognises the service again
    $report = app(ServiceIdentityCheck::class)->verify($site->fresh());
    expect(collect($report['checks'])->firstWhere('key', 'binding_type')['ok'])->toBeTrue()
        ->and($report['failed'])->not->toContain('binding_type');
});
