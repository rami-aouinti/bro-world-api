<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Interview;

use App\Recruit\Application\Security\RecruitPermissions;
use App\Recruit\Application\Service\InterviewService;
use App\Recruit\Domain\Entity\Interview;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function array_map;

#[AsController]
#[OA\Tag(name: 'Recruit Interview')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[IsGranted(RecruitPermissions::INTERVIEW_VIEW)]
readonly class InterviewListController
{
    public function __construct(
        private InterviewService $interviewService,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    #[Route(path: '/v1/recruit/private/applications/{applicationId}/interviews', methods: [Request::METHOD_GET])]
    public function __invoke(string $applicationId, User $loggedInUser): JsonResponse
    {
        $items = $this->interviewService->listByApplication($applicationId, $loggedInUser);
        $canViewSensitiveData = $this->authorizationChecker->isGranted(RecruitPermissions::SENSITIVE_DATA_VIEW);

        return new JsonResponse(array_map(fn (Interview $interview): array => [
            'id' => $interview->getId(),
            'scheduledAt' => $interview->getScheduledAt()->format(DATE_ATOM),
            'durationMinutes' => $interview->getDurationMinutes(),
            'mode' => $interview->getMode()->value,
            'locationOrUrl' => $interview->getLocationOrUrl(),
            'interviewerIds' => $interview->getInterviewerIds(),
            'status' => $interview->getStatus()->value,
            'notes' => $canViewSensitiveData ? $interview->getNotes() : null,
        ], $items));
    }
}
