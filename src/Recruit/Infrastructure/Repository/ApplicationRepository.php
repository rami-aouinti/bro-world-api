<?php

declare(strict_types=1);

namespace App\Recruit\Infrastructure\Repository;

use App\General\Infrastructure\Repository\BaseRepository;
use App\Recruit\Domain\Entity\Applicant;
use App\Recruit\Domain\Entity\Application as Entity;
use App\Recruit\Domain\Entity\ApplicationStatusHistory;
use App\Recruit\Domain\Entity\Job;
use App\Recruit\Domain\Entity\Recruit;
use App\Recruit\Domain\Enum\ApplicationStatus;
use App\Recruit\Domain\Repository\Interfaces\ApplicationRepositoryInterface;
use DateInterval;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
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
        'id',
    ];

    public function __construct(
        protected ManagerRegistry $managerRegistry
    ) {
    }

    public function findActiveByApplicantAndJob(Applicant $applicant, Job $job): ?Entity
    {
        return $this->createQueryBuilder('application')
            ->where('application.applicant = :applicant')
            ->andWhere('application.job = :job')
            ->andWhere('application.status IN (:statuses)')
            ->setParameter('applicant', $applicant)
            ->setParameter('job', $job)
            ->setParameter('statuses', [
                ApplicationStatus::WAITING,
                ApplicationStatus::SCREENING,
                ApplicationStatus::INTERVIEW_PLANNED,
                ApplicationStatus::INTERVIEW_DONE,
                ApplicationStatus::OFFER_SENT,
            ])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param array<string, int> $slaRulesInHours
     *
     * @return list<Entity>
     */
    public function findSlaBreaches(array $slaRulesInHours): array
    {
        $queryBuilder = $this->createQueryBuilder('application')
            ->innerJoin('application.job', 'job')
            ->addSelect('job', 'owner')
            ->leftJoin('job.owner', 'owner');

        $this->applySlaFilters($queryBuilder, $slaRulesInHours);

        /** @var list<Entity> $results */
        $results = $queryBuilder
            ->orderBy('application.updatedAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $results;
    }

    /**
     * @param array<string, int> $slaRulesInHours
     *
     * @return array<string, int>
     */
    public function countSlaBreachesByRecruit(Recruit $recruit, array $slaRulesInHours): array
    {
        $queryBuilder = $this->createQueryBuilder('application')
            ->select('application.status AS status', 'COUNT(application.id) AS breaches')
            ->innerJoin('application.job', 'job')
            ->andWhere('job.recruit = :recruit')
            ->setParameter('recruit', $recruit);

        $this->applySlaFilters($queryBuilder, $slaRulesInHours);

        $rows = $queryBuilder
            ->groupBy('application.status')
            ->getQuery()
            ->getArrayResult();

        $result = [];

        foreach ($rows as $row) {
            $status = (string)($row['status'] ?? '');
            if ($status === '') {
                continue;
            }

            $result[$status] = (int)($row['breaches'] ?? 0);
        }

        return $result;
    }

    public function findAnalyticsApplicationSnapshots(Recruit $recruit, ?DateTimeImmutable $from = null, ?DateTimeImmutable $to = null, ?Job $job = null): array
    {
        $rows = $this->createAnalyticsScopeQueryBuilder($recruit, $from, $to, $job)
            ->select('application.id AS id', 'application.status AS status', 'application.createdAt AS createdAt')
            ->getQuery()
            ->getArrayResult();

        $result = [];

        foreach ($rows as $row) {
            $id = (string)($row['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $status = $row['status'] ?? null;
            if ($status instanceof ApplicationStatus) {
                $status = $status->value;
            }

            $createdAt = $row['createdAt'] ?? null;
            $result[] = [
                'id' => $id,
                'status' => (string)$status,
                'createdAt' => $createdAt instanceof DateTimeImmutable ? $createdAt : null,
            ];
        }

        return $result;
    }

    public function countByCurrentStatusForAnalytics(Recruit $recruit, ?DateTimeImmutable $from = null, ?DateTimeImmutable $to = null, ?Job $job = null): array
    {
        $rows = $this->createAnalyticsScopeQueryBuilder($recruit, $from, $to, $job)
            ->select('application.status AS status', 'COUNT(application.id) AS total')
            ->groupBy('application.status')
            ->getQuery()
            ->getArrayResult();

        $counts = [];

        foreach ($rows as $row) {
            $status = $row['status'] ?? null;
            if ($status instanceof ApplicationStatus) {
                $status = $status->value;
            }

            if (!is_string($status) || $status === '') {
                continue;
            }

            $counts[$status] = (int)($row['total'] ?? 0);
        }

        return $counts;
    }

    public function countConversionsByStepForAnalytics(Recruit $recruit, ?DateTimeImmutable $from = null, ?DateTimeImmutable $to = null, ?Job $job = null): array
    {
        $appliedCount = (int)$this->createAnalyticsScopeQueryBuilder($recruit, $from, $to, $job)
            ->select('COUNT(application.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $screeningCount = $this->countReachedAnyStatusForAnalytics(
            $recruit,
            [ApplicationStatus::SCREENING],
            [ApplicationStatus::SCREENING],
            $from,
            $to,
            $job,
        );
        $interviewCount = $this->countReachedAnyStatusForAnalytics(
            $recruit,
            [ApplicationStatus::INTERVIEW_PLANNED, ApplicationStatus::INTERVIEW_DONE, ApplicationStatus::OFFER_SENT, ApplicationStatus::HIRED],
            [ApplicationStatus::INTERVIEW_PLANNED, ApplicationStatus::INTERVIEW_DONE],
            $from,
            $to,
            $job,
        );
        $offerCount = $this->countReachedAnyStatusForAnalytics(
            $recruit,
            [ApplicationStatus::OFFER_SENT, ApplicationStatus::HIRED],
            [ApplicationStatus::OFFER_SENT],
            $from,
            $to,
            $job,
        );
        $hiredCount = $this->countReachedAnyStatusForAnalytics(
            $recruit,
            [ApplicationStatus::HIRED],
            [ApplicationStatus::HIRED],
            $from,
            $to,
            $job,
        );

        return [
            'APPLIED' => $appliedCount,
            ApplicationStatus::SCREENING->value => $screeningCount,
            'INTERVIEW' => $interviewCount,
            ApplicationStatus::OFFER_SENT->value => $offerCount,
            ApplicationStatus::HIRED->value => $hiredCount,
        ];
    }

    /**
     * @return list<array{status: string, statusCount: int, avgAgingDays: float}>
     */
    public function findPipelineStatusMetrics(QueryBuilder $pipelineQueryBuilder): array
    {
        $aggregateQueryBuilder = clone $pipelineQueryBuilder;

        try {
            $rows = $aggregateQueryBuilder
                ->select(
                    'application.status AS status',
                    'COUNT(application.id) AS statusCount',
                    'AVG(TIMESTAMPDIFF(DAY, application.createdAt, CURRENT_TIMESTAMP())) AS avgAgingDays'
                )
                ->groupBy('application.status')
                ->getQuery()
                ->getArrayResult();

            return array_map(static fn (array $row): array => [
                'status' => (string)($row['status'] ?? ''),
                'statusCount' => (int)($row['statusCount'] ?? 0),
                'avgAgingDays' => round((float)($row['avgAgingDays'] ?? 0), 2),
            ], $rows);
        } catch (\Throwable) {
            $rows = (clone $pipelineQueryBuilder)
                ->select('application.status AS status', 'application.createdAt AS createdAt')
                ->getQuery()
                ->getArrayResult();

            $now = new DateTimeImmutable();
            $counts = [];
            $agingSums = [];

            foreach ($rows as $row) {
                $status = (string)($row['status'] ?? '');
                if ($status === '') {
                    continue;
                }

                $counts[$status] = ($counts[$status] ?? 0) + 1;

                $createdAt = $row['createdAt'] ?? null;
                if (!$createdAt instanceof DateTimeImmutable) {
                    continue;
                }

                $agingSums[$status] = ($agingSums[$status] ?? 0.0) + max(0, (float)$createdAt->diff($now)->days);
            }

            $result = [];
            foreach ($counts as $status => $count) {
                $result[] = [
                    'status' => $status,
                    'statusCount' => $count,
                    'avgAgingDays' => round(($agingSums[$status] ?? 0.0) / max(1, $count), 2),
                ];
            }

            return $result;
        }
    }

    /**
     * @param list<ApplicationStatus> $currentStatuses
     * @param list<ApplicationStatus> $historicalStatuses
     */
    private function countReachedAnyStatusForAnalytics(
        Recruit $recruit,
        array $currentStatuses,
        array $historicalStatuses,
        ?DateTimeImmutable $from = null,
        ?DateTimeImmutable $to = null,
        ?Job $job = null
    ): int
    {
        return (int)$this->createAnalyticsScopeQueryBuilder($recruit, $from, $to, $job)
            ->select('COUNT(DISTINCT application.id)')
            ->leftJoin(ApplicationStatusHistory::class, 'history', 'WITH', 'history.application = application')
            ->andWhere('application.status IN (:currentStatuses) OR history.toStatus IN (:historicalStatuses)')
            ->setParameter('currentStatuses', $currentStatuses)
            ->setParameter('historicalStatuses', $historicalStatuses)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function createAnalyticsScopeQueryBuilder(Recruit $recruit, ?DateTimeImmutable $from = null, ?DateTimeImmutable $to = null, ?Job $job = null): \Doctrine\ORM\QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('application')
            ->innerJoin('application.job', 'job')
            ->andWhere('job.recruit = :recruit')
            ->setParameter('recruit', $recruit);

        if ($from !== null) {
            $queryBuilder
                ->andWhere('application.createdAt >= :from')
                ->setParameter('from', $from);
        }

        if ($to !== null) {
            $queryBuilder
                ->andWhere('application.createdAt <= :to')
                ->setParameter('to', $to);
        }

        if ($job !== null) {
            $queryBuilder
                ->andWhere('application.job = :job')
                ->setParameter('job', $job);
        }

        return $queryBuilder;
    }

    /**
     * @param array<string, int> $slaRulesInHours
     */
    private function applySlaFilters(\Doctrine\ORM\QueryBuilder $queryBuilder, array $slaRulesInHours): void
    {
        $orX = $queryBuilder->expr()->orX();
        $now = new DateTimeImmutable();
        $index = 0;

        foreach ($slaRulesInHours as $status => $hours) {
            $statusParam = 'slaStatus' . $index;
            $dateParam = 'slaDate' . $index;
            $index++;

            $cutoff = $now->sub(new DateInterval('PT' . $hours . 'H'));
            $orX->add(sprintf('(application.status = :%s AND application.updatedAt <= :%s)', $statusParam, $dateParam));
            $queryBuilder
                ->setParameter($statusParam, ApplicationStatus::from($status))
                ->setParameter($dateParam, $cutoff);
        }

        if ($orX->count() > 0) {
            $queryBuilder->andWhere($orX);
        } else {
            $queryBuilder->andWhere('1 = 0');
        }
    }
}
