<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\Recruit\Domain\Entity\Job;
use App\Recruit\Domain\Entity\Recruit;
use App\Recruit\Infrastructure\Repository\JobRepository;
use App\User\Domain\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

readonly class ApplicationJobAccessService
{
    public function __construct(
        private RecruitResolverService $recruitResolverService,
        private JobRepository $jobRepository,
    ) {
    }

    public function resolveOwnedRecruitByApplicationSlug(string $applicationSlug, User $loggedInUser, string $forbiddenMessage): Recruit
    {
        $recruit = $this->recruitResolverService->resolveByApplicationSlug($applicationSlug);

        if ($recruit->getApplication()?->getUser()?->getId() !== $loggedInUser->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, $forbiddenMessage);
        }

        return $recruit;
    }

    public function resolveJobForRecruit(string $jobId, Recruit $recruit): Job
    {
        $job = $this->jobRepository->find($jobId);
        if (!$job instanceof Job) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Job not found.');
        }

        if ($job->getRecruit()?->getId() !== $recruit->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'This job does not belong to the given application.');
        }

        return $job;
    }
}
