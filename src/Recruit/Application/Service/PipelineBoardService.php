<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\Recruit\Domain\Entity\Application;
use App\Recruit\Domain\Entity\Recruit;
use App\Recruit\Domain\Enum\ApplicationStatus;
use App\Recruit\Infrastructure\Repository\ApplicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;

use function array_fill_keys;
use function array_map;
use function array_values;
use function ceil;
use function max;
use function min;
use function strtolower;
use function trim;

readonly class PipelineBoardService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getPipeline(Recruit $recruit, Request $request): array
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 20)));
        $offset = ($page - 1) * $limit;

        $jobId = trim($request->query->getString('jobId', ''));
        $owner = trim($request->query->getString('owner', ''));
        $date = trim($request->query->getString('date', ''));
        $source = strtolower(trim($request->query->getString('source', '')));
        $tag = trim($request->query->getString('tags', ''));

        $queryBuilder = $this->entityManager->getRepository(Application::class)
            ->createQueryBuilder('application')
            ->innerJoin('application.job', 'job')->addSelect('job')
            ->innerJoin('application.applicant', 'applicant')->addSelect('applicant')
            ->innerJoin('applicant.user', 'candidate')->addSelect('candidate')
            ->leftJoin('applicant.resume', 'resume')->addSelect('resume')
            ->leftJoin('job.owner', 'ownerUser')->addSelect('ownerUser')
            ->andWhere('job.recruit = :recruit')
            ->setParameter('recruit', $recruit->getId(), UuidBinaryOrderedTimeType::NAME)
            ->orderBy('application.createdAt', 'DESC')
            ->addOrderBy('application.id', 'DESC');

        if ($jobId !== '' && Uuid::isValid($jobId)) {
            $queryBuilder
                ->andWhere('job.id = :jobId')
                ->setParameter('jobId', $jobId, UuidBinaryOrderedTimeType::NAME);
        }

        if ($owner !== '' && Uuid::isValid($owner)) {
            $queryBuilder
                ->andWhere('ownerUser.id = :owner')
                ->setParameter('owner', $owner, UuidBinaryOrderedTimeType::NAME);
        }

        if ($date !== '') {
            $startOfDay = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if ($startOfDay !== false) {
                $startOfNextDay = $startOfDay->modify('+1 day');

                $queryBuilder
                    ->andWhere('application.createdAt >= :startOfDay')
                    ->andWhere('application.createdAt < :startOfNextDay')
                    ->setParameter('startOfDay', $startOfDay)
                    ->setParameter('startOfNextDay', $startOfNextDay);
            }
        }

        if ($source === 'resume') {
            $queryBuilder->andWhere('resume.id IS NOT NULL');
        } elseif ($source === 'manual') {
            $queryBuilder->andWhere('resume.id IS NULL');
        }

        if ($tag !== '') {
            $queryBuilder
                ->innerJoin('job.tags', 'jobTagFilter')
                ->andWhere('jobTagFilter.label = :tagLabel')
                ->setParameter('tagLabel', $tag);
        }

        /** @var ApplicationRepository $applicationRepository */
        $applicationRepository = $this->entityManager->getRepository(Application::class);
        $aggregateRows = $applicationRepository->findPipelineStatusMetrics($queryBuilder);

        $queryBuilder->setFirstResult($offset)->setMaxResults($limit);

        $paginator = new Paginator($queryBuilder->getQuery(), true);

        $statusOrder = array_map(static fn (ApplicationStatus $status): string => $status->value, ApplicationStatus::cases());

        $metricsByStatus = array_fill_keys($statusOrder, [
            'count' => 0,
            'avgAgingDays' => 0.0,
        ]);
        foreach ($aggregateRows as $row) {
            $status = (string)($row['status'] ?? '');
            if (!isset($metricsByStatus[$status])) {
                continue;
            }

            $metricsByStatus[$status] = [
                'count' => (int)($row['statusCount'] ?? 0),
                'avgAgingDays' => round((float)($row['avgAgingDays'] ?? 0), 2),
            ];
        }

        $columns = array_fill_keys($statusOrder, []);

        $now = new \DateTimeImmutable();

        foreach ($paginator as $application) {
            if (!$application instanceof Application) {
                continue;
            }

            $status = $application->getStatusValue();
            $applicant = $application->getApplicant();
            $candidate = $applicant->getUser();
            $job = $application->getJob();

            $columns[$status][] = [
                'id' => $application->getId(),
                'status' => $status,
                'createdAt' => $application->getCreatedAt()?->format(DATE_ATOM),
                'agingDays' => max(0, (int)$application->getCreatedAt()?->diff($now)->days),
                'source' => $applicant->getResume() !== null ? 'resume' : 'manual',
                'job' => [
                    'id' => $job->getId(),
                    'title' => $job->getTitle(),
                    'ownerId' => $job->getOwner()?->getId(),
                ],
                'candidate' => [
                    'id' => $candidate->getId(),
                    'email' => $candidate->getEmail(),
                    'firstName' => $candidate->getFirstName(),
                    'lastName' => $candidate->getLastName(),
                ],
            ];
        }

        $total = count($paginator);

        return [
            'columns' => array_values(array_map(static fn (string $status) => [
                'status' => $status,
                'metrics' => $metricsByStatus[$status],
                'candidates' => $columns[$status],
            ], $statusOrder)),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int)ceil(max(1, $total) / $limit),
            ],
            'filters' => [
                'jobId' => $jobId,
                'owner' => $owner,
                'date' => $date,
                'source' => $source,
                'tags' => $tag,
            ],
        ];
    }
}
