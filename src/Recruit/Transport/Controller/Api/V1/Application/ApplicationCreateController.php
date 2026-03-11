<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Application;

use App\Recruit\Domain\Entity\Application;
use App\Recruit\Domain\Enum\ApplicationStatus;
use App\Recruit\Infrastructure\Repository\ApplicantRepository;
use App\Recruit\Infrastructure\Repository\ApplicationRepository;
use App\Recruit\Infrastructure\Repository\JobRepository;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Recruit Application')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
class ApplicationCreateController
{
    public function __construct(
        private readonly ApplicationRepository $applicationRepository,
        private readonly ApplicantRepository $applicantRepository,
        private readonly JobRepository $jobRepository,
    ) {
    }

    #[Route(path: '/v1/recruit/applications/{applicationSlug}/applications', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Post(
        summary: 'Crée une candidature pour un job.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['applicantId', 'jobId'],
                properties: [
                    new OA\Property(property: 'applicantId', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'jobId', type: 'string', format: 'uuid'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Candidature créée.'),
            new OA\Response(response: 400, description: 'Payload invalide.'),
        ],
    )]
    public function __invoke(string $applicationSlug, Request $request, User $loggedInUser): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();

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

        $application = (new Application())
            ->setApplicant($applicant)
            ->setJob($job)
            ->setStatus(ApplicationStatus::WAITING);

        $this->applicationRepository->save($application);

        return new JsonResponse([
            'id' => $application->getId(),
            'status' => $application->getStatusValue(),
        ], JsonResponse::HTTP_CREATED);
    }
}
