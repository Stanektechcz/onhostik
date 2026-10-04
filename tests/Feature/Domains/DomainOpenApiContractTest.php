<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Yaml\Yaml;

/*
 * TASK-0066: the contract says what the domain writes of TASK-0056/0058 need and how they refuse. `POST /domains/transfer-in`
 * takes the paid order line (`order_item_id`) and refuses with its own slugs; a cart line that neither registers nor transfers
 * is refused with `domain_action_invalid` wherever a cart is priced. The holder route is in the contract like every other route.
 * The generator (`php artisan onhost:openapi`) writes all of it, so a deploy that regenerates the file keeps it.
 */

/** @return array<string, mixed> the contract as the generator writes it now */
function domainOpenApiFresh(): array
{
    $relative = 'storage/framework/testing/openapi-'.uniqid().'.yaml';
    Artisan::call('onhost:openapi', ['--out' => $relative]);
    $path = base_path($relative);
    try {
        return Yaml::parseFile($path);
    } finally {
        @unlink($path);
    }
}

/** @return list<string> the error slugs a response documents */
function domainOpenApiErrors(array $operation, string $status): array
{
    return (array) data_get($operation, "responses.{$status}.content.application/json.schema.allOf.1.properties.error.enum", []);
}

it('documents the holder route and the paid order line of a transfer', function () {
    $paths = domainOpenApiFresh()['paths'];

    expect($paths)->toHaveKey('/domains/{domain}/holder')
        ->and($paths['/domains/{domain}/holder']['post']['operationId'])->toBe('domainHolder');
    $body = data_get($paths, '/domains/transfer-in.post.requestBody.content.application/json.schema');
    expect($body['properties'])->toHaveKeys(['fqdn', 'order_item_id', 'auth_info', 'consent'])
        ->and($body['required'])->toContain('fqdn', 'auth_info', 'consent')->not->toContain('order_item_id');
    $holder = data_get($paths, '/domains/{domain}/holder.post.requestBody.content.application/json.schema');
    expect($holder['properties'])->toHaveKeys(['email', 'phone', 'street', 'city', 'postal_code', 'country']);
});

it('names the refusals of a transfer-in by their error slugs', function () {
    $transfer = domainOpenApiFresh()['paths']['/domains/transfer-in']['post'];

    expect(domainOpenApiErrors($transfer, '422'))->toContain('transfer_needs_order', 'transfer_order_mismatch')
        ->and(domainOpenApiErrors($transfer, '409'))->toContain('transfer_order_not_paid', 'transfer_already_submitted', 'transfer_line_closed')
        ->and($transfer['responses']['409']['description'])->toContain('transfer_already_submitted');
});

it('names domain_action_invalid wherever a cart is priced', function (string $path) {
    $operation = domainOpenApiFresh()['paths'][$path]['post'];

    expect(domainOpenApiErrors($operation, '422'))->toContain('domain_action_invalid');
})->with(['/cart/quote', '/orders', '/checkout/guest']);

it('keeps the committed contract regenerated for these routes', function () {
    $committed = Yaml::parseFile(base_path('contracts/openapi/onhost-v1.yaml'))['paths'];

    expect($committed)->toHaveKey('/domains/{domain}/holder')
        ->and(domainOpenApiErrors($committed['/domains/transfer-in']['post'], '422'))->toContain('transfer_needs_order');
});
