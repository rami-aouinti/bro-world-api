<?php

declare(strict_types=1);

namespace App\Blog\Application\MessageHandler;

use App\Blog\Application\Message\CreateBlogPostCommand;
use App\Blog\Domain\Entity\Blog;
use App\Blog\Infrastructure\Repository\BlogRepository;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateBlogPostCommandHandler
{
    public function __construct(
        private BlogRepository $blogRepository,
        private UserRepository $userRepository,
    ) {}

    public function __invoke(CreateBlogPostCommand $command): void
    {
        $user = $this->userRepository->find($command->actorUserId);

        if (!$user instanceof User) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'User not found.');
        }

        $blog = $this->blogRepository->find($command->blogId);

        if (!$blog instanceof Blog) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Blog not found.');
        }

        // TODO: ici tu crées et sauvegardes ton post
    }
}
