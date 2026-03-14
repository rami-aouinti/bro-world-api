<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\Recruit\Domain\Entity\Application;
use App\Recruit\Domain\Entity\Interview;
use App\Recruit\Domain\Enum\ApplicationStatus;
use App\Recruit\Domain\Enum\InterviewMode;
use App\Recruit\Domain\Enum\InterviewStatus;
use App\Recruit\Infrastructure\Repository\ApplicationRepository;
use App\Recruit\Infrastructure\Repository\InterviewRepository;
use App\User\Domain\Entity\User;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function array_filter;
use function array_map;
use function array_values;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function mb_strlen;
use function strtolower;
use function trim;

readonly class InterviewService
{
    private const int MIN_DURATION_MINUTES = 15;
    private const int MAX_DURATION_MINUTES = 240;

    public function __construct(
        private ApplicationRepository $applicationRepository,
        private InterviewRepository $interviewRepository,
        private RecruitNotificationService $recruitNotificationService,
    ) {
    }

    /** @param array<string,mixed> $payload */
    public function create(string $applicationId, array $payload, User $loggedInUser): Interview
    {
        $application = $this->getOwnedApplication($applicationId, $loggedInUser);
        $this->assertApplicationAllowsInterview($application);

        $interview = (new Interview())
            ->setApplication($application)
            ->setScheduledAt($this->extractScheduledAt($payload))
            ->setDurationMinutes($this->extractDuration($payload))
            ->setMode($this->extractMode($payload))
            ->setLocationOrUrl($this->extractLocationOrUrl($payload))
            ->setInterviewerIds($this->extractInterviewerIds($payload))
            ->setStatus($this->extractStatus($payload, false))
            ->setNotes($this->extractNotes($payload));

        $this->interviewRepository->save($interview);
        $this->recruitNotificationService->notifyInterviewScheduled($interview);

        return $interview;
    }

    /** @param array<string,mixed> $payload */
    public function update(string $interviewId, array $payload, User $loggedInUser): Interview
    {
        $interview = $this->interviewRepository->find($interviewId);
        if (!$interview instanceof Interview) {
            throw new NotFoundHttpException('Interview not found.');
        }

        $application = $interview->getApplication();
        $this->assertApplicationOwnership($application, $loggedInUser);
        $this->assertApplicationAllowsInterview($application);

        if (isset($payload['scheduledAt'])) {
            $interview->setScheduledAt($this->extractScheduledAt($payload));
        }

        if (isset($payload['durationMinutes'])) {
            $interview->setDurationMinutes($this->extractDuration($payload));
        }

        if (isset($payload['mode'])) {
            $interview->setMode($this->extractMode($payload));
        }

        if (isset($payload['locationOrUrl'])) {
            $interview->setLocationOrUrl($this->extractLocationOrUrl($payload));
        }

        if (isset($payload['interviewerIds'])) {
            $interview->setInterviewerIds($this->extractInterviewerIds($payload));
        }

        $statusBeforeUpdate = $interview->getStatus();

        if (isset($payload['status'])) {
            $interview->setStatus($this->extractStatus($payload, true));
        }

        if (isset($payload['notes'])) {
            $interview->setNotes($this->extractNotes($payload));
        }

        $this->interviewRepository->save($interview);

        if ($interview->getStatus() === InterviewStatus::CANCELED && $statusBeforeUpdate !== InterviewStatus::CANCELED) {
            $this->recruitNotificationService->notifyInterviewCanceled($interview);
        } else {
            $this->recruitNotificationService->notifyInterviewUpdated($interview);
        }

        return $interview;
    }

    public function delete(string $interviewId, User $loggedInUser): void
    {
        $interview = $this->interviewRepository->find($interviewId);
        if (!$interview instanceof Interview) {
            throw new NotFoundHttpException('Interview not found.');
        }

        $this->assertApplicationOwnership($interview->getApplication(), $loggedInUser);

        $this->interviewRepository->remove($interview);
    }

    /** @return array<int, Interview> */
    public function listByApplication(string $applicationId, User $loggedInUser): array
    {
        $application = $this->getOwnedApplication($applicationId, $loggedInUser);

        return $this->interviewRepository->findByApplicationOrdered($application);
    }

    private function getOwnedApplication(string $applicationId, User $loggedInUser): Application
    {
        $application = $this->applicationRepository->find($applicationId);
        if (!$application instanceof Application) {
            throw new NotFoundHttpException('Application not found.');
        }

        $this->assertApplicationOwnership($application, $loggedInUser);

        return $application;
    }

    private function assertApplicationOwnership(Application $application, User $loggedInUser): void
    {
        if ($application->getJob()->getOwner()?->getId() !== $loggedInUser->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'You are not allowed to manage interviews for this application.');
        }
    }

    private function assertApplicationAllowsInterview(Application $application): void
    {
        if (in_array($application->getStatus(), [ApplicationStatus::REJECTED, ApplicationStatus::HIRED], true)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Cannot schedule or update interviews for applications with status REJECTED or HIRED.');
        }
    }

    /** @param array<string,mixed> $payload */
    private function extractScheduledAt(array $payload): DateTimeImmutable
    {
        if (!is_string($payload['scheduledAt'] ?? null)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "scheduledAt" must be a valid datetime string.');
        }

        try {
            $scheduledAt = new DateTimeImmutable($payload['scheduledAt']);
        } catch (\Throwable) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "scheduledAt" must be a valid datetime string.');
        }

        if ($scheduledAt <= new DateTimeImmutable()) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "scheduledAt" must be in the future.');
        }

        return $scheduledAt;
    }

    /** @param array<string,mixed> $payload */
    private function extractDuration(array $payload): int
    {
        if (!is_int($payload['durationMinutes'] ?? null)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "durationMinutes" must be an integer.');
        }

        $duration = $payload['durationMinutes'];
        if ($duration < self::MIN_DURATION_MINUTES || $duration > self::MAX_DURATION_MINUTES) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "durationMinutes" must be between 15 and 240.');
        }

        return $duration;
    }

    /** @param array<string,mixed> $payload */
    private function extractMode(array $payload): InterviewMode
    {
        if (!is_string($payload['mode'] ?? null)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "mode" must be one of: visio, on-site.');
        }

        $mode = InterviewMode::tryFrom($payload['mode']);
        if ($mode === null) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "mode" must be one of: visio, on-site.');
        }

        return $mode;
    }

    /** @param array<string,mixed> $payload */
    private function extractLocationOrUrl(array $payload): string
    {
        if (!is_string($payload['locationOrUrl'] ?? null) || trim($payload['locationOrUrl']) === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "locationOrUrl" must be a non-empty string.');
        }

        return trim($payload['locationOrUrl']);
    }

    /** @param array<string,mixed> $payload @return array<int,string> */
    private function extractInterviewerIds(array $payload): array
    {
        if (!is_array($payload['interviewerIds'] ?? null)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "interviewerIds" must be an array of user IDs.');
        }

        $interviewerIds = array_values(array_filter(array_map(static fn (mixed $value): ?string => is_string($value) && trim($value) !== '' ? trim($value) : null, $payload['interviewerIds'])));

        return $interviewerIds;
    }

    /** @param array<string,mixed> $payload */
    private function extractStatus(array $payload, bool $allowDoneAndCanceled): InterviewStatus
    {
        if (!is_string($payload['status'] ?? null)) {
            return InterviewStatus::PLANNED;
        }

        $status = InterviewStatus::tryFrom(strtolower($payload['status']));
        if ($status === null) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "status" must be one of: planned, done, canceled.');
        }

        if (!$allowDoneAndCanceled && $status !== InterviewStatus::PLANNED) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "status" can only be "planned" when creating an interview.');
        }

        return $status;
    }

    /** @param array<string,mixed> $payload */
    private function extractNotes(array $payload): ?string
    {
        if (!isset($payload['notes'])) {
            return null;
        }

        if (!is_string($payload['notes'])) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "notes" must be a string or null.');
        }

        $notes = trim($payload['notes']);

        if ($notes === '') {
            return null;
        }

        if (mb_strlen($notes) > 5000) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "notes" is too long.');
        }

        return $notes;
    }
}
