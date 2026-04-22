<?php

declare(strict_types=1);

namespace App\Recruit\Infrastructure\Repository;

use App\General\Infrastructure\Repository\BaseRepository;
use App\Recruit\Domain\Entity\Application;
use App\Recruit\Domain\Entity\Interview as Entity;
use App\Recruit\Domain\Repository\Interfaces\InterviewRepositoryInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method Entity[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 */
class InterviewRepository extends BaseRepository implements InterviewRepositoryInterface
{
    protected static string $entityName = Entity::class;

    protected static array $searchColumns = [
        'id',
    ];

    public function __construct(
        protected ManagerRegistry $managerRegistry,
    ) {
    }

    public function findByApplicationOrdered(Application $application): array
    {
        return $this->createQueryBuilder('interview')
            ->where('interview.application = :application')
            ->setParameter('application', $application)
            ->orderBy('interview.scheduledAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findFirstInterviewAtByApplicationId(array $applicationIds): array
    {
        if ($applicationIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('interview')
            ->select('IDENTITY(interview.application) AS applicationId', 'MIN(interview.scheduledAt) AS firstInterviewAt')
            ->andWhere('interview.application IN (:applicationIds)')
            ->setParameter('applicationIds', $applicationIds)
            ->groupBy('interview.application')
            ->getQuery()
            ->getArrayResult();

        $result = [];

        foreach ($rows as $row) {
            $applicationId = (string)($row['applicationId'] ?? '');
            $firstInterviewAt = $row['firstInterviewAt'] ?? null;
            if ($applicationId === '' || !$firstInterviewAt instanceof \DateTimeImmutable) {
                continue;
            }

            $result[$applicationId] = $firstInterviewAt;
        }

        return $result;
    }
}
