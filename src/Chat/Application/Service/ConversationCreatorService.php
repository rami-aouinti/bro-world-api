<?php

declare(strict_types=1);

namespace App\Chat\Application\Service;

use App\Chat\Domain\Entity\Chat;
use App\Chat\Domain\Entity\Conversation;
use App\Chat\Domain\Repository\Interfaces\ConversationRepositoryInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

readonly class ConversationCreatorService
{
    public function __construct(
        private ConversationRepositoryInterface $conversationRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function getOrCreate(Chat $chat): Conversation
    {
        $conversation = $this->conversationRepository->findOneByChat($chat);
        if ($conversation instanceof Conversation) {
            return $conversation;
        }

        $conversation = new Conversation()
            ->setChat($chat);

        try {
            $this->entityManager->persist($conversation);
            $this->entityManager->flush();

            return $conversation;
        } catch (UniqueConstraintViolationException) {
            $conversation = $this->conversationRepository->findOneByChat($chat);
            if ($conversation instanceof Conversation) {
                return $conversation;
            }

            throw new RuntimeException('Unable to create an idempotent conversation.');
        }
    }
}
