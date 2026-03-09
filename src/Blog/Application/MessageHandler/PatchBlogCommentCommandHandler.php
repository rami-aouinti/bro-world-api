<?php

declare(strict_types=1);

namespace App\Blog\Application\MessageHandler;

use App\Blog\Application\Message\PatchBlogCommentCommand;
use App\Blog\Domain\Entity\BlogComment;
use App\Blog\Infrastructure\Repository\BlogCommentRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PatchBlogCommentCommandHandler
{
    public function __construct(private BlogCommentRepository $commentRepository) {}

    public function __invoke(PatchBlogCommentCommand $command): void
    {
        $comment = $this->commentRepository->find($command->commentId);

        if (!$comment instanceof BlogComment) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Comment not found.');
        }

        if ($comment->getAuthor()->getId() !== $command->actorUserId) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Only comment owner can patch.');
        }

        $comment
            ->setContent($command->content)
            ->setFilePath($command->filePath);

        $this->commentRepository->save($comment);
    }
}
