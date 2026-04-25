<?php

declare(strict_types=1);

namespace App\Crm\Infrastructure\Repository;

use App\Crm\Domain\Entity\TaskRequestWorklog as Entity;
use App\General\Infrastructure\Repository\BaseRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;

use function array_values;

/**
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method Entity[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 */
class TaskRequestWorklogRepository extends BaseRepository
{
    protected static string $entityName = Entity::class;

    protected static array $searchColumns = [
        'id',
    ];

    public function __construct(
        protected ManagerRegistry $managerRegistry,
    ) {
    }

    public function sumConsumedHoursByTaskRequestId(string $taskRequestId): float
    {
        return $this->sumConsumedHoursByTaskRequestIds([$taskRequestId])[$taskRequestId] ?? 0.0;
    }

    /**
     * @param list<string> $taskRequestIds
     * @return array<string,float>
     */
    public function sumConsumedHoursByTaskRequestIds(array $taskRequestIds): array
    {
        if ($taskRequestIds === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('worklog')
            ->select('IDENTITY(worklog.taskRequest) AS taskRequestId, COALESCE(SUM(worklog.hours), 0) AS consumedHours')
            ->groupBy('worklog.taskRequest');

        $orConditions = [];
        foreach (array_values($taskRequestIds) as $index => $taskRequestId) {
            $paramName = 'taskRequestId_' . $index;
            $orConditions[] = 'IDENTITY(worklog.taskRequest) = :' . $paramName;
            $qb->setParameter($paramName, $taskRequestId, UuidBinaryOrderedTimeType::NAME);
        }

        $qb->andWhere('(' . implode(' OR ', $orConditions) . ')');

        /** @var list<array{taskRequestId:string,consumedHours:numeric-string|float|int}> $rows */
        $rows = $qb->getQuery()->getArrayResult();

        $consumedHoursByTaskRequest = [];
        foreach ($rows as $row) {
            $consumedHoursByTaskRequest[(string)$row['taskRequestId']] = (float)$row['consumedHours'];
        }

        return $consumedHoursByTaskRequest;
    }
}
