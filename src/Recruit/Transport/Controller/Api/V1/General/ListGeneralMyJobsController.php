<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\General;

use App\Recruit\Application\Service\GeneralMyJobListService;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Recruit General Job')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class ListGeneralMyJobsController
{
    public function __construct(
        private GeneralMyJobListService $generalMyJobListService,
    ) {
    }

    #[Route(path: '/v1/recruit/general/private/me/jobs', methods: [Request::METHOD_GET])]
    #[OA\Get(
        summary: 'Retourne les jobs créés et les jobs postulés par le user connecté.',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des jobs créés et des candidatures du user connecté.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'createdJobs',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '018f4f85-70f0-7f89-8d59-7cfc7b6d93b0'),
                                    new OA\Property(property: 'slug', type: 'string', example: 'developpeur-php-senior-paris'),
                                    new OA\Property(property: 'title', type: 'string', example: 'Développeur PHP Senior'),
                                    new OA\Property(property: 'company', type: 'string', example: 'BroWorld Tech'),
                                    new OA\Property(property: 'location', type: 'string', example: 'Paris'),
                                    new OA\Property(property: 'contractType', type: 'string', example: 'CDI'),
                                    new OA\Property(property: 'workMode', type: 'string', example: 'HYBRID'),
                                    new OA\Property(property: 'schedule', type: 'string', example: 'FULL_TIME'),
                                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2026-04-16T10:30:00+00:00', nullable: true),
                                    new OA\Property(property: 'owner', type: 'boolean', example: true),
                                    new OA\Property(property: 'apply', type: 'boolean', example: false),
                                ],
                            ),
                        ),
                        new OA\Property(
                            property: 'appliedJobs',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'applicationId', type: 'string', format: 'uuid', example: '018f4f85-73ba-7b9a-8e9b-4a1406828cf1'),
                                    new OA\Property(
                                        property: 'status',
                                        type: 'string',
                                        enum: ['WAITING', 'SCREENING', 'INTERVIEW_PLANNED', 'INTERVIEW_DONE', 'OFFER_SENT', 'HIRED', 'REJECTED'],
                                        example: 'INTERVIEW_PLANNED',
                                    ),
                                    new OA\Property(property: 'appliedAt', type: 'string', format: 'date-time', example: '2026-04-15T09:15:00+00:00', nullable: true),
                                    new OA\Property(
                                        property: 'job',
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '018f4f85-70f0-7f89-8d59-7cfc7b6d93b0'),
                                            new OA\Property(property: 'slug', type: 'string', example: 'backend-engineer-lyon'),
                                            new OA\Property(property: 'title', type: 'string', example: 'Backend Engineer'),
                                            new OA\Property(property: 'company', type: 'string', example: 'BroWorld Tech'),
                                            new OA\Property(property: 'location', type: 'string', example: 'Lyon'),
                                            new OA\Property(property: 'contractType', type: 'string', example: 'CDI'),
                                            new OA\Property(property: 'workMode', type: 'string', example: 'REMOTE'),
                                            new OA\Property(property: 'schedule', type: 'string', example: 'FULL_TIME'),
                                            new OA\Property(property: 'owner', type: 'boolean', example: false),
                                            new OA\Property(property: 'apply', type: 'boolean', example: true),
                                        ],
                                    ),
                                ],
                            ),
                        ),
                    ],
                ),
            ),
            new OA\Response(response: 400, description: 'Requête invalide.'),
            new OA\Response(response: 401, description: 'Utilisateur non authentifié.'),
            new OA\Response(response: 403, description: 'Accès refusé.'),
            new OA\Response(response: 404, description: 'Ressource introuvable.'),
        ],
    )]
    public function __invoke(User $loggedInUser): JsonResponse
    {
        return new JsonResponse($this->generalMyJobListService->getList($loggedInUser));
    }
}
