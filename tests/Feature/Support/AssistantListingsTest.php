<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Support\Assistant\AssistantService;

/*
 * The chat is meant to work with everything the customer can see about their own services, and the list of what it
 * may read was written once and then stood still while the platform learned to serve more: a web hosting now holds
 * mail, and the customer asking „jak si nastavím poštu do Outlooku“ was told the listing does not exist here. The
 * two lists below are now a decision that has to be made for every listing the platform serves — a new kind is in
 * one of them or the suite is red.
 */

it('has decided, for every listing the platform serves, whether the chat may answer with it', function () {
    $readable = array_values(array_unique(array_merge(...array_values(AssistantService::READABLE))));
    $undecided = array_values(array_diff(ServiceFeatures::RESOURCES, $readable, array_keys(AssistantService::NOT_READABLE)));

    expect($undecided)->toBe([], 'put each of these in AssistantService::READABLE (for the families it fits) or in NOT_READABLE with the reason: '.implode(', ', $undecided));
});

it('keeps both lists about listings that really exist', function () {
    $readable = array_values(array_unique(array_merge(...array_values(AssistantService::READABLE))));
    $known = [...ServiceFeatures::RESOURCES, 'backups']; // backups are the platform's own, not the panel's

    expect(array_diff($readable, $known))->toBe([])
        ->and(array_diff(array_keys(AssistantService::NOT_READABLE), $known))->toBe([])
        ->and(array_filter(AssistantService::NOT_READABLE, fn (string $why) => trim($why) === ''))->toBe([]);
});

it('answers a web hosting customer asking how to set up their mail', function () {
    Http::preventStrayRequests();
    Http::fake(fn (Request $request) => Http::response(['code' => 'ok', 'message' => '', 'response' => match ((string) parse_url($request->url(), PHP_URL_QUERY)) {
        'login' => 'sess-al',
        default => [],
    }]));
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');

    $settings = app(ServiceFeatures::class)->resources($service, 'mail_access');

    expect(in_array('mail_access', AssistantService::READABLE['web'], true))->toBeTrue()
        ->and($settings['imap']['port'] ?? null)->toBe(993)
        ->and($settings['smtp']['port'] ?? null)->toBe(587);
});
