<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Application;

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
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Recruit Application')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
readonly class ApplicationStatusHistoryListController
{
    public function __construct(
        private ApplicationRepository $applicationRepository,
        private ApplicationStatusHistoryRepository $applicationStatusHistoryRepository,
    ) {
    }

    #[Route(path: '/v1/recruit/applications/{applicationSlug}/private/applications/{applicationId}/status-history', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Get(summary: 'Retourne l\'historique des transitions de statut d\'une candidature.')]
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

        $historyEntries = $this->applicationStatusHistoryRepository->findBy([
            'application' => $application,
        ], [
            'createdAt' => 'ASC',
        ]);

        return new JsonResponse(array_map(
            static fn (ApplicationStatusHistory $history): array => [
                'id' => $history->getId(),
                'fromStatus' => $history->getFromStatus()->value,
                'toStatus' => $history->getToStatus()->value,
                'authorId' => $history->getAuthor()->getId(),
                'comment' => $history->getComment(),
                'createdAt' => $history->getCreatedAt()?->format(DATE_ATOM),
            ],
            $historyEntries,
        ));
    }
}
