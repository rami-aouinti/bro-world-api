<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Application;

use App\Recruit\Application\Service\ApplicationStatusTransitionService;
use App\Recruit\Infrastructure\Repository\ApplicationRepository;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Recruit Application')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
class ApplicationStatusUpdateController
{
    public function __construct(
        private readonly ApplicationRepository $applicationRepository,
        private readonly ApplicationStatusTransitionService $applicationStatusTransitionService,
    ) {
    }

    #[Route(path: '/v1/recruit/{applicationSlug}/private/applications/{applicationId}/status', methods: [Request::METHOD_PATCH, Request::METHOD_PUT])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Patch(
        summary: 'Modifie le statut d\'une candidature.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['WAITING', 'IN_PROGRESS', 'DISCUSSION', 'INVITE_TO_INTERVIEW', 'INTERVIEW', 'ACCEPTED', 'REJECTED']),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Statut de candidature mis à jour.'),
            new OA\Response(response: 403, description: 'Vous n\'êtes pas propriétaire du job.'),
        ],
    )]
    public function __invoke(string $applicationSlug, string $applicationId, Request $request, User $loggedInUser): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        $application = $this->applicationRepository->find($applicationId);

        if ($application === null) {
            throw new NotFoundHttpException('Application not found.');
        }

        $ownerId = $application->getJob()->getOwner()?->getId();
        if ($ownerId === null || $ownerId !== $loggedInUser->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'You are not allowed to update the status for this application.');
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();
        $this->applicationStatusTransitionService->applyStatusTransition($application, $payload['status'] ?? null);

        $this->applicationRepository->save($application);

        return new JsonResponse([
            'id' => $application->getId(),
            'status' => $application->getStatusValue(),
        ]);
    }
}
