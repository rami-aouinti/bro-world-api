<?php

declare(strict_types=1);

namespace App\Blog\Application\Message;

use App\General\Domain\Message\Interfaces\MessageHighInterface;

final readonly class CreateBlogCommentCommand implements MessageHighInterface
{
    public function __construct(public string $operationId, public string $actorUserId, public string $postId, public ?string $content, public ?string $filePath, public ?string $parentCommentId = null) {}
}
