<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Resume;

use App\Recruit\Application\Service\ResumePayloadService;
use App\Recruit\Application\Service\ResumeNormalizerService;
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
readonly class MyResumePatchController
{
    public function __construct(
        private ResumeRepository $resumeRepository,
        private ResumePayloadService $resumePayloadService,
        private ResumeNormalizerService $resumeNormalizerService,
    ) {
    }

    #[Route(path: '/v1/recruit/applications/{applicationSlug}/private/me/resumes/{resumeId}', methods: [Request::METHOD_PATCH])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Patch(summary: 'Met à jour un CV appartenant au user connecté.')]
    public function __invoke(string $applicationSlug, string $resumeId, Request $request, User $loggedInUser): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        if (!Uuid::isValid($resumeId)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Route "resumeId" must be a valid UUID.');
        }

        $resume = $this->resumeRepository->find($resumeId);
        if (!$resume instanceof Resume) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Resume not found.');
        }

        if ($resume->getOwner()->getId() !== $loggedInUser->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'You cannot update this resume.');
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();
        $this->resumePayloadService->replaceResumeSections($resume, $payload);
        $this->resumePayloadService->applyResumeInformationForPatch($resume, $payload);

        $this->resumeRepository->save($resume);

        return new JsonResponse($this->resumeNormalizerService->normalize($resume));
    }
}
