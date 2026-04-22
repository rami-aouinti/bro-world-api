<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\General;

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
#[OA\Tag(name: 'Recruit General Resume')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class ListGeneralMyResumesController
{
    public function __construct(
        private ResumeRepository $resumeRepository,
        private ResumeNormalizerService $resumeNormalizerService,
    ) {
    }

    #[OA\Get(
        summary: 'Retourne les CV du user connecté.',
        responses: [
            new OA\Response(response: 200, description: 'Liste des CV récupérée.'),
            new OA\Response(response: 400, description: 'Requête invalide.'),
            new OA\Response(response: 401, description: 'Authentification requise.'),
            new OA\Response(response: 403, description: 'Accès refusé.'),
            new OA\Response(response: 404, description: 'Ressource introuvable.'),
        ],
    )]
    public function __invoke(User $loggedInUser): JsonResponse
    {
        $resumes = $this->resumeRepository->findBy([
            'owner' => $loggedInUser,
        ], [
            'createdAt' => 'DESC',
        ]);

        return new JsonResponse($this->resumeNormalizerService->normalizeCollection($resumes));
    }
}
