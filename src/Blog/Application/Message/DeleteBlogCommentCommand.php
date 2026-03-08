<?php

declare(strict_types=1);

namespace App\Blog\Application\Message;

use App\General\Domain\Message\Interfaces\MessageHighInterface;

final readonly class DeleteBlogCommentCommand implements MessageHighInterface
{
    public function __construct(public string $operationId, public string $actorUserId, public string $commentId) {}
}
