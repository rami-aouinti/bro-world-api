<?php

declare(strict_types=1);

namespace App\Blog\Application\MessageHandler;

use App\Blog\Application\Message\PatchBlogReactionCommand;
use App\Blog\Domain\Entity\BlogReaction;
use App\Blog\Infrastructure\Repository\BlogReactionRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PatchBlogReactionCommandHandler
{
    public function __construct(private BlogReactionRepository $reactionRepository) {}

    public function __invoke(PatchBlogReactionCommand $command): void
    {
        $reaction = $this->reactionRepository->find($command->reactionId);

        if (!$reaction instanceof BlogReaction) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Reaction not found.');
        }

        if ($reaction->getAuthor()->getId() !== $command->actorUserId) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Only reaction owner can patch.');
        }

        $reaction->setType($command->type);
        $this->reactionRepository->save($reaction);
    }
}
