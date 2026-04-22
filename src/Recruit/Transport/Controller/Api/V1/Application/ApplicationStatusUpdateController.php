<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Application;

use App\Recruit\Application\Security\RecruitPermissions;
use App\Recruit\Application\Service\ApplicationStatusTransitionService;
use App\Recruit\Infrastructure\Repository\ApplicationRepository;
use App\User\Domain\Entity\User;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function is_string;

#[AsController]
#[OA\Tag(name: 'Recruit Application')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[IsGranted(RecruitPermissions::APPLICATION_STATUS_TRANSITION)]
readonly class ApplicationStatusUpdateController
{
    public function __construct(
        private ApplicationRepository $applicationRepository,
        private ApplicationStatusTransitionService $applicationStatusTransitionService,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    #[Route(path: '/v1/recruit/private/applications/{applicationId}/status', methods: [Request::METHOD_PATCH, Request::METHOD_PUT])]
    #[OA\Parameter(name: 'applicationSlug', in: 'query', required: true, schema: new OA\Schema(type: 'string'))]
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
        $comment = is_string($payload['comment'] ?? null) ? $payload['comment'] : null;
        $this->applicationStatusTransitionService->applyStatusTransition($application, $payload['status'] ?? null, $loggedInUser, $comment);

        $this->applicationRepository->save($application);

        return new JsonResponse([
            'id' => $application->getId(),
            'status' => $application->getStatusValue(),
        ]);
    }
}
