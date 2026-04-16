<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\General;

use App\Recruit\Application\Service\GeneralResumeService;
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
final readonly class ListGeneralMyResumesController
{
    public function __construct(
        private GeneralResumeService $generalResumeService,
    ) {
    }

    #[Route(path: '/v1/recruit/general/private/me/resumes', methods: [Request::METHOD_GET])]
    #[OA\Get(summary: 'Retourne les CV du user connecté.')]
    public function __invoke(User $loggedInUser): JsonResponse
    {
        return new JsonResponse($this->generalResumeService->getMyResumes($loggedInUser));
    }
}
