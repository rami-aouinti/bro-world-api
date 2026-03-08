<?php

declare(strict_types=1);

namespace App\Chat\Application\Service;

use App\Chat\Domain\Entity\Conversation;
use App\Chat\Domain\Entity\ConversationParticipant;
use App\Chat\Domain\Repository\Interfaces\ConversationParticipantRepositoryInterface;
use App\User\Domain\Entity\User;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

class ConversationParticipantCreatorService
{
    public function __construct(
        private readonly ConversationParticipantRepositoryInterface $conversationParticipantRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getOrCreate(Conversation $conversation, User $user): ConversationParticipant
    {
        $conversationParticipant = $this->conversationParticipantRepository->findOneByConversationAndUser($conversation, $user);
        if ($conversationParticipant instanceof ConversationParticipant) {
            return $conversationParticipant;
        }

        $conversationParticipant = (new ConversationParticipant())
            ->setConversation($conversation)
            ->setUser($user);

        try {
            $this->entityManager->persist($conversationParticipant);
            $this->entityManager->flush();

            return $conversationParticipant;
        } catch (UniqueConstraintViolationException) {
            $conversationParticipant = $this->conversationParticipantRepository->findOneByConversationAndUser($conversation, $user);
            if ($conversationParticipant instanceof ConversationParticipant) {
                return $conversationParticipant;
            }

            throw new \RuntimeException('Unable to create an idempotent conversation participant.');
        }
    }
}
