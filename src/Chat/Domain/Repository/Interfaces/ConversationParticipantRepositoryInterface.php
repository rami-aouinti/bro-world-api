<?php

declare(strict_types=1);

namespace App\Chat\Domain\Repository\Interfaces;

use App\Chat\Domain\Entity\Conversation;
use App\Chat\Domain\Entity\ConversationParticipant;
use App\User\Domain\Entity\User;

interface ConversationParticipantRepositoryInterface
{
    public function findOneByConversationAndUser(Conversation $conversation, User $user): ?ConversationParticipant;
}
