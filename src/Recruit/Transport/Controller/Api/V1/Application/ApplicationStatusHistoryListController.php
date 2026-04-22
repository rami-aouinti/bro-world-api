<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Application;

use App\Recruit\Application\Security\RecruitPermissions;
use App\Recruit\Domain\Entity\ApplicationStatusHistory;
use App\Recruit\Infrastructure\Repository\ApplicationRepository;
use App\Recruit\Infrastructure\Repository\ApplicationStatusHistoryRepository;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Recruit Application')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[IsGranted(RecruitPermissions::APPLICATION_STATUS_HISTORY_VIEW)]
readonly class ApplicationStatusHistoryListController
{
    public function __construct(
        private ApplicationRepository $applicationRepository,
        private ApplicationStatusHistoryRepository $applicationStatusHistoryRepository,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    #[Route(path: '/v1/recruit/private/applications/{applicationId}/status-history', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'applicationSlug', in: 'query', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'applicationId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Get(
        summary: 'Retourne l\'historique des transitions de statut d\'une candidature.',
        responses: [
            new OA\Response(response: 200, description: 'Historique des transitions.'),
            new OA\Response(response: 403, description: 'Accès interdit.'),
            new OA\Response(response: 404, description: 'Candidature introuvable.'),
        ],
    )]
    public function __invoke(string $applicationSlug, string $applicationId, User $loggedInUser): JsonResponse
    {
        $application = $this->applicationRepository->find($applicationId);

        if ($application === null) {
            throw new NotFoundHttpException('Application not found.');
        }

        $ownerId = $application->getJob()->getOwner()?->getId();
        if ($ownerId === null || $ownerId !== $loggedInUser->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'You are not allowed to view the status history for this application.');
        }

        $canViewSensitiveData = $this->authorizationChecker->isGranted(RecruitPermissions::SENSITIVE_DATA_VIEW);
        $historyEntries = $this->applicationStatusHistoryRepository->findBy([
            'application' => $application,
        ], [
            'createdAt' => 'ASC',
        ]);

        return new JsonResponse(array_map(
            fn (ApplicationStatusHistory $history): array => [
                'id' => $history->getId(),
                'fromStatus' => $history->getFromStatus()->value,
                'toStatus' => $history->getToStatus()->value,
                'authorId' => $canViewSensitiveData ? $history->getAuthor()->getId() : null,
                'comment' => $canViewSensitiveData ? $history->getComment() : null,
                'createdAt' => $history->getCreatedAt()?->format(DATE_ATOM),
            ],
            $historyEntries,
        ));
    }
}
