<?php

declare(strict_types=1);

namespace App\General\Application\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class CacheInvalidationService
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly CacheKeyConventionService $cacheKeyConventionService,
    ) {
    }

    public function invalidateApplicationListCaches(?string $applicationSlug = null): void
    {
        if ($this->cache instanceof TagAwareCacheInterface) {
            $tags = [$this->cacheKeyConventionService->applicationListTag()];
            if ($applicationSlug !== null && $applicationSlug !== '') {
                $tags[] = $this->cacheKeyConventionService->recruitJobListTag($applicationSlug);
            }

            $this->cache->invalidateTags($tags);
        }

        $this->cache->delete($this->cacheKeyConventionService->buildApplicationListKey(null, 1, 20, [
            'title' => '',
            'description' => '',
            'platformName' => '',
            'platformKey' => '',
        ]));

        if ($applicationSlug !== null && $applicationSlug !== '') {
            $this->invalidateRecruitJobListCaches($applicationSlug);
        }
    }

    public function invalidateShopProductListCaches(): void
    {
        if ($this->cache instanceof TagAwareCacheInterface) {
            $this->cache->invalidateTags([$this->cacheKeyConventionService->shopProductListTag()]);
        }

        $this->cache->delete($this->cacheKeyConventionService->buildShopProductListKey(1, 20, [
            'q' => '',
            'name' => '',
            'category' => '',
        ]));
    }

    public function invalidateCrmTaskListCaches(): void
    {
        if ($this->cache instanceof TagAwareCacheInterface) {
            $this->cache->invalidateTags([$this->cacheKeyConventionService->crmTaskListTag()]);
        }

        $this->cache->delete($this->cacheKeyConventionService->buildCrmTaskListKey(1, 20, [
            'q' => '',
            'title' => '',
        ]));
    }

    public function invalidateSchoolExamListCaches(): void
    {
        if ($this->cache instanceof TagAwareCacheInterface) {
            $this->cache->invalidateTags([$this->cacheKeyConventionService->schoolExamListTag()]);
        }

        $this->cache->delete($this->cacheKeyConventionService->buildSchoolExamListKey(1, 20, [
            'q' => '',
            'title' => '',
        ]));
    }

    public function invalidateRecruitJobListCaches(string $applicationSlug): void
    {
        if ($this->cache instanceof TagAwareCacheInterface) {
            $this->cache->invalidateTags([$this->cacheKeyConventionService->recruitJobListTag($applicationSlug)]);
        }

        $this->cache->delete($this->cacheKeyConventionService->buildRecruitJobPublicListKey($applicationSlug, null, 1, 20, [
            'company' => '',
            'salaryMin' => 0,
            'salaryMax' => 0,
            'contractType' => '',
            'workMode' => '',
            'schedule' => '',
            'postedAtLabel' => '',
            'location' => '',
            'q' => '',
        ]));
    }

    /**
     * @param list<string|null> $userIds
     */
    public function invalidateBlogCaches(?string $applicationSlug, array $userIds = []): void
    {
        if (!$this->cache instanceof TagAwareCacheInterface) {
            return;
        }

        $tags = [
            $this->cacheKeyConventionService->tagPublicBlog(),
            $this->cacheKeyConventionService->tagPublicBlogByApplication($applicationSlug),
        ];

        foreach (array_values(array_unique($userIds)) as $userId) {
            if ($userId === null || $userId === '') {
                continue;
            }

            $tags[] = $this->cacheKeyConventionService->tagPrivateBlog($userId);
        }

        $this->cache->invalidateTags($tags);
    }

    public function invalidatePublicPageCaches(): void
    {
        if (!$this->cache instanceof TagAwareCacheInterface) {
            return;
        }

        $this->cache->invalidateTags([$this->cacheKeyConventionService->tagPublicPage()]);
    }

    public function invalidatePublicPlatformListCaches(): void
    {
        if (!$this->cache instanceof TagAwareCacheInterface) {
            return;
        }

        $this->cache->invalidateTags([$this->cacheKeyConventionService->tagPublicPlatformsList()]);
    }

    public function invalidateConversationCaches(?string $chatId, ?string $userId): void
    {
        if (!$this->cache instanceof TagAwareCacheInterface) {
            return;
        }

        $tags = [];
        if ($chatId !== null && $chatId !== '') {
            $tags[] = $this->cacheKeyConventionService->tagPublicConversationByChat($chatId);
        }
        if ($userId !== null && $userId !== '') {
            $tags[] = $this->cacheKeyConventionService->tagPrivateConversation($userId);
        }

        if ($tags !== []) {
            $this->cache->invalidateTags($tags);
        }
    }

    public function invalidateEventCaches(?string $applicationSlug, ?string $userId): void
    {
        if (!$this->cache instanceof TagAwareCacheInterface) {
            return;
        }

        $tags = [];
        if ($applicationSlug !== null && $applicationSlug !== '') {
            $tags[] = $this->cacheKeyConventionService->tagPublicEventsByApplication($applicationSlug);
        }
        if ($userId !== null && $userId !== '') {
            $tags[] = $this->cacheKeyConventionService->tagPrivateEvents($userId);
        }

        if ($tags !== []) {
            $this->cache->invalidateTags($tags);
        }
    }

    public function invalidateUserStoryCaches(): void
    {
        if (!$this->cache instanceof TagAwareCacheInterface) {
            return;
        }

        $this->cache->invalidateTags([$this->cacheKeyConventionService->tagPrivateStoryList()]);
    }
}
