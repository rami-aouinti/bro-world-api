<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Resume;

use App\Recruit\Application\Service\ResumeNormalizerService;
use App\Recruit\Infrastructure\Repository\ResumeRepository;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Recruit Resume')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
readonly class MyResumeListController
{
    public function __construct(
        private ResumeRepository $resumeRepository,
        private ResumeNormalizerService $resumeNormalizerService,
    ) {
    }

    #[Route(path: '/v1/recruit/private/me/resumes', methods: [Request::METHOD_GET])]
        #[OA\Get(summary: 'Retourne les CV du user connecté.')]
    public function __invoke(string $applicationSlug, User $loggedInUser): JsonResponse
    {
        $resumes = $this->resumeRepository->findBy([
            'owner' => $loggedInUser,
        ], [
            'createdAt' => 'DESC',
        ]);

        return new JsonResponse($this->resumeNormalizerService->normalizeCollection($resumes));
    }
}
