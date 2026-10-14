<?php

declare(strict_types=1);

namespace Onhost\Domain\Content\Commands;

use Onhost\Domain\Content\ContentEditor;
use Onhost\Domain\Content\Models\ContentReference;
use Onhost\Domain\Content\Models\Post;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

final class ContentCommandHandler implements CommandHandler
{
    public function __construct(private readonly ContentEditor $editor) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof ContentCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        $input = (array) $command->get('input', []);

        return match ($command->op()) {
            'post.create' => $this->editor->createPost($input)->toAdmin(),
            'post.update' => $this->editor->updatePost($this->post($command), $input)->toAdmin(),
            'post.state' => $this->editor->setPostState($this->post($command), (string) $command->get('state'))->toAdmin(),
            'post.delete' => (function () use ($command): array {
                $post = $this->post($command);
                $this->editor->deletePost($post);

                return ['id' => $post->id, 'slug' => $post->slug, 'deleted' => true];
            })(),
            'post.upsert' => $this->editor->upsertPost($input)->toAdmin(),
            'reference.create' => $this->editor->createReference($input)->toAdmin(),
            'reference.update' => $this->editor->updateReference($this->reference($command), $input)->toAdmin(),
            'reference.state' => $this->editor->setReferencePublished($this->reference($command), (bool) $command->get('published'), $context)->toAdmin(),
            'reference.delete' => (function () use ($command): array {
                $reference = $this->reference($command);
                $this->editor->deleteReference($reference);

                return ['id' => $reference->id, 'deleted' => true];
            })(),
            'reference.order' => $this->editor->orderReferences(array_values((array) $command->get('ids', []))),
            default => throw new DomainError('content_op_unknown', "Unknown content operation {$command->op()}.", 422, ['field' => 'op']),
        };
    }

    private function post(ContentCommand $command): Post
    {
        return Post::query()->find((string) $command->get('id')) ?? throw DomainError::notFound('post');
    }

    private function reference(ContentCommand $command): ContentReference
    {
        return ContentReference::query()->find((string) $command->get('id')) ?? throw DomainError::notFound('reference');
    }
}
