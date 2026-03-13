<?php

declare(strict_types=1);

namespace App\Chat\Domain\Repository\Interfaces;

use App\Chat\Domain\Entity\ChatMessage;
use App\Chat\Domain\Entity\ChatMessageReaction;
use App\Chat\Domain\Enum\ChatReactionType;
use App\General\Domain\Repository\Interfaces\BaseRepositoryInterface;
use App\User\Domain\Entity\User;

interface ChatMessageReactionRepositoryInterface extends BaseRepositoryInterface
{
    public function findOneByMessageUserReaction(ChatMessage $message, User $user, ChatReactionType $reaction): ?ChatMessageReaction;
}
