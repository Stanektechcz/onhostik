<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

interface GameProvider extends BackupCapable, ConsoleCapable, InfrastructureProvider, PowerCapable
{
    /** @return list<array{id:int,ip:string,port:int,alias:string|null}> unassigned allocations on a node */
    public function freeAllocations(int $nodeId, ?int $port = null): array;

    /** Ensure a panel user exists for the customer (never a root admin). @return array{remote_id:string,created:bool} */
    public function ensureUser(string $email, string $displayName, string $externalId): array;

    public function sendCommand(ResourceRef $server, string $command): ProviderResult;

    /** @param array{name:string,cron:string,actions:list<array{action:string,payload:string}>} $schedule */
    public function createSchedule(ResourceRef $server, array $schedule): ProviderResult;

    /** @return list<array{id:int,name:string,memory:int,disk:int,allocated_memory:int,allocated_disk:int,maintenance:bool}> */
    public function listNodes(): array;

    /** @return array<string,mixed> egg definition incl. docker images, startup, variables */
    public function eggDefinition(int $nestId, int $eggId): array;
}
