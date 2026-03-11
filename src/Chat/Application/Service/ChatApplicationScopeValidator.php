<?php

declare(strict_types=1);

namespace App\Chat\Application\Service;

use App\Chat\Domain\Entity\Chat;
use App\Chat\Infrastructure\Repository\ChatRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class ChatApplicationScopeValidator
{
    public function __construct(
        private ChatRepository $chatRepository,
    ) {
    }

    public function validate(string $chatId, string $applicationSlug): Chat
    {
        $chat = $this->chatRepository->findOneByIdAndApplicationSlug($chatId, $applicationSlug);
        if (!$chat instanceof Chat) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Chat not found for this application.');
        }

        return $chat;
    }
}
