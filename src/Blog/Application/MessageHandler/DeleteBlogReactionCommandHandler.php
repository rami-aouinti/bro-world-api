<?php

declare(strict_types=1);

namespace App\Blog\Application\MessageHandler;

use App\Blog\Application\Message\DeleteBlogReactionCommand;
use App\Blog\Domain\Entity\BlogReaction;
use App\Blog\Infrastructure\Repository\BlogReactionRepository;
use App\General\Application\Service\CacheInvalidationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteBlogReactionCommandHandler
{
    public function __construct(private BlogReactionRepository $reactionRepository, private CacheInvalidationService $cacheInvalidationService) {}

    public function __invoke(DeleteBlogReactionCommand $command): void
    {
        $reaction = $this->reactionRepository->find($command->reactionId);

        if (!$reaction instanceof BlogReaction) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Reaction not found.');
        }

        if ($reaction->getAuthor()->getId() !== $command->actorUserId) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Only reaction owner can delete.');
        }

        $applicationSlug = $reaction->getComment()->getPost()->getBlog()->getApplication()?->getSlug();
        $this->reactionRepository->remove($reaction);
        $this->cacheInvalidationService->invalidateBlogCaches($applicationSlug, $command->actorUserId);
    }
}
