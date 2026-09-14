<?php

declare(strict_types=1);

namespace Onhost\Platform\Commands;

final class CommandScope
{
    private function __construct(
        public readonly string $type,
        public readonly ?string $id,
        public readonly ?string $organizationId = null,
        public readonly ?string $projectId = null,
    ) {}

    public static function global(): self
    {
        return new self('global', null);
    }

    public static function organization(string $organizationId): self
    {
        return new self('organization', $organizationId, $organizationId);
    }

    public static function project(string $projectId, string $organizationId): self
    {
        return new self('project', $projectId, $organizationId, $projectId);
    }

    /** A resource scope carries the project it belongs to, so a project-scoped role covers the resources of that project. */
    public static function resource(string $resourceId, string $organizationId, ?string $projectId = null): self
    {
        return new self('resource', $resourceId, $organizationId, $projectId);
    }

    public function isGlobal(): bool
    {
        return $this->type === 'global';
    }
}
