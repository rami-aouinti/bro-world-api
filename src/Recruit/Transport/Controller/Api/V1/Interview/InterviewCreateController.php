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
readonly class InterviewCreateController
{
    public function __construct(private InterviewService $interviewService)
    {
    }

    #[Route(path: '/v1/recruit/private/applications/{applicationId}/interviews', methods: [Request::METHOD_POST])]
    public function __invoke(string $applicationId, Request $request, User $loggedInUser): JsonResponse
    {
        /** @var array<string,mixed> $payload */
        $payload = $request->toArray();
        $interview = $this->interviewService->create($applicationId, $payload, $loggedInUser);

        return new JsonResponse($this->normalize($interview), JsonResponse::HTTP_CREATED);
    }

    /** @return array<string,mixed> */
    private function normalize(\App\Recruit\Domain\Entity\Interview $interview): array
    {
        return [
            'id' => $interview->getId(),
            'applicationId' => $interview->getApplication()->getId(),
            'scheduledAt' => $interview->getScheduledAt()->format(DATE_ATOM),
            'durationMinutes' => $interview->getDurationMinutes(),
            'mode' => $interview->getMode()->value,
            'locationOrUrl' => $interview->getLocationOrUrl(),
            'interviewerIds' => $interview->getInterviewerIds(),
            'status' => $interview->getStatus()->value,
            'notes' => $interview->getNotes(),
        ];
    }
}
