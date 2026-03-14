<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruit\Application\Service;

use App\Recruit\Application\Service\ApplicationDiscussionBootstrapService;
use App\Recruit\Application\Service\ApplicationStatusTransitionService;
use App\Recruit\Domain\Entity\Application;
use App\Recruit\Domain\Entity\ApplicationStatusHistory;
use App\Recruit\Domain\Enum\ApplicationStatus;
use App\Recruit\Infrastructure\Repository\ApplicationStatusHistoryRepository;
use App\User\Domain\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ApplicationStatusTransitionServiceTest extends TestCase
{
    public function testApplyStatusTransitionAllowsValidTransitionAndPersistsHistory(): void
    {
        $application = (new Application())->setStatus(ApplicationStatus::WAITING);
        $author = $this->createMock(User::class);

        $bootstrapService = $this->createMock(ApplicationDiscussionBootstrapService::class);
        $bootstrapService->expects(self::once())
            ->method('bootstrap')
            ->with($application);

        $historyRepository = $this->createMock(ApplicationStatusHistoryRepository::class);
        $historyRepository->expects(self::once())
            ->method('save')
            ->with(
                self::callback(static function (mixed $history) use ($application, $author): bool {
                    if (!$history instanceof ApplicationStatusHistory) {
                        return false;
                    }

                    return $history->getApplication() === $application
                        && $history->getAuthor() === $author
                        && $history->getFromStatus() === ApplicationStatus::WAITING
                        && $history->getToStatus() === ApplicationStatus::SCREENING
                        && $history->getComment() === 'Commentaire RH';
                }),
                false,
            );

        $service = new ApplicationStatusTransitionService($bootstrapService, $historyRepository);

        $service->applyStatusTransition($application, ApplicationStatus::SCREENING->value, $author, 'Commentaire RH');

        self::assertSame(ApplicationStatus::SCREENING, $application->getStatus());
    }

    public function testApplyStatusTransitionThrowsWhenTransitionIsNotAllowed(): void
    {
        $application = (new Application())->setStatus(ApplicationStatus::WAITING);
        $author = $this->createMock(User::class);

        $bootstrapService = $this->createMock(ApplicationDiscussionBootstrapService::class);
        $historyRepository = $this->createMock(ApplicationStatusHistoryRepository::class);
        $historyRepository->expects(self::never())->method('save');

        $service = new ApplicationStatusTransitionService($bootstrapService, $historyRepository);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Cannot transition application status from WAITING to HIRED. Allowed next statuses: SCREENING, REJECTED.');

        $service->applyStatusTransition($application, ApplicationStatus::HIRED->value, $author);
    }

    public function testApplyStatusTransitionThrowsWhenStatusIsInvalid(): void
    {
        $application = (new Application())->setStatus(ApplicationStatus::WAITING);
        $author = $this->createMock(User::class);

        $bootstrapService = $this->createMock(ApplicationDiscussionBootstrapService::class);
        $historyRepository = $this->createMock(ApplicationStatusHistoryRepository::class);

        $service = new ApplicationStatusTransitionService($bootstrapService, $historyRepository);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Field "status" must be one of: WAITING, SCREENING, INTERVIEW_PLANNED, INTERVIEW_DONE, OFFER_SENT, HIRED, REJECTED.');

        $service->applyStatusTransition($application, 'UNKNOWN', $author);
    }
}
