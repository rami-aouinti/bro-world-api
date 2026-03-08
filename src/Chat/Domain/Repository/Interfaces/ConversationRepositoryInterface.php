<?php

declare(strict_types=1);

namespace App\Chat\Domain\Repository\Interfaces;

use App\Chat\Domain\Entity\Chat;
use App\Chat\Domain\Entity\Conversation;

interface ConversationRepositoryInterface
{
    public function findOneByChatAndApplicationSlug(Chat $chat, string $applicationSlug): ?Conversation;
}
