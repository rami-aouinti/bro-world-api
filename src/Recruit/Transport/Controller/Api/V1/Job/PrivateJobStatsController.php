<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Job;

use App\Recruit\Application\Service\JobStatsService;
use App\Recruit\Domain\Entity\Recruit;
use App\Recruit\Domain\Repository\Interfaces\RecruitRepositoryInterface;
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
class PrivateJobStatsController
{
    public function __construct(
        private readonly RecruitRepositoryInterface $recruitRepository,
        private readonly JobStatsService $jobStatsService,
    ) {
    }

    #[Route(path: '/v1/recruit/private/{applicationSlug}/jobs/stats', methods: [Request::METHOD_GET])]
    public function __invoke(string $applicationSlug, User $loggedInUser): JsonResponse
    {
        $recruit = $this->resolveRecruitByApplicationSlug($applicationSlug);

        if ($recruit->getApplication()?->getUser()?->getId() !== $loggedInUser->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'You cannot access stats for this application.');
        }

        return new JsonResponse($this->jobStatsService->getStats($recruit));
    }

    private function resolveRecruitByApplicationSlug(string $applicationSlug): Recruit
    {
        $recruit = $this->recruitRepository->findOneByApplicationSlug($applicationSlug);

        if (!$recruit instanceof Recruit) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Unknown "applicationSlug".');
        }

        return $recruit;
    }
}
