<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\General;

use App\Recruit\Application\Security\RecruitPermissions;
use App\Recruit\Application\Service\GeneralApplicationStatusService;
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
#[OA\Tag(name: 'Recruit Application')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[IsGranted(RecruitPermissions::APPLICATION_STATUS_TRANSITION)]
final readonly class UpdateGeneralApplicationStatusController
{
    public function __construct(
        private GeneralApplicationStatusService $generalApplicationStatusService,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    #[Route(path: '/v1/recruit/general/private/applications/{applicationId}/status', methods: [Request::METHOD_PATCH])]
    #[OA\Parameter(name: 'applicationId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Patch(
        summary: 'Modifie le statut d\'une candidature.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['WAITING', 'SCREENING', 'INTERVIEW_PLANNED', 'INTERVIEW_DONE', 'OFFER_SENT', 'HIRED', 'REJECTED']),
                    new OA\Property(property: 'comment', type: 'string', nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Statut de candidature mis à jour.'),
            new OA\Response(response: 400, description: 'Transition invalide ou payload incomplet.'),
            new OA\Response(response: 403, description: 'Vous n\'êtes pas propriétaire du job.'),
            new OA\Response(response: 404, description: 'Candidature introuvable.'),
        ],
    )]
    public function __invoke(string $applicationId, Request $request, User $loggedInUser): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();

        return new JsonResponse($this->generalApplicationStatusService->updateStatus($applicationId, $payload, $loggedInUser));
    }
}
