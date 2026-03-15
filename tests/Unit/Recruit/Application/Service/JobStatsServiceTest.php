<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruit\Application\Service;

use App\Recruit\Application\Service\ApplicationSlaService;
use App\Recruit\Application\Service\JobStatsService;
use App\Recruit\Domain\Entity\Job;
use App\Recruit\Domain\Entity\Recruit;
use App\Recruit\Domain\Enum\ContractType;
use App\Recruit\Domain\Enum\ExperienceLevel;
use App\Recruit\Domain\Enum\WorkMode;
use App\Recruit\Infrastructure\Repository\ApplicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;

class JobStatsServiceTest extends TestCase
{
    public function testGetStatsReturnsSameResultAsLegacyComputation(): void
    {
        $recruit = new Recruit();
        $jobs = $this->createRepresentativeJobs();

        $expectedStats = $this->computeLegacyStats($jobs);
        $expectedSlaRules = [
            'WAITING' => 72,
        ];
        $expectedBreaches = [
            'WAITING' => 3,
        ];
        $expectedStats['sla'] = [
            'rulesHours' => $expectedSlaRules,
            'breachesByStatus' => $expectedBreaches,
        ];

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->getMockBuilder(stdClass::class)
            ->addMethods(['createQueryBuilder'])
            ->getMock();

        $entityManager
            ->expects(self::exactly(6))
            ->method('getRepository')
            ->willReturn($repository);

        $applicationSlaService = $this->createMock(ApplicationSlaService::class);
        $applicationSlaService
            ->expects(self::exactly(2))
            ->method('getRulesInHours')
            ->willReturn($expectedSlaRules);

        $applicationRepository = $this->createMock(ApplicationRepository::class);
        $applicationRepository
            ->expects(self::once())
            ->method('countSlaBreachesByRecruit')
            ->with($recruit, $expectedSlaRules)
            ->willReturn($expectedBreaches);

        $repository
            ->expects(self::exactly(6))
            ->method('createQueryBuilder')
            ->with('job')
            ->willReturnOnConsecutiveCalls(
                $this->createCountQueryBuilderMock($expectedStats['total']),
                $this->createCountQueryBuilderMock($expectedStats['published']),
                $this->createCountQueryBuilderMock($expectedStats['draft']),
                $this->createGroupedQueryBuilderMock($expectedStats['byContractType']),
                $this->createGroupedQueryBuilderMock($expectedStats['byWorkMode']),
                $this->createGroupedQueryBuilderMock($expectedStats['byExperienceLevel']),
            );

        $service = new JobStatsService($entityManager, $applicationSlaService, $applicationRepository);

        self::assertSame($expectedStats, $service->getStats($recruit));
    }

    /**
     * @param array<string, int> $counts
     */
    private function createGroupedQueryBuilderMock(array $counts): QueryBuilder&MockObject
    {
        $query = $this->createMock(Query::class);
        $query
            ->expects(self::once())
            ->method('getArrayResult')
            ->willReturn($this->toGroupedRows($counts));

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('groupBy')->willReturnSelf();
        $queryBuilder
            ->expects(self::once())
            ->method('getQuery')
            ->willReturn($query);

        return $queryBuilder;
    }

    private function createCountQueryBuilderMock(int $count): QueryBuilder&MockObject
    {
        $query = $this->createMock(Query::class);
        $query
            ->expects(self::once())
            ->method('getSingleScalarResult')
            ->willReturn((string)$count);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder
            ->expects(self::once())
            ->method('getQuery')
            ->willReturn($query);

        return $queryBuilder;
    }

    /**
     * @return array<int, array{statKey: string, statCount: int}>
     */
    private function toGroupedRows(array $counts): array
    {
        $rows = [];

        foreach ($counts as $key => $count) {
            $rows[] = [
                'statKey' => $key,
                'statCount' => $count,
            ];
        }

        return $rows;
    }

    /**
     * @return list<Job>
     */
    private function createRepresentativeJobs(): array
    {
        return [
            $this->createJob(true, ContractType::CDI, WorkMode::REMOTE, ExperienceLevel::SENIOR),
            $this->createJob(true, ContractType::CDD, WorkMode::HYBRID, ExperienceLevel::MID),
            $this->createJob(false, ContractType::FREELANCE, WorkMode::ONSITE, ExperienceLevel::LEAD),
            $this->createJob(false, ContractType::CDI, WorkMode::REMOTE, ExperienceLevel::JUNIOR),
            $this->createJob(true, ContractType::INTERNSHIP, WorkMode::HYBRID, ExperienceLevel::JUNIOR),
        ];
    }

    private function createJob(
        bool $isPublished,
        ContractType $contractType,
        WorkMode $workMode,
        ExperienceLevel $experienceLevel,
    ): Job {
        return (new Job())
            ->setIsPublished($isPublished)
            ->setContractType($contractType)
            ->setWorkMode($workMode)
            ->setExperienceLevel($experienceLevel);
    }

    /**
     * @param list<Job> $jobs
     *
     * @return array<string, mixed>
     */
    private function computeLegacyStats(array $jobs): array
    {
        $stats = [
            'total' => 0,
            'published' => 0,
            'draft' => 0,
            'byContractType' => [],
            'byWorkMode' => [],
            'byExperienceLevel' => [],
        ];

        foreach ($jobs as $job) {
            $stats['total']++;
            $stats[$job->isPublished() ? 'published' : 'draft']++;
            $stats['byContractType'][$job->getContractTypeValue()] = ($stats['byContractType'][$job->getContractTypeValue()] ?? 0) + 1;
            $stats['byWorkMode'][$job->getWorkModeValue()] = ($stats['byWorkMode'][$job->getWorkModeValue()] ?? 0) + 1;
            $stats['byExperienceLevel'][$job->getExperienceLevelValue()] = ($stats['byExperienceLevel'][$job->getExperienceLevelValue()] ?? 0) + 1;
        }

        return $stats;
    }
}
