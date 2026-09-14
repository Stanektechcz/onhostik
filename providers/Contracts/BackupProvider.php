<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/** Backup plane (PBS). Client identities can create/verify, never prune or delete (§12.1). */
interface BackupProvider extends ProviderAdapter
{
    /** @return list<array{datastore:string,namespace:string|null,backup_type:string,backup_id:string,backup_time:int,size:int|null,verification:array<string,mixed>|null,protected:bool}> */
    public function listSnapshots(string $datastore, ?string $namespace = null, ?string $backupId = null): array;

    public function verify(string $datastore, ?string $namespace, string $backupType, string $backupId, int $backupTime): ProviderResult;

    /** @return array{total:int,used:int,avail:int,gc_status:array<string,mixed>|null} */
    public function datastoreStatus(string $datastore): array;

    public function awaitStatus(AsyncHandle $handle): AsyncStatus;
}
