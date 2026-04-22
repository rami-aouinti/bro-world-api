<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\General\Application\Service\ApplicationScopeResolver;
use App\Recruit\Domain\Entity\Recruit;
use App\Recruit\Domain\Repository\Interfaces\RecruitRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

readonly class RecruitResolverService
{
    public function __construct(
        private RecruitRepositoryInterface $recruitRepository,
        private ApplicationScopeResolver $applicationScopeResolver,
    ) {
    }

    public function resolveFromRequest(Request $request): Recruit
    {
        return $this->resolveByApplicationSlug($this->applicationScopeResolver->resolveApplicationSlug($request));
    }

    public function resolveByApplicationSlug(string $applicationSlug): Recruit
    {
        $recruit = $this->recruitRepository->findOneByApplicationSlug($applicationSlug);

        if (!$recruit instanceof Recruit) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, ApplicationScopeResolver::UNKNOWN_APPLICATION_SLUG_MESSAGE);
        }

        return $recruit;
    }
}
