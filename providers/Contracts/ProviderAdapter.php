<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * Common surface of every executor adapter (blueprint §5.4 + §41 DoD):
 * least-privilege credentials from SecretStore, contract tests per vendor version,
 * capability registry, health, normalized errors.
 */
interface ProviderAdapter
{
    /** Provider family key: proxmox | pbs | ispconfig | aapanel | pterodactyl | powerdns | wedos | kubernetes */
    public static function providerKey(): string;

    /** Adapter contract version (bumped when the mapping to the vendor API changes). */
    public static function adapterVersion(): string;

    /** Vendor versions this adapter is contract-tested against. @return list<string> */
    public static function supportedVendorVersions(): array;

    /** Capability map, e.g. ['vm.create' => true, 'vm.resize_disk_online' => 'conditional']. @return array<string, bool|string> */
    public function capabilities(): array;

    public function health(): ProviderHealth;

    /** Detected vendor version (cached), null when unreachable. */
    public function vendorVersion(): ?string;
}
