<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * A hypervisor that keeps customers' own installation images (TASK-0110, owner decision G-R5) on a storage of their own — never
 * the operator's rescue storage, whose images every customer may boot (`ComputeProvider::listIsoImages`). The image is attached
 * and detached with `ComputeProvider::bootMedia()` / `setBootMedia()` like a rescue image; this contract only moves files.
 *
 * File names on the storage are the platform's (`CustomIso::remoteFilename()`), never a name a customer typed.
 */
interface CustomIsoCapable
{
    /** The storage customers' images go to, or null when the operator has not configured one (the feature is then off). */
    public function customIsoStorage(): ?string;

    /** The volume id an image of this file name has on the custom storage (`<storage>:iso/<file>`). */
    public function customIsoVolume(string $filename): string;

    /**
     * Whether the custom storage of this node already holds the volume (a shared storage holds it for every node).
     */
    public function hasCustomIso(string $node, string $volume): bool;

    /**
     * Uploads one image to the node's custom storage, verified by the hypervisor against its SHA-256. The async handle is the
     * hypervisor's import task: nothing is reported done before it says so.
     *
     * @param  resource  $stream
     */
    public function uploadCustomIso(string $node, string $filename, $stream, string $sha256): ProviderResult;

    /** Deletes the volume from the custom storage; a volume that is already gone counts as deleted. */
    public function deleteCustomIso(string $node, string $volume): ProviderResult;
}
