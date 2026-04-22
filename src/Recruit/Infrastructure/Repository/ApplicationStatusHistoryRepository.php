<?php

declare(strict_types=1);

namespace App\Recruit\Infrastructure\Repository;

use App\General\Infrastructure\Repository\BaseRepository;
use App\Recruit\Domain\Entity\ApplicationStatusHistory as Entity;
use App\Recruit\Domain\Repository\Interfaces\ApplicationStatusHistoryRepositoryInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method Entity[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 */
class ApplicationStatusHistoryRepository extends BaseRepository implements ApplicationStatusHistoryRepositoryInterface
{
    protected static string $entityName = Entity::class;

    protected static array $searchColumns = [
        'id',
    ];

    public function __construct(
        protected ManagerRegistry $managerRegistry,
    ) {
    }

    public function findAnalyticsHistoryRowsByApplicationId(array $applicationIds): array
    {
        if ($applicationIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('history')
            ->select('IDENTITY(history.application) AS applicationId', 'history.toStatus AS toStatus', 'history.createdAt AS createdAt', 'history.comment AS comment')
            ->andWhere('history.application IN (:applicationIds)')
            ->setParameter('applicationIds', $applicationIds)
            ->orderBy('history.createdAt', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $result = [];

        foreach ($rows as $row) {
            $applicationId = (string)($row['applicationId'] ?? '');
            if ($applicationId === '') {
                continue;
            }

            $toStatus = $row['toStatus'] ?? null;
            if ($toStatus instanceof \App\Recruit\Domain\Enum\ApplicationStatus) {
                $toStatus = $toStatus->value;
            }

            $createdAt = $row['createdAt'] ?? null;
            if (!$createdAt instanceof \DateTimeImmutable) {
                continue;
            }

            $result[$applicationId] ??= [];
            $result[$applicationId][] = [
                'toStatus' => (string)$toStatus,
                'createdAt' => $createdAt,
                'comment' => isset($row['comment']) ? (string)$row['comment'] : null,
            ];
        }

        return $result;
    }
}
