<?php

declare(strict_types=1);

namespace App\Blog\Application\MessageHandler;

use App\Blog\Application\Message\CreateBlogPostReactionCommand;
use App\Blog\Application\Service\BlogNotificationService;
use App\Blog\Domain\Entity\BlogPost;
use App\Blog\Domain\Entity\BlogReaction;
use App\Blog\Infrastructure\Repository\BlogPostRepository;
use App\Blog\Infrastructure\Repository\BlogReactionRepository;
use App\General\Application\Service\CacheInvalidationService;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateBlogPostReactionCommandHandler
{
    public function __construct(
        private BlogReactionRepository $reactionRepository,
        private BlogPostRepository $postRepository,
        private UserRepository $userRepository,
        private BlogNotificationService $blogNotificationService,
        private CacheInvalidationService $cacheInvalidationService,
    ) {
    }

    public function __invoke(CreateBlogPostReactionCommand $command): string
    {
        $post = $this->postRepository->find($command->postId);
        $user = $this->userRepository->find($command->actorUserId);

        if (!$post instanceof BlogPost || !$user instanceof User) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Resource not found.');
        }

        $affectedUserIds = array_values(array_filter(array_unique([
            $command->actorUserId,
            $post->getAuthor()->getId(),
        ]), static fn (?string $userId): bool => $userId !== null && $userId !== ''));

        $existingReaction = $this->reactionRepository->findOneByPostAndAuthor($post, $user);

        if ($existingReaction instanceof BlogReaction) {
            $existingReaction->setType($command->type);
            $this->reactionRepository->save($existingReaction);

            $this->cacheInvalidationService->invalidateBlogCaches($post->getBlog()->getApplication()?->getSlug(), $affectedUserIds);

            return $existingReaction->getId();
        }

        $reaction = (new BlogReaction())
            ->setPost($post)
            ->setAuthor($user)
            ->setType($command->type);

        $this->reactionRepository->save($reaction);

        $this->blogNotificationService->notifyPostReactionCreated($post, $user, $command->type->value);
        $this->cacheInvalidationService->invalidateBlogCaches($post->getBlog()->getApplication()?->getSlug(), $affectedUserIds);

        return $reaction->getId();
    }
}
