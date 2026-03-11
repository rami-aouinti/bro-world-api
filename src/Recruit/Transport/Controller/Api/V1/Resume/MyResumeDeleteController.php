<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Resume;

use App\Recruit\Domain\Entity\Resume;
use App\Recruit\Infrastructure\Repository\ResumeRepository;
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
#[OA\Tag(name: 'Recruit Resume')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
readonly class MyResumeDeleteController
{
    public function __construct(
        private ResumeRepository $resumeRepository
    ) {
    }

    #[Route(path: '/v1/recruit/{applicationSlug}/private/me/resumes/{resumeId}', methods: [Request::METHOD_DELETE])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Delete(summary: 'Supprime un CV appartenant au user connecté.')]
    public function __invoke(string $applicationSlug, string $resumeId, User $loggedInUser): JsonResponse
    {
        if (!Uuid::isValid($resumeId)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Route "resumeId" must be a valid UUID.');
        }

        $resume = $this->resumeRepository->find($resumeId);
        if (!$resume instanceof Resume) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Resume not found.');
        }

        if ($resume->getOwner()->getId() !== $loggedInUser->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'You cannot delete this resume.');
        }

        $this->resumeRepository->remove($resume);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
