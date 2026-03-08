<?php

declare(strict_types=1);

namespace App\Platform\Infrastructure\Repository;

use App\General\Infrastructure\Repository\BaseRepository;
use App\Platform\Domain\Entity\Application as Entity;
use App\Platform\Domain\Repository\Interfaces\ApplicationRepositoryInterface;
use App\User\Domain\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method Entity[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 */
class ApplicationRepository extends BaseRepository implements ApplicationRepositoryInterface
{
    protected static string $entityName = Entity::class;

    protected static array $searchColumns = [
        'title',
        'description',
        'slug',
    ];

    public function __construct(protected ManagerRegistry $managerRegistry)
    {
    }

    public function createListQuery(array $filters, ?User $loggedInUser, ?array $esIds, int $page, int $limit): Query
    {
        $ids = $this->createListIdsQueryBuilder($filters, $loggedInUser, $esIds)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getSingleColumnResult();

        if ($ids === []) {
            return $this->createQueryBuilder('application')
                ->where('1 = 0')
                ->getQuery();
        }

        $query = $this->createQueryBuilder('application')
            ->leftJoin('application.platform', 'platform')
            ->leftJoin('application.user', 'user')
            ->leftJoin('application.applicationPlugins', 'applicationPlugin')
            ->leftJoin('applicationPlugin.plugin', 'plugin')
            ->addSelect('platform', 'user', 'applicationPlugin', 'plugin')
            ->andWhere('application.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('application.title', 'ASC')
            ->addOrderBy('application.id', 'ASC')
            ->getQuery();

        return $query;
    }

    public function countList(array $filters, ?User $loggedInUser, ?array $esIds): int
    {
        return (int) $this->createListIdsQueryBuilder($filters, $loggedInUser, $esIds)
            ->select('COUNT(application.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function createListIdsQueryBuilder(array $filters, ?User $loggedInUser, ?array $esIds): QueryBuilder
    {
        $qb = $this->createQueryBuilder('application')
            ->leftJoin('application.platform', 'platform')
            ->select('application.id')
            ->orderBy('application.title', 'ASC')
            ->addOrderBy('application.id', 'ASC');

        if ($loggedInUser === null) {
            $qb->where('application.private = :publicApplication')
                ->setParameter('publicApplication', false);
        } else {
            $qb->where('application.private = :publicApplication')
                ->orWhere('application.user = :loggedInUser')
                ->setParameter('publicApplication', false)
                ->setParameter('loggedInUser', $loggedInUser);
        }

        if ($filters['platformKey'] !== '') {
            $qb->andWhere('LOWER(platform.platformKey) = :platformKey')
                ->setParameter('platformKey', mb_strtolower($filters['platformKey']));
        }

        if ($esIds !== null) {
            $qb->andWhere('application.id IN (:esIds)')
                ->setParameter('esIds', $esIds);
        }

        if ($filters['title'] !== '') {
            $qb->andWhere('LOWER(application.title) LIKE :title')
                ->setParameter('title', '%' . mb_strtolower($filters['title']) . '%');
        }

        if ($filters['description'] !== '') {
            $qb->andWhere('LOWER(application.description) LIKE :description')
                ->setParameter('description', '%' . mb_strtolower($filters['description']) . '%');
        }

        if ($filters['platformName'] !== '') {
            $qb->andWhere('LOWER(platform.name) LIKE :platformName')
                ->setParameter('platformName', '%' . mb_strtolower($filters['platformName']) . '%');
        }

        return $qb;
    }
}
