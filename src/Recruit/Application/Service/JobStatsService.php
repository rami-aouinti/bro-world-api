<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\Recruit\Domain\Entity\Job;
use App\Recruit\Domain\Entity\Recruit;
use App\Recruit\Infrastructure\Repository\ApplicationRepository;
use BackedEnum;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;

readonly class JobStatsService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ApplicationSlaService $applicationSlaService,
        private ApplicationRepository $applicationRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getStats(Recruit $recruit): array
    {
        return [
            'total' => $this->getCount($recruit),
            'published' => $this->getCount($recruit, true),
            'draft' => $this->getCount($recruit, false),
            'byContractType' => $this->getGroupedCounts($recruit, 'contractType'),
            'byWorkMode' => $this->getGroupedCounts($recruit, 'workMode'),
            'byExperienceLevel' => $this->getGroupedCounts($recruit, 'experienceLevel'),
            'sla' => [
                'rulesHours' => $this->applicationSlaService->getRulesInHours(),
                'breachesByStatus' => $this->applicationRepository->countSlaBreachesByRecruit($recruit, $this->applicationSlaService->getRulesInHours()),
            ],
        ];
    }

    private function getCount(Recruit $recruit, ?bool $isPublished = null): int
    {
        $queryBuilder = $this->createBaseQueryBuilder($recruit)
            ->select('COUNT(job.id)');

        if ($isPublished !== null) {
            $queryBuilder
                ->andWhere('job.isPublished = :isPublished')
                ->setParameter('isPublished', $isPublished);
        }

        return (int)$queryBuilder->getQuery()->getSingleScalarResult();
    }

    /**
     * @return array<string, int>
     */
    private function getGroupedCounts(Recruit $recruit, string $field): array
    {
        $rows = $this->createBaseQueryBuilder($recruit)
            ->select(sprintf('job.%s AS statKey', $field), 'COUNT(job.id) AS statCount')
            ->groupBy(sprintf('job.%s', $field))
            ->getQuery()
            ->getArrayResult();

        $result = [];

        foreach ($rows as $row) {
            $rawKey = $row['statKey'] ?? '';
            $key = $rawKey instanceof BackedEnum ? $rawKey->value : (string)$rawKey;
            $result[$key] = (int)($row['statCount'] ?? 0);
        }

        return $result;
    }

    private function createBaseQueryBuilder(Recruit $recruit): QueryBuilder
    {
        return $this->entityManager->getRepository(Job::class)
            ->createQueryBuilder('job')
            ->andWhere('job.recruit = :recruit')
            ->setParameter('recruit', $recruit->getId(), UuidBinaryOrderedTimeType::NAME);
    }
}
