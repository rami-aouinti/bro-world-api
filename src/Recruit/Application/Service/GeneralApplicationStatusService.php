<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\Recruit\Infrastructure\Repository\ApplicationRepository;
use App\User\Domain\Entity\User;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function is_string;

readonly class GeneralApplicationStatusService
{
    public function __construct(
        private ApplicationRepository $applicationRepository,
        private ApplicationStatusTransitionService $applicationStatusTransitionService,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{id: string, status: string}
     *
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function updateStatus(string $applicationId, array $payload, User $loggedInUser): array
    {
        $application = $this->applicationRepository->find($applicationId);

        if ($application === null) {
            throw new NotFoundHttpException('Application not found.');
        }

        $ownerId = $application->getJob()->getOwner()?->getId();
        if ($ownerId === null || $ownerId !== $loggedInUser->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'You are not allowed to update the status for this application.');
        }

        $comment = is_string($payload['comment'] ?? null) ? $payload['comment'] : null;
        $this->applicationStatusTransitionService->applyStatusTransition($application, $payload['status'] ?? null, $loggedInUser, $comment);

        $this->applicationRepository->save($application);

        return [
            'id' => $application->getId(),
            'status' => $application->getStatusValue(),
        ];
    }
}
