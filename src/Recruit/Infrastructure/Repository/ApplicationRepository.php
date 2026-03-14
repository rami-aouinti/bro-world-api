<?php

declare(strict_types=1);

namespace App\Recruit\Infrastructure\Repository;

use App\General\Infrastructure\Repository\BaseRepository;
use App\Recruit\Domain\Entity\Applicant;
use App\Recruit\Domain\Entity\Application as Entity;
use App\Recruit\Domain\Entity\Job;
use App\Recruit\Domain\Entity\Recruit;
use App\Recruit\Domain\Enum\ApplicationStatus;
use App\Recruit\Domain\Repository\Interfaces\ApplicationRepositoryInterface;
use DateInterval;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
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
