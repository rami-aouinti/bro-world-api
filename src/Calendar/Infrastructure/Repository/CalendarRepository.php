<?php

declare(strict_types=1);

namespace App\Calendar\Infrastructure\Repository;

use App\Calendar\Domain\Entity\Calendar as Entity;
use App\Calendar\Domain\Repository\Interfaces\CalendarRepositoryInterface;
use App\General\Infrastructure\Repository\BaseRepository;
use App\Platform\Domain\Entity\Application;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method Entity[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 */
class CalendarRepository extends BaseRepository implements CalendarRepositoryInterface
{
    protected static string $entityName = Entity::class;

    protected static array $searchColumns = [
        'id',
        'title',
    ];

    public function __construct(
        protected ManagerRegistry $managerRegistry
    ) {
    }

    public function findOneByApplication(Application $application): ?Entity
    {
        $calendar = $this->findOneBy([
            'application' => $application,
        ]);

        return $calendar instanceof Entity ? $calendar : null;
    }
}
