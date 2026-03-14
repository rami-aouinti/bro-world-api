<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Offer;

use App\Recruit\Application\Service\OfferWorkflowService;
use App\Recruit\Infrastructure\Repository\ApplicationRepository;
use App\Recruit\Infrastructure\Repository\OfferRepository;
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

use function is_string;

#[AsController]
#[OA\Tag(name: 'Recruit Offer')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
readonly class OfferStatusUpdateController
{
    public function __construct(
        private OfferRepository $offerRepository,
        private ApplicationRepository $applicationRepository,
        private OfferWorkflowService $offerWorkflowService,
    ) {
    }

    #[Route(path: '/v1/recruit/applications/{applicationSlug}/private/offers/{offerId}/send', methods: [Request::METHOD_POST])]
    public function send(string $applicationSlug, string $offerId, Request $request, User $loggedInUser): JsonResponse
    {
        return $this->apply($applicationSlug, $offerId, $request, $loggedInUser, 'send');
    }

    #[Route(path: '/v1/recruit/applications/{applicationSlug}/private/offers/{offerId}/accept', methods: [Request::METHOD_POST])]
    public function accept(string $applicationSlug, string $offerId, Request $request, User $loggedInUser): JsonResponse
    {
        return $this->apply($applicationSlug, $offerId, $request, $loggedInUser, 'accept');
    }

    #[Route(path: '/v1/recruit/applications/{applicationSlug}/private/offers/{offerId}/decline', methods: [Request::METHOD_POST])]
    public function decline(string $applicationSlug, string $offerId, Request $request, User $loggedInUser): JsonResponse
    {
        return $this->apply($applicationSlug, $offerId, $request, $loggedInUser, 'decline');
    }

    #[Route(path: '/v1/recruit/applications/{applicationSlug}/private/offers/{offerId}/withdraw', methods: [Request::METHOD_POST])]
    public function withdraw(string $applicationSlug, string $offerId, Request $request, User $loggedInUser): JsonResponse
    {
        return $this->apply($applicationSlug, $offerId, $request, $loggedInUser, 'withdraw');
    }

    private function apply(string $applicationSlug, string $offerId, Request $request, User $loggedInUser, string $action): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        $offer = $this->offerRepository->find($offerId);

        if ($offer === null) {
            throw new NotFoundHttpException('Offer not found.');
        }

        if ($offer->getApplication()->getJob()->getOwner()?->getId() !== $loggedInUser->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'You are not allowed to manage this offer.');
        }

        /** @var array<string,mixed> $payload */
        $payload = $request->toArray();
        $comment = is_string($payload['comment'] ?? null) ? $payload['comment'] : null;

        match ($action) {
            'send' => $this->offerWorkflowService->send($offer, $loggedInUser, $comment),
            'accept' => $this->offerWorkflowService->accept($offer, $loggedInUser, $comment),
            'decline' => $this->offerWorkflowService->decline($offer, $loggedInUser, $comment),
            default => $this->offerWorkflowService->withdraw($offer, $loggedInUser, $comment),
        };

        $this->offerRepository->save($offer);
        $this->applicationRepository->save($offer->getApplication());

        return new JsonResponse([
            'id' => $offer->getId(),
            'status' => $offer->getStatus()->value,
            'applicationStatus' => $offer->getApplication()->getStatus()->value,
        ]);
    }
}
