<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruit\Application\Service;

use App\Recruit\Application\Service\InterviewService;
use App\Recruit\Application\Service\RecruitNotificationService;
use App\Recruit\Domain\Entity\Applicant;
use App\Recruit\Domain\Entity\Application;
use App\Recruit\Domain\Entity\Interview;
use App\Recruit\Domain\Entity\Job;
use App\Recruit\Domain\Enum\ApplicationStatus;
use App\Recruit\Domain\Enum\InterviewMode;
use App\Recruit\Infrastructure\Repository\ApplicationRepository;
use App\Recruit\Infrastructure\Repository\InterviewRepository;
use App\User\Domain\Entity\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class InterviewServiceTest extends TestCase
{
    public function testCreateRejectsClosedApplication(): void
    {
        $owner = $this->createOwner();
        $application = $this->buildApplication($owner, ApplicationStatus::REJECTED);

        $applicationRepository = $this->createMock(ApplicationRepository::class);
        $applicationRepository->method('find')->willReturn($application);

        $interviewRepository = $this->createMock(InterviewRepository::class);
        $interviewRepository->expects(self::never())->method('save');

        $notificationService = $this->createMock(RecruitNotificationService::class);
        $notificationService->expects(self::never())->method('notifyInterviewScheduled');

        $service = new InterviewService($applicationRepository, $interviewRepository, $notificationService);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Cannot schedule or update interviews for applications with status REJECTED or HIRED.');

        $service->create('app-id', [
            'scheduledAt' => (new DateTimeImmutable('+1 day'))->format(DATE_ATOM),
            'durationMinutes' => 45,
            'mode' => 'visio',
            'locationOrUrl' => 'https://meet',
            'interviewerIds' => [],
        ], $owner);
    }

    public function testCreateRejectsPastDate(): void
    {
        $owner = $this->createOwner();
        $application = $this->buildApplication($owner, ApplicationStatus::WAITING);

        $applicationRepository = $this->createMock(ApplicationRepository::class);
        $applicationRepository->method('find')->willReturn($application);

        $interviewRepository = $this->createMock(InterviewRepository::class);
        $notificationService = $this->createMock(RecruitNotificationService::class);

        $service = new InterviewService($applicationRepository, $interviewRepository, $notificationService);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Field "scheduledAt" must be in the future.');

        $service->create('app-id', [
            'scheduledAt' => (new DateTimeImmutable('-1 hour'))->format(DATE_ATOM),
            'durationMinutes' => 45,
            'mode' => 'visio',
            'locationOrUrl' => 'https://meet',
            'interviewerIds' => [],
        ], $owner);
    }

    public function testCreatePersistsInterviewAndNotifies(): void
    {
        $owner = $this->createOwner();
        $application = $this->buildApplication($owner, ApplicationStatus::WAITING);

        $applicationRepository = $this->createMock(ApplicationRepository::class);
        $applicationRepository->method('find')->willReturn($application);

        $interviewRepository = $this->createMock(InterviewRepository::class);
        $interviewRepository->expects(self::once())->method('save')->with(self::isInstanceOf(Interview::class));

        $notificationService = $this->createMock(RecruitNotificationService::class);
        $notificationService->expects(self::once())->method('notifyInterviewScheduled')->with(self::isInstanceOf(Interview::class));

        $service = new InterviewService($applicationRepository, $interviewRepository, $notificationService);

        $interview = $service->create('app-id', [
            'scheduledAt' => (new DateTimeImmutable('+1 day'))->format(DATE_ATOM),
            'durationMinutes' => 30,
            'mode' => 'on-site',
            'locationOrUrl' => 'Paris HQ',
            'interviewerIds' => ['u-2'],
            'notes' => 'focus culture',
        ], $owner);

        self::assertSame(30, $interview->getDurationMinutes());
        self::assertSame('on-site', $interview->getMode()->value);
    }

    public function testUpdateTriggersUpdatedNotification(): void
    {
        $owner = $this->createOwner();
        $application = $this->buildApplication($owner, ApplicationStatus::INTERVIEW_PLANNED);

        $interview = (new Interview())
            ->setApplication($application)
            ->setScheduledAt(new DateTimeImmutable('+1 day'))
            ->setDurationMinutes(45)
            ->setMode(InterviewMode::VISIO)
            ->setLocationOrUrl('https://meet')
            ->setInterviewerIds([]);

        $interviewRepository = $this->createMock(InterviewRepository::class);
        $interviewRepository->method('find')->willReturn($interview);
        $interviewRepository->expects(self::once())->method('save')->with($interview);

        $applicationRepository = $this->createMock(ApplicationRepository::class);

        $notificationService = $this->createMock(RecruitNotificationService::class);
        $notificationService->expects(self::once())->method('notifyInterviewUpdated')->with($interview);
        $notificationService->expects(self::never())->method('notifyInterviewCanceled');

        $service = new InterviewService($applicationRepository, $interviewRepository, $notificationService);

        $service->update($interview->getId(), ['notes' => 'updated'], $owner);
    }

    public function testUpdateTriggersCanceledNotificationWhenStatusSwitchesToCanceled(): void
    {
        $owner = $this->createOwner();
        $application = $this->buildApplication($owner, ApplicationStatus::INTERVIEW_PLANNED);

        $interview = (new Interview())
            ->setApplication($application)
            ->setScheduledAt(new DateTimeImmutable('+1 day'))
            ->setDurationMinutes(45)
            ->setMode(InterviewMode::VISIO)
            ->setLocationOrUrl('https://meet')
            ->setInterviewerIds([]);

        $interviewRepository = $this->createMock(InterviewRepository::class);
        $interviewRepository->method('find')->willReturn($interview);
        $interviewRepository->expects(self::once())->method('save')->with($interview);

        $applicationRepository = $this->createMock(ApplicationRepository::class);

        $notificationService = $this->createMock(RecruitNotificationService::class);
        $notificationService->expects(self::once())->method('notifyInterviewCanceled')->with($interview);
        $notificationService->expects(self::never())->method('notifyInterviewUpdated');

        $service = new InterviewService($applicationRepository, $interviewRepository, $notificationService);

        $service->update($interview->getId(), ['status' => 'canceled'], $owner);
    }

    private function createOwner(): User
    {
        $owner = $this->createMock(User::class);
        $owner->method('getId')->willReturn('u1');

        return $owner;
    }

    private function buildApplication(User $owner, ApplicationStatus $status): Application
    {
        $job = (new Job())->setOwner($owner)->setTitle('X')->ensureGeneratedSlug();

        return (new Application())
            ->setApplicant(new Applicant())
            ->setJob($job)
            ->setStatus($status);
    }
}
