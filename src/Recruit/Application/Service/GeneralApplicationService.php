<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\Recruit\Domain\Entity\Application;
use App\Recruit\Domain\Enum\ApplicationStatus;
use App\Recruit\Infrastructure\Repository\ApplicantRepository;
use App\Recruit\Infrastructure\Repository\ApplicationRepository;
use App\Recruit\Infrastructure\Repository\JobRepository;
use App\User\Domain\Entity\User;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function is_string;

readonly class GeneralApplicationService
{
    public function __construct(
        private ApplicationRepository $applicationRepository,
        private ApplicantRepository $applicantRepository,
        private JobRepository $jobRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{id: string, status: string}
     *
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function create(array $payload, User $loggedInUser): array
    {
        $applicantId = $payload['applicantId'] ?? null;
        $jobId = $payload['jobId'] ?? null;
        if (!is_string($applicantId) || !Uuid::isValid($applicantId)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "applicantId" must be a valid UUID.');
        }

        if (!is_string($jobId) || !Uuid::isValid($jobId)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "jobId" must be a valid UUID.');
        }

        $applicant = $this->applicantRepository->find($applicantId);
        if ($applicant === null) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Unknown "applicantId".');
        }

        if ($applicant->getUser()->getId() !== $loggedInUser->getId()) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'The given applicant does not belong to the authenticated user.');
        }

        $job = $this->jobRepository->find($jobId);
        if ($job === null) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Unknown "jobId".');
        }

        $existingApplication = $this->applicationRepository->findActiveByApplicantAndJob($applicant, $job);
        if ($existingApplication instanceof Application) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'An active application already exists for this applicant and job.');
        }

        $application = new Application()
            ->setApplicant($applicant)
            ->setJob($job)
            ->setStatus(ApplicationStatus::WAITING);

        $this->applicationRepository->save($application);

        return [
            'id' => $application->getId(),
            'status' => $application->getStatusValue(),
        ];
    }
}
