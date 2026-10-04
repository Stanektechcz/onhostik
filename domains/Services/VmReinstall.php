<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Errors\DomainError;
use Onhost\Providers\Contracts\ComputeProvider;
use Throwable;

/**
 * A fresh operating system on a customer's own server (Phase C, C9).
 *
 * The audit found no way to reinstall a VPS: a broken system was a ticket and a hand-made clone in the hypervisor. The
 * reinstall is an ordinary service action now (`reinstall` on a `cloud` service, ServiceActionWorkflow), under the rules
 * every destructive action has — HIGH risk with a fresh step-up, a destructive preview, a protected snapshot first — and
 * one of its own: **only an image the platform allows**. That is an image the operator keeps a golden template for on
 * the instance (`ComputeProvider::reinstallImages()`) AND, when the product names the images it sells (`meta.images`),
 * one of those. A name the customer types is never a disk to copy.
 */
final class VmReinstall
{
    public function __construct(private readonly ProviderRegistry $providers) {}

    /**
     * The images this server may be reinstalled with, in the order the catalogue sells them.
     *
     * @return list<string>
     */
    public function images(Service $service): array
    {
        if ($service->family !== 'cloud') {
            return [];
        }
        $instance = $service->provider_instance_id ? ProviderInstance::query()->find($service->provider_instance_id) : null;
        try {
            $adapter = $instance === null ? null : $this->providers->forInstance($instance);
        } catch (Throwable) {
            return []; // no credentials for the instance yet: nothing can be offered, nothing breaks
        }

        return $adapter instanceof ComputeProvider ? self::allowed($service, $adapter->reinstallImages()) : [];
    }

    /**
     * What the operator keeps templates for, narrowed to what the product sells when it says so.
     *
     * @param  list<string>  $templates
     * @return list<string>
     */
    public static function allowed(Service $service, array $templates): array
    {
        $sold = array_values(array_filter((array) data_get(Product::query()->where('key', $service->product_key)->first()?->meta, 'images', []), 'is_string'));
        if ($sold === []) {
            return array_values($templates);
        }

        return array_values(array_filter($sold, fn (string $image) => in_array($image, $templates, true)));
    }

    /** The image a request names, refused unless it is allowed for this server. */
    public function assertImage(Service $service, mixed $image): string
    {
        $image = is_string($image) ? trim($image) : '';
        $allowed = $this->images($service);
        if ($image === '' || ! in_array($image, $allowed, true)) {
            throw new DomainError('action_param_invalid', 'reinstall: image must be one of the systems offered for this server ('.implode(', ', $allowed).').', 422, ['field' => 'image', 'allowed' => $allowed]);
        }

        return $image;
    }

    /** The size the new system disk grows to: what the plan sells, never less than nothing. */
    public static function diskGb(Service $service): int
    {
        return max(0, (int) (((array) $service->entitlements)['nvme_gb'] ?? 0));
    }
}
