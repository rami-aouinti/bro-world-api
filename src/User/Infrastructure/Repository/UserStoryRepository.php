<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Repository;

use App\General\Infrastructure\Repository\BaseRepository;
use App\User\Domain\Entity\UserStory;
use Doctrine\Persistence\ManagerRegistry;

class UserStoryRepository extends BaseRepository
{
    protected static string $entityName = UserStory::class;
    protected static array $searchColumns = ['imageUrl'];

    public function __construct(
        protected ManagerRegistry $managerRegistry
    ) {
    }
}
