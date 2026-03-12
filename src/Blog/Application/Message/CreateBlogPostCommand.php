<?php

declare(strict_types=1);

namespace App\Blog\Application\Message;

use App\General\Domain\Message\Interfaces\MessageHighInterface;

final readonly class CreateBlogPostCommand implements MessageHighInterface
{
    /**
     * @param list<string> $mediaUrls
     */
    public function __construct(
        public string $operationId,
        public string $actorUserId,
        public string $blogId,
        public string $title,
        public string $slug,
        public ?string $content,
        public ?string $filePath,
        public array $mediaUrls,
        public ?string $sharedUrl,
        public ?string $parentPostId,
        public bool $isPinned
    ) {
    }
}
