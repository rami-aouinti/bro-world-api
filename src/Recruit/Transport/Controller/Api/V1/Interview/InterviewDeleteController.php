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
readonly class InterviewDeleteController
{
    public function __construct(private InterviewService $interviewService)
    {
    }

    #[Route(path: '/v1/recruit/private/interviews/{interviewId}', methods: [Request::METHOD_DELETE])]
    public function __invoke(string $interviewId, User $loggedInUser): JsonResponse
    {
        $this->interviewService->delete($interviewId, $loggedInUser);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
