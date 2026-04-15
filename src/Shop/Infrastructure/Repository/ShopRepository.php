<?php

declare(strict_types=1);

namespace App\Shop\Infrastructure\Repository;

use App\General\Infrastructure\Repository\BaseRepository;
use App\Platform\Domain\Entity\Application;
use App\Shop\Domain\Entity\Shop as Entity;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;
use function trim;

/**
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method Entity[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 */
class ShopRepository extends BaseRepository
{
    protected static string $entityName = Entity::class;

    protected static array $searchColumns = [
        'id',
    ];

    public function __construct(
        protected ManagerRegistry $managerRegistry
    ) {
    }

    public function findOneByApplicationSlug(string $applicationSlug): ?Entity
    {
        $scope = trim($applicationSlug);
        if ($scope === '') {
            return null;
        }

        $entity = $this->createQueryBuilder('module')
            ->innerJoin('module.application', 'application')
            ->addSelect('application')
            ->where('application.slug = :applicationSlug')
            ->andWhere('module.isGlobal = false')
            ->setParameter('applicationSlug', $scope)
            ->getQuery()
            ->getOneOrNullResult();

        return $entity instanceof Entity ? $entity : null;
    }

    public function findGlobalShop(): ?Entity
    {
        $entity = $this->createQueryBuilder('shop')
            ->where('shop.isGlobal = true')
            ->getQuery()
            ->getOneOrNullResult();

        return $entity instanceof Entity ? $entity : null;
    }

    public function countGlobalShops(): int
    {
        return (int) $this->createQueryBuilder('shop')
            ->select('COUNT(shop.id)')
            ->where('shop.isGlobal = true')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findApplicationBySlug(string $applicationSlug): ?Application
    {
        $application = $this->getEntityManager()->getRepository(Application::class)->findOneBy([
            'slug' => $applicationSlug,
        ]);

        return $application instanceof Application ? $application : null;
    }
}
