<?php

declare(strict_types=1);

namespace App\Blog\Application\MessageHandler;

use App\Blog\Application\Message\CreateBlogReactionCommand;
use App\Blog\Application\Service\BlogNotificationService;
use App\Blog\Domain\Entity\BlogComment;
use App\Blog\Domain\Entity\BlogReaction;
use App\Blog\Infrastructure\Repository\BlogCommentRepository;
use App\Blog\Infrastructure\Repository\BlogReactionRepository;
use App\General\Application\Service\CacheInvalidationService;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateBlogReactionCommandHandler
{
    public function __construct(
        private BlogReactionRepository $reactionRepository,
        private BlogCommentRepository $commentRepository,
        private UserRepository $userRepository,
        private BlogNotificationService $blogNotificationService,
        private CacheInvalidationService $cacheInvalidationService,
    ) {
    }

    public function __invoke(CreateBlogReactionCommand $command): void
    {
        $comment = $this->commentRepository->find($command->commentId);
        $user = $this->userRepository->find($command->actorUserId);

        if (!$comment instanceof BlogComment || !$user instanceof User) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Resource not found.');
        }

        $this->reactionRepository->save((new BlogReaction())
            ->setComment($comment)
            ->setAuthor($user)
            ->setType($command->type));

        $this->blogNotificationService->notifyReactionCreated($comment, $user, $command->type);
        $this->cacheInvalidationService->invalidateBlogCaches($comment->getPost()->getBlog()->getApplication()?->getSlug(), $command->actorUserId);
    }
}
