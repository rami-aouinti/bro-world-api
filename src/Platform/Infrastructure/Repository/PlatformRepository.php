<?php

declare(strict_types=1);

namespace App\Platform\Infrastructure\Repository;

use App\General\Infrastructure\Repository\BaseRepository;
use App\Platform\Domain\Entity\Platform as Entity;
use App\Platform\Domain\Repository\Interfaces\PlatformRepositoryInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @package App\Platform
 *
 * @psalm-suppress LessSpecificImplementedReturnType
 * @codingStandardsIgnoreStart
 *
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method Entity|null findAdvanced(string $id, string|int|null $hydrationMode = null, string|null $entityManagerName = null)
 * @method Entity|null findOneBy(array $criteria, ?array $orderBy = null, ?string $entityManagerName = null)
 * @method Entity[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 * @method Entity[] findByAdvanced(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?array $search = null, ?string $entityManagerName = null)
 * @method Entity[] findAll(?string $entityManagerName = null)
 *
 * @codingStandardsIgnoreEnd
 */
class PlatformRepository extends BaseRepository implements PlatformRepositoryInterface
{
    /**
     * @psalm-var class-string
     */
    protected static string $entityName = Entity::class;

    /**
     * @var array<int, string>
     */
    protected static array $searchColumns = [
        'name',
        'description',
        'enabled',
        'private',
    ];

    public function __construct(
        protected ManagerRegistry $managerRegistry,
    ) {
    }

    /**
     * @return array<int, Entity>
     */
    public function findPublicEnabled(): array
    {
        return $this->findBy(
            criteria: [
                'enabled' => true,
                'private' => false,
            ],
            orderBy: [
                'name' => 'ASC',
                'id' => 'ASC',
            ],
        );
    }
}
