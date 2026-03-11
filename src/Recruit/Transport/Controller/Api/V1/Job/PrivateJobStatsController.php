<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Job;

use App\Recruit\Application\Service\JobStatsService;
use App\Recruit\Application\Service\RecruitResolverService;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Recruit Job')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
readonly class PrivateJobStatsController
{
    public function __construct(
        private RecruitResolverService $recruitResolverService,
        private JobStatsService $jobStatsService,
    ) {
    }

    #[Route(path: '/v1/recruit/{applicationSlug}/private/jobs/stats', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, User $loggedInUser): JsonResponse
    {
        $recruit = $this->recruitResolverService->resolveByApplicationSlug($applicationSlug);

        if ($recruit->getApplication()?->getUser()?->getId() !== $loggedInUser->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'You cannot access stats for this application.');
        }

        return new JsonResponse($this->jobStatsService->getStats($recruit));
    }
}
