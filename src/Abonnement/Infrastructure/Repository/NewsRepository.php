<?php

declare(strict_types=1);

namespace App\Abonnement\Infrastructure\Repository;

use App\Abonnement\Domain\Entity\News as Entity;
use App\Abonnement\Domain\Repository\Interfaces\NewsRepositoryInterface;
use App\General\Infrastructure\Repository\BaseRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method Entity|null findOneBy(array $criteria, ?array $orderBy = null, ?string $entityManagerName = null)
 * @method Entity[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 */
class NewsRepository extends BaseRepository implements NewsRepositoryInterface
{
    protected static string $entityName = Entity::class;

    public function __construct(
        protected ManagerRegistry $managerRegistry,
    ) {
    }
}
