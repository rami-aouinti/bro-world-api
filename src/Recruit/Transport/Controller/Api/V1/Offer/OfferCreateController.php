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

#[AsController]
#[OA\Tag(name: 'Recruit Offer')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
readonly class OfferCreateController
{
    public function __construct(
        private ApplicationRepository $applicationRepository,
        private OfferRepository $offerRepository,
        private OfferWorkflowService $offerWorkflowService,
    ) {
    }

    #[Route(path: '/v1/recruit/applications/{applicationSlug}/private/applications/{applicationId}/offers', methods: [Request::METHOD_POST])]
    public function __invoke(string $applicationSlug, string $applicationId, Request $request, User $loggedInUser): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        $application = $this->applicationRepository->find($applicationId);

        if ($application === null) {
            throw new NotFoundHttpException('Application not found.');
        }

        if ($application->getJob()->getOwner()?->getId() !== $loggedInUser->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'You are not allowed to manage offers for this application.');
        }

        /** @var array<string,mixed> $payload */
        $payload = $request->toArray();
        $offer = $this->offerWorkflowService->create($application, $payload, $loggedInUser);
        $this->offerRepository->save($offer);
        $this->applicationRepository->save($application);

        return new JsonResponse($this->normalize($offer), JsonResponse::HTTP_CREATED);
    }

    /**
     * @return array<string,mixed>
     */
    private function normalize(\App\Recruit\Domain\Entity\Offer $offer): array
    {
        return [
            'id' => $offer->getId(),
            'applicationId' => $offer->getApplication()->getId(),
            'salaryProposed' => $offer->getSalaryProposed(),
            'startDate' => $offer->getStartDate()->format('Y-m-d'),
            'contractType' => $offer->getContractType()->value,
            'status' => $offer->getStatus()->value,
        ];
    }
}
