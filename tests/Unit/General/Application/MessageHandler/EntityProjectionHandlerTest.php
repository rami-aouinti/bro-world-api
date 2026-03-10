<?php

declare(strict_types=1);

namespace App\Tests\Unit\General\Application\MessageHandler;

use App\General\Application\Message\EntityCreated;
use App\General\Application\Message\EntityDeleted;
use App\General\Application\MessageHandler\EntityProjectionHandler;
use App\General\Application\Service\CacheInvalidationService;
use App\General\Application\Service\CriticalViewWarmer;
use App\General\Application\Service\MessageIdempotenceGuard;
use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use App\Platform\Application\Projection\ApplicationProjection;
use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Entity\Platform;
use App\Platform\Infrastructure\Repository\ApplicationRepository;
use App\Recruit\Application\Projection\RecruitJobProjection;
use App\Recruit\Domain\Entity\Job;
use App\Recruit\Domain\Entity\Recruit;
use App\Recruit\Infrastructure\Repository\JobRepository;
use PHPUnit\Framework\TestCase;

final class EntityProjectionHandlerTest extends TestCase
{
    public function testThatItIndexesApplicationAndWarmsCachesOnCreate(): void
    {
        $application = (new Application())
            ->setTitle('App')
            ->setDescription('Desc')
            ->setPlatform((new Platform())->setName('Platform')->setPlatformKey('CRM'));

        $applicationRepository = $this->createMock(ApplicationRepository::class);
        $applicationRepository->method('find')->willReturn($application);

        $jobRepository = $this->createMock(JobRepository::class);

        $cacheInvalidationService = $this->createMock(CacheInvalidationService::class);
        $cacheInvalidationService->expects(self::once())->method('invalidateApplicationListCaches');

        $criticalViewWarmer = $this->createMock(CriticalViewWarmer::class);
        $criticalViewWarmer->expects(self::once())->method('warmApplicationList');

        $guard = $this->createMock(MessageIdempotenceGuard::class);
        $guard->method('shouldProcess')->willReturn(true);

        $elastic = $this->createMock(ElasticsearchServiceInterface::class);
        $elastic->expects(self::once())->method('index')->with(
            ApplicationProjection::INDEX_NAME,
            $application->getId(),
            self::arrayHasKey('title'),
        );

        $handler = new EntityProjectionHandler(
            $applicationRepository,
            $jobRepository,
            $cacheInvalidationService,
            $criticalViewWarmer,
            $elastic,
            $guard,
        );

        $handler(new EntityCreated('platform_application', $application->getId(), 'evt_1'));
    }

    public function testThatItDeletesRecruitJobDocumentAndInvalidatesJobCacheOnDelete(): void
    {
        $applicationRepository = $this->createMock(ApplicationRepository::class);
        $jobRepository = $this->createMock(JobRepository::class);

        $cacheInvalidationService = $this->createMock(CacheInvalidationService::class);
        $cacheInvalidationService->expects(self::once())->method('invalidateRecruitJobListCaches')->with('slug-1');

        $criticalViewWarmer = $this->createMock(CriticalViewWarmer::class);
        $criticalViewWarmer->expects(self::once())->method('warmRecruitJobList')->with('slug-1');

        $guard = $this->createMock(MessageIdempotenceGuard::class);
        $guard->method('shouldProcess')->willReturn(true);

        $elastic = $this->createMock(ElasticsearchServiceInterface::class);
        $elastic->expects(self::once())->method('delete')->with(RecruitJobProjection::INDEX_NAME, 'job-1');

        $handler = new EntityProjectionHandler(
            $applicationRepository,
            $jobRepository,
            $cacheInvalidationService,
            $criticalViewWarmer,
            $elastic,
            $guard,
        );

        $handler(new EntityDeleted('recruit_job', 'job-1', 'evt_2', [
            'applicationSlug' => 'slug-1',
        ]));
    }

    public function testThatItIsIdempotentForRetries(): void
    {
        $applicationRepository = $this->createMock(ApplicationRepository::class);
        $jobRepository = $this->createMock(JobRepository::class);
        $application = (new Application())->setTitle('app')->setPlatform((new Platform())->setName('p')->setPlatformKey('CRM'));
        $application->ensureGeneratedSlug();
        $recruit = (new Recruit())->setApplication($application);
        $job = (new Job())->setRecruit($recruit)->setTitle('Job 1');
        $job->ensureGeneratedSlug();
        $jobRepository->method('find')->willReturn($job);

        $cacheInvalidationService = $this->createMock(CacheInvalidationService::class);
        $cacheInvalidationService->expects(self::once())->method('invalidateRecruitJobListCaches');

        $criticalViewWarmer = $this->createMock(CriticalViewWarmer::class);
        $criticalViewWarmer->expects(self::once())->method('warmRecruitJobList');

        $guard = $this->createMock(MessageIdempotenceGuard::class);
        $guard->expects(self::exactly(2))->method('shouldProcess')->willReturnOnConsecutiveCalls(true, false);

        $elastic = $this->createMock(ElasticsearchServiceInterface::class);
        $elastic->expects(self::once())->method('index');

        $handler = new EntityProjectionHandler(
            $applicationRepository,
            $jobRepository,
            $cacheInvalidationService,
            $criticalViewWarmer,
            $elastic,
            $guard,
        );

        $message = new EntityCreated('recruit_job', 'job-1', 'evt_same', [
            'applicationSlug' => 'slug-1',
        ]);
        $handler($message);
        $handler($message);
    }
}
