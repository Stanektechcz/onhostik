<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

interface ComputeProvider extends BackupCapable, ConsoleCapable, InfrastructureProvider, PowerCapable
{
    /** @return list<array{node:string,status:string,cpu:float|null,maxcpu:int|null,mem:int|null,maxmem:int|null,disk:int|null,maxdisk:int|null,uptime:int|null}> */
    public function clusterNodes(): array;

    /** @return list<array<string,mixed>> guests visible to the token (vmid, node, name, status, cpus, maxmem, maxdisk, tags) */
    public function listGuests(): array;

    public function reserveVmid(): int;

    public function snapshot(ResourceRef $vm, string $name, ?string $description = null): ProviderResult;

    public function rollback(ResourceRef $vm, string $name): ProviderResult;

    public function deleteSnapshot(ResourceRef $vm, string $name): ProviderResult;

    /** @return list<array{name:string,description:string|null,created_at:string|null,parent:string|null}> */
    public function listSnapshots(ResourceRef $vm): array;

    /** @param array<string,mixed> $config */
    public function applyConfig(ResourceRef $vm, array $config): ProviderResult;

    /** @param array{user?:string,password?:string,sshkeys?:list<string>,ipconfig0?:string,nameserver?:string} $cloudInit */
    public function applyCloudInit(ResourceRef $vm, array $cloudInit): ProviderResult;

    /** @param list<array{action:string,type:string,proto?:string,dport?:string,source?:string,enable?:bool,comment?:string}> $rules */
    public function applyFirewall(ResourceRef $vm, array $rules, bool $enabled = true): ProviderResult;

    public function guestAgentPing(ResourceRef $vm): bool;

    /**
     * Rescue media the node offers: the images a customer may boot when their own system will not start (H233).
     *
     * @return list<array{volume:string,name:string,size_bytes:int}>
     */
    public function listIsoImages(string $node): array;

    /**
     * What the VM boots from right now — the attached image and the boot order — so the platform can put back exactly
     * what it found when the rescue session ends.
     *
     * @return array{iso:string|null, boot:string}
     */
    public function bootMedia(ResourceRef $vm): array;

    /**
     * Attaches an image and/or sets the boot order. A null volume detaches the drive; a null order leaves it alone.
     * The change reaches the running guest only at its next start, so the caller reboots.
     */
    public function setBootMedia(ResourceRef $vm, ?string $volume, ?string $bootOrder = null): ProviderResult;

    /** Moves the VM to another node of the same cluster (live when it runs, offline otherwise); the async handle is the migration task. */
    public function migrate(ResourceRef $vm, string $targetNode, bool $online = true): ProviderResult;
}
