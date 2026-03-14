<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\Recruit\Domain\Entity\Application;
use App\Recruit\Domain\Entity\ApplicationStatusHistory;
use App\Recruit\Domain\Enum\ApplicationStatus;
use App\Recruit\Infrastructure\Repository\ApplicationStatusHistoryRepository;
use App\User\Domain\Entity\User;
use DomainException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function array_key_exists;
use function array_map;
use function implode;
use function in_array;
use function is_string;
use function strtoupper;

readonly class ApplicationStatusTransitionService
{
    public function __construct(
        private ApplicationDiscussionBootstrapService $applicationDiscussionBootstrapService,
        private ApplicationStatusHistoryRepository $applicationStatusHistoryRepository,
        private RecruitNotificationService $recruitNotificationService,
    ) {
    }

    public function applyStatusTransition(Application $application, mixed $status, User $author, ?string $comment = null): void
    {
        if (!is_string($status)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "status" must be provided as a string.');
        }

        $newStatus = ApplicationStatus::tryFrom(strtoupper($status));
        if ($newStatus === null) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "status" must be one of: WAITING, SCREENING, INTERVIEW_PLANNED, INTERVIEW_DONE, OFFER_SENT, HIRED, REJECTED.');
        }

        $currentStatus = $application->getStatus();

        if ($newStatus === $currentStatus) {
            return;
        }

        if (!$this->isAllowedTransition($currentStatus, $newStatus)) {
            $allowedStatuses = $this->getAllowedTransitions($currentStatus);
            $allowedStatusesAsString = $allowedStatuses === []
                ? 'none'
                : implode(', ', array_map(static fn (ApplicationStatus $applicationStatus): string => $applicationStatus->value, $allowedStatuses));

            throw new HttpException(
                JsonResponse::HTTP_BAD_REQUEST,
                'Cannot transition application status from ' . $currentStatus->value . ' to ' . $newStatus->value . '. Allowed next statuses: ' . $allowedStatusesAsString . '.',
            );
        }

        if ($newStatus === ApplicationStatus::SCREENING && $currentStatus !== ApplicationStatus::SCREENING) {
            try {
                $this->applicationDiscussionBootstrapService->bootstrap($application);
            } catch (DomainException $exception) {
                throw new HttpException(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, $exception->getMessage(), $exception);
            }
        }

        $application->setStatus($newStatus);

        $history = (new ApplicationStatusHistory())
            ->setApplication($application)
            ->setAuthor($author)
            ->setFromStatus($currentStatus)
            ->setToStatus($newStatus)
            ->setComment($comment);

        $this->applicationStatusHistoryRepository->save($history, false);

        $this->recruitNotificationService->notifyStatusUpdated($application, $currentStatus, $newStatus);

        if ($newStatus === ApplicationStatus::OFFER_SENT) {
            $this->recruitNotificationService->notifyOfferSent($application);
        }
    }

    private function isAllowedTransition(ApplicationStatus $from, ApplicationStatus $to): bool
    {
        return in_array($to, $this->getAllowedTransitions($from), true);
    }

    /**
     * @return array<int, ApplicationStatus>
     */
    private function getAllowedTransitions(ApplicationStatus $from): array
    {
        $allowedTransitions = [
            ApplicationStatus::WAITING->value => [ApplicationStatus::SCREENING, ApplicationStatus::REJECTED],
            ApplicationStatus::SCREENING->value => [ApplicationStatus::INTERVIEW_PLANNED, ApplicationStatus::REJECTED],
            ApplicationStatus::INTERVIEW_PLANNED->value => [ApplicationStatus::INTERVIEW_DONE, ApplicationStatus::REJECTED],
            ApplicationStatus::INTERVIEW_DONE->value => [ApplicationStatus::OFFER_SENT, ApplicationStatus::REJECTED],
            ApplicationStatus::OFFER_SENT->value => [ApplicationStatus::HIRED, ApplicationStatus::REJECTED],
            ApplicationStatus::HIRED->value => [],
            ApplicationStatus::REJECTED->value => [],
        ];

        if (!array_key_exists($from->value, $allowedTransitions)) {
            return [];
        }

        return $allowedTransitions[$from->value];
    }
}
