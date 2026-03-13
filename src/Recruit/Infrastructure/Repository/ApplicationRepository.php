<?php

declare(strict_types=1);

namespace App\Recruit\Infrastructure\Repository;

use App\General\Infrastructure\Repository\BaseRepository;
use App\Recruit\Domain\Entity\Applicant;
use App\Recruit\Domain\Entity\Application as Entity;
use App\Recruit\Domain\Entity\Job;
use App\Recruit\Domain\Enum\ApplicationStatus;
use App\Recruit\Domain\Repository\Interfaces\ApplicationRepositoryInterface;
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
                ApplicationStatus::IN_PROGRESS,
                ApplicationStatus::DISCUSSION,
                ApplicationStatus::INVITE_TO_INTERVIEW,
                ApplicationStatus::INTERVIEW,
            ])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
