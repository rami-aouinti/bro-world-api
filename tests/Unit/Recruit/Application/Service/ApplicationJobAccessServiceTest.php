<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruit\Application\Service;

use App\Platform\Domain\Entity\Application as PlatformApplication;
use App\Recruit\Application\Service\ApplicationJobAccessService;
use App\Recruit\Application\Service\RecruitResolverService;
use App\Recruit\Domain\Entity\Job;
use App\Recruit\Domain\Entity\Recruit;
use App\Recruit\Infrastructure\Repository\JobRepository;
use App\User\Domain\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ApplicationJobAccessServiceTest extends TestCase
{
    public function testResolveOwnedRecruitByApplicationSlugReturnsRecruitForOwner(): void
    {
        $loggedInUser = $this->createMock(User::class);
        $owner = $this->createMock(User::class);
        $application = $this->createMock(PlatformApplication::class);
        $recruit = $this->createMock(Recruit::class);

        $loggedInUser->method('getId')->willReturn('user-id');
        $owner->method('getId')->willReturn('user-id');
        $application->method('getUser')->willReturn($owner);
        $recruit->method('getApplication')->willReturn($application);

        $recruitResolverService = $this->createMock(RecruitResolverService::class);
        $jobRepository = $this->createMock(JobRepository::class);
        $recruitResolverService->method('resolveByApplicationSlug')->with('app-slug')->willReturn($recruit);

        $service = new ApplicationJobAccessService($recruitResolverService, $jobRepository);

        self::assertSame($recruit, $service->resolveOwnedRecruitByApplicationSlug('app-slug', $loggedInUser, 'forbidden'));
    }

    public function testResolveOwnedRecruitByApplicationSlugThrowsForbiddenForNonOwner(): void
    {
        $loggedInUser = $this->createMock(User::class);
        $owner = $this->createMock(User::class);
        $application = $this->createMock(PlatformApplication::class);
        $recruit = $this->createMock(Recruit::class);

        $loggedInUser->method('getId')->willReturn('user-id');
        $owner->method('getId')->willReturn('other-id');
        $application->method('getUser')->willReturn($owner);
        $recruit->method('getApplication')->willReturn($application);

        $recruitResolverService = $this->createMock(RecruitResolverService::class);
        $jobRepository = $this->createMock(JobRepository::class);
        $recruitResolverService->method('resolveByApplicationSlug')->willReturn($recruit);

        $service = new ApplicationJobAccessService($recruitResolverService, $jobRepository);

        try {
            $service->resolveOwnedRecruitByApplicationSlug('app-slug', $loggedInUser, 'forbidden');
            self::fail('Expected HttpException was not thrown.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
            self::assertSame('forbidden', $exception->getMessage());
        }
    }

    public function testResolveJobForRecruitThrowsNotFoundWhenJobIsMissing(): void
    {
        $recruitResolverService = $this->createMock(RecruitResolverService::class);
        $jobRepository = $this->createMock(JobRepository::class);
        $recruit = $this->createMock(Recruit::class);

        $jobRepository->method('find')->with('job-id')->willReturn(null);

        $service = new ApplicationJobAccessService($recruitResolverService, $jobRepository);

        try {
            $service->resolveJobForRecruit('job-id', $recruit);
            self::fail('Expected HttpException was not thrown.');
        } catch (HttpException $exception) {
            self::assertSame(404, $exception->getStatusCode());
            self::assertSame('Job not found.', $exception->getMessage());
        }
    }

    public function testResolveJobForRecruitThrowsForbiddenWhenJobBelongsToAnotherRecruit(): void
    {
        $jobRecruit = $this->createMock(Recruit::class);
        $requestedRecruit = $this->createMock(Recruit::class);
        $job = $this->createMock(Job::class);

        $jobRecruit->method('getId')->willReturn('recruit-a');
        $requestedRecruit->method('getId')->willReturn('recruit-b');
        $job->method('getRecruit')->willReturn($jobRecruit);

        $recruitResolverService = $this->createMock(RecruitResolverService::class);
        $jobRepository = $this->createMock(JobRepository::class);
        $jobRepository->method('find')->with('job-id')->willReturn($job);

        $service = new ApplicationJobAccessService($recruitResolverService, $jobRepository);

        try {
            $service->resolveJobForRecruit('job-id', $requestedRecruit);
            self::fail('Expected HttpException was not thrown.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
            self::assertSame('This job does not belong to the given application.', $exception->getMessage());
        }
    }
}
