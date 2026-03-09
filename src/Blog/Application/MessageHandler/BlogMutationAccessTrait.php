<?php

declare(strict_types=1);

namespace App\Blog\Application\MessageHandler;

use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Enum\BlogStatus;
use App\User\Domain\Entity\User;

trait BlogMutationAccessTrait
{
    private function canWritePost(Blog $blog, User $user): bool
    {
        return $blog->getPostStatus() === BlogStatus::OPEN || $blog->getOwner()->getId() === $user->getId();
    }

    private function canWriteComment(Blog $blog, User $user): bool
    {
        return $blog->getCommentStatus() === BlogStatus::OPEN || $blog->getOwner()->getId() === $user->getId();
    }
}
