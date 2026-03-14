<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruit\Application\Service;

use App\Recruit\Application\Service\InterviewDecisionService;
use App\Recruit\Application\Service\InterviewFeedbackService;
use App\Recruit\Domain\Entity\Applicant;
use App\Recruit\Domain\Entity\Application;
use App\Recruit\Domain\Entity\Interview;
use App\Recruit\Domain\Entity\Job;
use App\Recruit\Infrastructure\Repository\ApplicationRepository;
use App\Recruit\Infrastructure\Repository\InterviewFeedbackRepository;
use App\Recruit\Infrastructure\Repository\InterviewRepository;
use App\User\Domain\Entity\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class InterviewFeedbackServiceTest extends TestCase
{
    public function testUpsertDeniedForUserWhoIsNotAssignedInterviewer(): void
    {
        $hiringManager = $this->mockUser('hiring-manager');
        $interviewer = $this->mockUser('interviewer-1');
        $intruder = $this->mockUser('intruder');

        $interview = $this->buildInterview($hiringManager, [$interviewer->getId()]);

        $interviewRepository = $this->createMock(InterviewRepository::class);
        $interviewRepository->method('find')->willReturn($interview);

        $feedbackRepository = $this->createMock(InterviewFeedbackRepository::class);
        $applicationRepository = $this->createMock(ApplicationRepository::class);

        $service = new InterviewFeedbackService($interviewRepository, $feedbackRepository, $applicationRepository, new InterviewDecisionService());

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Only assigned interviewers can submit feedback for this interview.');

        $service->upsert($interview->getId(), [
            'skills' => 4,
            'communication' => 4,
            'cultureFit' => 4,
            'recommendation' => 'hire',
        ], $intruder);
    }

    public function testSummaryDeniedForUserWhoIsNeitherInterviewerNorHiringManager(): void
    {
        $hiringManager = $this->mockUser('hiring-manager');
        $interviewer = $this->mockUser('interviewer-1');
        $intruder = $this->mockUser('intruder');

        $application = $this->buildApplication($hiringManager);
        $interview = $this->buildInterview($hiringManager, [$interviewer->getId()]);
        $application->getInterviews()->add($interview);

        $applicationRepository = $this->createMock(ApplicationRepository::class);
        $applicationRepository->method('find')->willReturn($application);

        $interviewRepository = $this->createMock(InterviewRepository::class);
        $feedbackRepository = $this->createMock(InterviewFeedbackRepository::class);

        $service = new InterviewFeedbackService($interviewRepository, $feedbackRepository, $applicationRepository, new InterviewDecisionService());

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Only assigned interviewers or the hiring manager can access this summary.');

        $service->getApplicationSummary($application->getId(), $intruder);
    }

    private function buildInterview(User $hiringManager, array $interviewerIds): Interview
    {
        return (new Interview())
            ->setApplication($this->buildApplication($hiringManager))
            ->setScheduledAt(new DateTimeImmutable('+1 day'))
            ->setDurationMinutes(60)
            ->setMode('visio')
            ->setLocationOrUrl('https://meet')
            ->setInterviewerIds($interviewerIds);
    }

    private function buildApplication(User $hiringManager): Application
    {
        $job = (new Job())
            ->setOwner($hiringManager)
            ->setTitle('Hiring')
            ->ensureGeneratedSlug();

        return (new Application())
            ->setApplicant(new Applicant())
            ->setJob($job);
    }

    private function mockUser(string $id): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);

        return $user;
    }
}
