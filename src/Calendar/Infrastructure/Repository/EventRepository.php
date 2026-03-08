<?php

declare(strict_types=1);

namespace App\Calendar\Infrastructure\Repository;

use App\Calendar\Domain\Entity\Event as Entity;
use App\Calendar\Domain\Repository\Interfaces\EventRepositoryInterface;
use App\General\Infrastructure\Repository\BaseRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method Entity[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 */
class EventRepository extends BaseRepository implements EventRepositoryInterface
{
    protected static string $entityName = Entity::class;

    protected static array $searchColumns = [
        'id',
        'title',
        'description',
        'status',
        'visibility',
    ];

    public function __construct(protected ManagerRegistry $managerRegistry)
    {
    }
}
