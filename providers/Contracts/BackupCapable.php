<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

interface BackupCapable
{
    /** @param array<string,mixed> $policy retention/mode/storage */
    public function backup(ResourceRef $ref, array $policy): ProviderResult;

    /** @return list<array{remote_id:string,created_at:string,size_bytes:int|null,verified:bool|null,protected:bool|null,meta:array<string,mixed>}> */
    public function listBackups(ResourceRef $ref): array;

    public function restore(ResourceRef $ref, string $backupRemoteId, array $options = []): ProviderResult;
}
