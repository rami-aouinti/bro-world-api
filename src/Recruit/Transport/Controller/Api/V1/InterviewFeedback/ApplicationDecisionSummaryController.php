<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\InterviewFeedback;

use App\Recruit\Application\Service\InterviewFeedbackService;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Recruit Interview Feedback')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
readonly class ApplicationDecisionSummaryController
{
    public function __construct(
        private InterviewFeedbackService $feedbackService
    ) {
    }

    #[Route(path: '/v1/recruit/private/applications/{applicationId}/decision-summary', methods: [Request::METHOD_GET])]
    public function __invoke(string $applicationId, User $loggedInUser): JsonResponse
    {
        return new JsonResponse($this->feedbackService->getApplicationSummary($applicationId, $loggedInUser));
    }
}
