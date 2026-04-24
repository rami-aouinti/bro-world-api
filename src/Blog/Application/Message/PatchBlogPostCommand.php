<?php

declare(strict_types=1);

namespace App\Blog\Application\Message;

use App\General\Domain\Message\Interfaces\MessageHighInterface;

final readonly class PatchBlogPostCommand implements MessageHighInterface
{
    /**
     * @param list<string>|null $mediaUrls
     * @param list<string>|null $tagIds
     */
    public function __construct(
        public string $operationId,
        public string $actorUserId,
        public string $postId,
        public ?string $title,
        public ?string $content,
        public ?string $filePath,
        public ?array $mediaUrls,
        public ?array $tagIds,
        public ?string $sharedUrl,
        public ?bool $isPinned
    ) {
    }
}
