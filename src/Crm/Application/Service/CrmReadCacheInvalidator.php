<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

use App\General\Application\Service\CacheKeyConventionService;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

readonly class CrmReadCacheInvalidator
{
    public function __construct(
        private CacheInterface $cache,
        private CacheKeyConventionService $cacheKeyConventionService,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function invalidateBilling(string $applicationSlug, string $billingId): void
    {
        $this->invalidateTags([
            $this->cacheKeyConventionService->crmBillingListTag($applicationSlug),
            $this->cacheKeyConventionService->crmBillingDetailTag($applicationSlug, $billingId),
        ]);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function invalidateCompany(string $applicationSlug, ?string $companyId = null): void
    {
        $tags = [$this->cacheKeyConventionService->crmCompanyListByApplicationTag($applicationSlug)];
        if ($companyId !== null) {
            $tags[] = $this->cacheKeyConventionService->crmCompanyDetailTag($applicationSlug, $companyId);
        }

        $this->invalidateTags($tags);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function invalidateContact(string $applicationSlug, string $contactId): void
    {
        $this->invalidateTags([
            $this->cacheKeyConventionService->crmContactListTag($applicationSlug),
            $this->cacheKeyConventionService->crmContactDetailTag($applicationSlug, $contactId),
        ]);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function invalidateProject(string $applicationSlug, string $projectId): void
    {
        $this->invalidateTags([
            $this->cacheKeyConventionService->crmProjectListTag($applicationSlug),
            $this->cacheKeyConventionService->crmProjectDetailTag($applicationSlug, $projectId),
        ]);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function invalidateEmployee(string $applicationSlug, string $employeeId): void
    {
        $this->invalidateTags([
            $this->cacheKeyConventionService->crmEmployeeListTag($applicationSlug),
            $this->cacheKeyConventionService->crmEmployeeDetailTag($applicationSlug, $employeeId),
        ]);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function invalidateTaskRequest(string $applicationSlug, string $taskRequestId): void
    {
        $this->invalidateTags([
            $this->cacheKeyConventionService->crmTaskRequestListTag($applicationSlug),
            $this->cacheKeyConventionService->crmTaskRequestDetailTag($applicationSlug, $taskRequestId),
        ]);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function invalidateTask(string $applicationSlug, ?string $taskId = null): void
    {
        $tags = [$this->cacheKeyConventionService->crmTaskListTag($applicationSlug)];
        if ($taskId !== null) {
            $tags[] = $this->cacheKeyConventionService->crmTaskDetailTag($applicationSlug, $taskId);
        }

        $this->invalidateTags($tags);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function invalidateSprint(string $applicationSlug, ?string $sprintId = null): void
    {
        $tags = [$this->cacheKeyConventionService->crmSprintListTag($applicationSlug)];
        if ($sprintId !== null) {
            $tags[] = $this->cacheKeyConventionService->crmSprintDetailTag($applicationSlug, $sprintId);
        }

        $this->invalidateTags($tags);
    }

    /**
     * @param list<string> $tags
     * @throws InvalidArgumentException
     */
    private function invalidateTags(array $tags): void
    {
        if (!$this->cache instanceof TagAwareCacheInterface) {
            return;
        }

        $this->cache->invalidateTags($tags);
    }
}
