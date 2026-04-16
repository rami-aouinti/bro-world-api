<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\Recruit\Domain\Entity\Applicant;
use App\Recruit\Infrastructure\Repository\ApplicantRepository;
use App\Recruit\Infrastructure\Repository\ResumeRepository;
use App\User\Domain\Entity\User;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function is_string;
use function trim;

readonly class GeneralApplicantService
{
    public function __construct(
        private ApplicantRepository $applicantRepository,
        private ResumeRepository $resumeRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{id: string}
     *
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function create(array $payload, User $loggedInUser): array
    {
        $resumeId = $payload['resumeId'] ?? null;
        $coverLetter = $payload['coverLetter'] ?? '';

        if (!is_string($resumeId) || !Uuid::isValid($resumeId)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "resumeId" must be a valid UUID.');
        }

        if (!is_string($coverLetter)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "coverLetter" must be a string.');
        }

        $resume = $this->resumeRepository->find($resumeId);
        if ($resume === null) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Unknown "resumeId".');
        }

        if ($resume->getOwner()->getId() !== $loggedInUser->getId()) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'The given resume does not belong to the authenticated user.');
        }

        $applicant = new Applicant()
            ->setUser($loggedInUser)
            ->setResume($resume)
            ->setCoverLetter(trim($coverLetter));

        $this->applicantRepository->save($applicant);

        return [
            'id' => $applicant->getId(),
        ];
    }
}
