<?php

declare(strict_types=1);

namespace Onhost\Domain\Content\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\GlobalCommand;

/**
 * Staff writes of the public content (I-R17 blog, I-R18 references), dispatched by `op`:
 *  post.create · post.update{id} · post.state{id, state} · post.delete{id} · post.upsert (the old PUT by slug);
 *  reference.create · reference.update{id} · reference.state{id, published} · reference.delete{id} · reference.order{ids}.
 * Permission `content.manage`, routine risk (public text, no money, no customer data). The bus audits every run; the audit keeps the
 * operation and the ids, not the text (an article is not an audit record).
 */
final class ContentCommand extends GlobalCommand implements RiskAwareCommand
{
    public const OPS = ['post.create', 'post.update', 'post.state', 'post.delete', 'post.upsert', 'reference.create', 'reference.update', 'reference.state', 'reference.delete', 'reference.order'];

    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): string
    {
        return 'content.manage';
    }

    public function name(): string
    {
        return 'content.'.$this->op();
    }

    public function riskLevel(): string
    {
        return PermissionCatalog::NORMAL;
    }

    public function requiresStepUp(): bool
    {
        return false;
    }

    public function requiresApproval(): bool
    {
        return false;
    }

    public function toAudit(): array
    {
        $input = (array) $this->get('input', []);

        return ['payload' => array_filter([
            'op' => $this->op(), 'id' => $this->get('id'), 'slug' => $input['slug'] ?? null, 'state' => $this->get('state') ?? ($input['state'] ?? null),
            'published' => $this->get('published'), 'ids' => $this->get('ids'), 'fields' => array_keys($input),
        ], fn ($v) => $v !== null && $v !== [])];
    }
}
