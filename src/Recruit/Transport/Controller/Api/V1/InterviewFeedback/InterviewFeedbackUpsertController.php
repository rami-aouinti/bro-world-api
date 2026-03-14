<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\InterviewFeedback;

use App\Recruit\Application\Service\InterviewFeedbackService;
use App\Recruit\Domain\Entity\InterviewFeedback;
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
readonly class InterviewFeedbackUpsertController
{
    public function __construct(private InterviewFeedbackService $feedbackService)
    {
    }

    #[Route(path: '/v1/recruit/private/interviews/{interviewId}/feedback', methods: [Request::METHOD_POST, Request::METHOD_PUT])]
    public function __invoke(string $interviewId, Request $request, User $loggedInUser): JsonResponse
    {
        /** @var array<string,mixed> $payload */
        $payload = $request->toArray();
        $feedback = $this->feedbackService->upsert($interviewId, $payload, $loggedInUser);

        return new JsonResponse($this->normalize($feedback));
    }

    /** @return array<string,mixed> */
    private function normalize(InterviewFeedback $feedback): array
    {
        return [
            'id' => $feedback->getId(),
            'interviewId' => $feedback->getInterview()->getId(),
            'interviewerId' => $feedback->getInterviewer()->getId(),
            'skills' => $feedback->getSkillsScore(),
            'communication' => $feedback->getCommunicationScore(),
            'cultureFit' => $feedback->getCultureFitScore(),
            'recommendation' => $feedback->getRecommendation()->value,
            'comment' => $feedback->getComment(),
        ];
    }
}
