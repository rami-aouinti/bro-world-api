<?php

declare(strict_types=1);

namespace App\Blog\Application\MessageHandler;

use App\Blog\Application\Message\CreateGeneralBlogCommand;
use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Enum\BlogType;
use App\Blog\Infrastructure\Repository\BlogRepository;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateGeneralBlogCommandHandler
{
    public function __construct(private BlogRepository $blogRepository, private UserRepository $userRepository) {}

    public function __invoke(CreateGeneralBlogCommand $command): void
    {
        $user = $this->userRepository->find($command->actorUserId);
        if (!$user instanceof User) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'User not found.');
        }

        if ($this->blogRepository->findGeneralBlog() instanceof Blog) {
            return;
        }

        $this->blogRepository->save((new Blog())->setTitle($command->title)->setOwner($user)->setType(BlogType::GENERAL));
    }
}
