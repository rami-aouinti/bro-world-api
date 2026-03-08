<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Application;

use App\Recruit\Application\Service\ApplicationDiscussionBootstrapService;
use App\Recruit\Domain\Enum\ApplicationStatus;
use App\Recruit\Infrastructure\Repository\ApplicationRepository;
use App\User\Domain\Entity\User;
use DomainException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function array_key_exists;
use function in_array;
use function is_string;
use function strtoupper;

#[AsController]
#[OA\Tag(name: 'Recruit Application')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
class ApplicationStatusUpdateController
{
    public function __construct(
        private readonly ApplicationRepository $applicationRepository,
        private readonly ApplicationDiscussionBootstrapService $applicationDiscussionBootstrapService,
    ) {
    }

    #[Route(path: '/v1/recruit/private/applications/{applicationId}/status', methods: [Request::METHOD_PATCH, Request::METHOD_PUT])]
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
    public function __invoke(string $applicationId, Request $request, User $loggedInUser): JsonResponse
    {
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
        $status = $payload['status'] ?? null;

        if (!is_string($status)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "status" must be provided as a string.');
        }

        $newStatus = ApplicationStatus::tryFrom(strtoupper($status));
        if ($newStatus === null) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "status" must be one of: WAITING, IN_PROGRESS, DISCUSSION, INVITE_TO_INTERVIEW, INTERVIEW, ACCEPTED, REJECTED.');
        }

        $currentStatus = $application->getStatus();
        if ($newStatus !== $currentStatus && !$this->isAllowedTransition($currentStatus, $newStatus)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Status transition is not allowed for this application.');
        }

        if ($newStatus === ApplicationStatus::DISCUSSION && $currentStatus !== ApplicationStatus::DISCUSSION) {
            try {
                $this->applicationDiscussionBootstrapService->bootstrap($application);
            } catch (DomainException $exception) {
                throw new HttpException(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, $exception->getMessage(), $exception);
            }
        }

        $application->setStatus($newStatus);
        $this->applicationRepository->save($application);

        return new JsonResponse([
            'id' => $application->getId(),
            'status' => $application->getStatusValue(),
        ]);
    }

    private function isAllowedTransition(ApplicationStatus $from, ApplicationStatus $to): bool
    {
        $allowedTransitions = [
            ApplicationStatus::WAITING->value => [ApplicationStatus::IN_PROGRESS->value, ApplicationStatus::REJECTED->value],
            ApplicationStatus::IN_PROGRESS->value => [ApplicationStatus::DISCUSSION->value, ApplicationStatus::INVITE_TO_INTERVIEW->value, ApplicationStatus::REJECTED->value],
            ApplicationStatus::DISCUSSION->value => [ApplicationStatus::INVITE_TO_INTERVIEW->value, ApplicationStatus::REJECTED->value],
            ApplicationStatus::INVITE_TO_INTERVIEW->value => [ApplicationStatus::INTERVIEW->value, ApplicationStatus::REJECTED->value],
            ApplicationStatus::INTERVIEW->value => [ApplicationStatus::ACCEPTED->value, ApplicationStatus::REJECTED->value],
            ApplicationStatus::ACCEPTED->value => [],
            ApplicationStatus::REJECTED->value => [],
        ];

        if (!array_key_exists($from->value, $allowedTransitions)) {
            return false;
        }

        return in_array($to->value, $allowedTransitions[$from->value], true);
    }
}
