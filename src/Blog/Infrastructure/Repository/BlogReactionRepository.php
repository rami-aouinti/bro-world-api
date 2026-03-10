<?php

declare(strict_types=1);

namespace App\Blog\Infrastructure\Repository;

use App\Blog\Domain\Entity\BlogReaction;
use App\General\Infrastructure\Repository\BaseRepository;
use Doctrine\Persistence\ManagerRegistry;

class BlogReactionRepository extends BaseRepository
{
    protected static string $entityName = BlogReaction::class;
    protected static array $searchColumns = ['id', 'type'];

    public function __construct(
        protected ManagerRegistry $managerRegistry
    ) {
    }
}
