<?php

declare(strict_types=1);

namespace App\Chat\Domain\Repository\Interfaces;

use App\Chat\Domain\Entity\Chat;
use App\Platform\Domain\Entity\Application;
use App\User\Domain\Entity\User;

interface ChatRepositoryInterface
{
    public function findOneByApplication(Application $application): ?Chat;

    public function findOneByIdAndApplicationSlug(string $chatId, string $applicationSlug): ?Chat;

    public function findChatForDirectConversation(User $actor, User $targetUser): ?Chat;
}
