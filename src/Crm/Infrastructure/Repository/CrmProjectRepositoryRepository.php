<?php

declare(strict_types=1);

namespace App\Crm\Infrastructure\Repository;

use App\Crm\Domain\Entity\CrmRepository as Entity;
use App\General\Infrastructure\Repository\BaseRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;

/**
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method Entity[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 */
class CrmProjectRepositoryRepository extends BaseRepository
{
    protected static string $entityName = Entity::class;

    protected static array $searchColumns = [
        'id',
        'fullName',
    ];

    public function __construct(
        protected ManagerRegistry $managerRegistry,
    ) {
    }

    public function findOneScopedById(string $id, string $crmId): ?Entity
    {
        $entity = $this->createQueryBuilder('repository')
            ->leftJoin('repository.project', 'project')
            ->leftJoin('project.company', 'company')
            ->andWhere('repository.id = :id')
            ->andWhere('IDENTITY(company.crm) = :crmId')
            ->setParameter('id', $id, UuidBinaryOrderedTimeType::NAME)
            ->setParameter('crmId', $crmId, UuidBinaryOrderedTimeType::NAME)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $entity instanceof Entity ? $entity : null;
    }

    public function findOneByProviderAndFullName(string $provider, string $fullName): ?Entity
    {
        $entity = $this->findOneBy([
            'provider' => trim($provider),
            'fullName' => trim($fullName),
        ]);

        return $entity instanceof Entity ? $entity : null;
    }
}
