<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Interview;

use App\Recruit\Application\Security\RecruitPermissions;
use App\Recruit\Application\Service\InterviewService;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Recruit Interview')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[IsGranted(RecruitPermissions::INTERVIEW_MANAGE)]
readonly class InterviewPatchController
{
    public function __construct(private InterviewService $interviewService)
    {
    }

    #[Route(path: '/v1/recruit/private/interviews/{interviewId}', methods: [Request::METHOD_PATCH])]
    public function __invoke(string $interviewId, Request $request, User $loggedInUser): JsonResponse
    {
        /** @var array<string,mixed> $payload */
        $payload = $request->toArray();
        $interview = $this->interviewService->update($interviewId, $payload, $loggedInUser);

        return new JsonResponse([
            'id' => $interview->getId(),
            'status' => $interview->getStatus()->value,
            'scheduledAt' => $interview->getScheduledAt()->format(DATE_ATOM),
            'durationMinutes' => $interview->getDurationMinutes(),
        ]);
    }
}
