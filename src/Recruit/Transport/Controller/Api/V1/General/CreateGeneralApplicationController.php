<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\General;

use App\Recruit\Application\Service\GeneralApplicationService;
use App\User\Domain\Entity\User;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Recruit General Application')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class CreateGeneralApplicationController
{
    public function __construct(
        private GeneralApplicationService $generalApplicationService,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
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
            new OA\Response(response: 401, description: 'Authentification requise.'),
            new OA\Response(response: 403, description: 'Accès refusé.'),
            new OA\Response(response: 404, description: 'Ressource liée introuvable.'),
        ],
    )]
    public function __invoke(Request $request, User $loggedInUser): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();

        return new JsonResponse($this->generalApplicationService->create($payload, $loggedInUser), JsonResponse::HTTP_CREATED);
    }
}
