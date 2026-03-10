<?php

declare(strict_types=1);

namespace App\General\Application\Service;

use function json_encode;
use function md5;

class CacheKeyConventionService
{
    public function buildPublicPageKey(int $page, string $lang): string
    {
        return 'public/page/' . $page . '/' . $lang;
    }

    public function buildPublicBlogKey(string $scope): string
    {
        return 'public/blog/' . $scope;
    }

    public function buildPublicPlatformsListKey(): string
    {
        return 'public/platforms/list';
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function buildPublicApplicationsListKey(array $filters): string
    {
        return 'public/applications/list/' . $this->buildHash($filters);
    }

    public function buildPrivateProfileKey(string $username): string
    {
        return 'private/' . $username . '/profile';
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function buildPrivateConversationKey(string $username, array $filters): string
    {
        return 'private/' . $username . '/conversation/' . $this->buildHash($filters);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function buildPrivateNotificationKey(string $username, array $filters): string
    {
        return 'private/' . $username . '/notifications/' . $this->buildHash($filters);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function buildPrivateEventKey(string $username, array $filters): string
    {
        return 'private/' . $username . '/events/' . $this->buildHash($filters);
    }

    public function buildPrivateBlogKey(string $username, string $scope): string
    {
        return 'private/' . $username . '/blog/' . $scope;
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function buildApplicationListKey(?string $userId, int $page, int $limit, array $filters): string
    {
        return 'application_list_' . md5((string) json_encode([
            'userId' => $userId,
            'page' => $page,
            'limit' => $limit,
            'filters' => $filters,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function buildRecruitJobPublicListKey(string $applicationSlug, ?string $userId, int $page, int $limit, array $filters): string
    {
        return 'recruit_job_public_' . md5((string) json_encode([
            'applicationSlug' => $applicationSlug,
            'userId' => $userId,
            'page' => $page,
            'limit' => $limit,
            'filters' => $filters,
        ], JSON_THROW_ON_ERROR));
    }


    /**
     * @param array<string, mixed> $filters
     */
    public function buildShopProductListKey(int $page, int $limit, array $filters): string
    {
        return 'shop_product_list_' . md5((string) json_encode([
            'page' => $page,
            'limit' => $limit,
            'filters' => $filters,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function buildCrmTaskListKey(int $page, int $limit, array $filters): string
    {
        return 'crm_task_list_' . md5((string) json_encode([
            'page' => $page,
            'limit' => $limit,
            'filters' => $filters,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function buildSchoolExamListKey(int $page, int $limit, array $filters): string
    {
        return 'school_exam_list_' . md5((string) json_encode([
            'page' => $page,
            'limit' => $limit,
            'filters' => $filters,
        ], JSON_THROW_ON_ERROR));
    }

    public function applicationListTag(): string
    {
        return $this->tagPublicApplicationsList();
    }

    public function recruitJobListTag(string $applicationSlug): string
    {
        return 'cache:recruit:job:list:' . $applicationSlug;
    }

    public function tagPublicPage(): string
    {
        return 'cache:public:page';
    }

    public function tagPublicBlog(): string
    {
        return 'cache:public:blog';
    }

    public function tagPublicPlatformsList(): string
    {
        return 'cache:public:platforms:list';
    }

    public function tagPublicApplicationsList(): string
    {
        return 'cache:public:applications:list';
    }

    public function tagPrivateProfile(string $userId): string
    {
        return 'cache:private:' . $userId . ':profile';
    }

    public function tagPrivateConversation(string $userId): string
    {
        return 'cache:private:' . $userId . ':conversation';
    }

    public function tagPrivateNotification(string $userId): string
    {
        return 'cache:private:' . $userId . ':notifications';
    }

    public function tagPrivateEvents(string $userId): string
    {
        return 'cache:private:' . $userId . ':events';
    }

    public function tagPrivateBlog(string $userId): string
    {
        return 'cache:private:' . $userId . ':blog';
    }

    public function shopProductListTag(): string
    {
        return 'cache:shop:product:list';
    }

    public function crmTaskListTag(): string
    {
        return 'cache:crm:task:list';
    }

    public function schoolExamListTag(): string
    {
        return 'cache:school:exam:list';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function buildHash(array $payload): string
    {
        return md5((string) json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
