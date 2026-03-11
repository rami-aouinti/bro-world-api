<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\Recruit\Domain\Entity\Job;
use App\Recruit\Domain\Entity\Recruit;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;

class JobStatsService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** @return array<string, mixed> */
    public function getStats(Recruit $recruit): array
    {
        $jobs = $this->entityManager->getRepository(Job::class)
            ->createQueryBuilder('job')
            ->andWhere('job.recruit = :recruit')
            ->setParameter('recruit', $recruit->getId(), UuidBinaryOrderedTimeType::NAME)
            ->getQuery()
            ->getResult();

        $stats = [
            'total' => 0,
            'published' => 0,
            'draft' => 0,
            'byContractType' => [],
            'byWorkMode' => [],
            'byExperienceLevel' => [],
        ];

        foreach ($jobs as $job) {
            if (!$job instanceof Job) {
                continue;
            }

            $stats['total']++;
            $stats[$job->isPublished() ? 'published' : 'draft']++;
            $stats['byContractType'][$job->getContractTypeValue()] = ($stats['byContractType'][$job->getContractTypeValue()] ?? 0) + 1;
            $stats['byWorkMode'][$job->getWorkModeValue()] = ($stats['byWorkMode'][$job->getWorkModeValue()] ?? 0) + 1;
            $stats['byExperienceLevel'][$job->getExperienceLevelValue()] = ($stats['byExperienceLevel'][$job->getExperienceLevelValue()] ?? 0) + 1;
        }

        return $stats;
    }
}
