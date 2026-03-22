<?php

declare(strict_types=1);

namespace App\General\Application\Service;

use JsonException;

use function json_encode;
use function md5;
use function preg_replace;
use function strtolower;
use function trim;

class CacheKeyConventionService
{
    public function buildPublicPageKey(string $page, string $lang): string
    {
        return 'public_page_' . $this->sanitizeSegment($page) . '_' . $this->sanitizeSegment($lang);
    }

    public function buildPublicBlogKey(string $scope): string
    {
        return 'public_blog_' . $this->sanitizeSegment($scope);
    }

    public function buildPublicPlatformsListKey(): string
    {
        return 'public_platform_list';
    }

    /**
     * @param array<string, mixed> $filters
     * @throws JsonException
     */
    public function buildPublicUserListKey(array $filters): string
    {
        return 'public_users_list_' . $this->buildHash($filters);
    }

    /**
     * @param array<string, mixed> $filters
     * @throws JsonException
     */
    public function buildPublicApplicationsListKey(array $filters): string
    {
        return 'public_applications_list_' . $this->buildHash($filters);
    }

    public function buildPrivateProfileKey(string $username): string
    {
        return 'private_' . $this->sanitizeSegment($username) . '_profile';
    }

    /**
     * @param array<string, mixed> $filters
     * @throws JsonException
     */
    public function buildPrivateConversationKey(string $username, array $filters): string
    {
        return 'private_' . $this->sanitizeSegment($username) . '_conversation_' . $this->buildHash($filters);
    }

    /**
     * @param array<string, mixed> $filters
     * @throws JsonException
     */
    public function buildPrivateNotificationKey(string $username, array $filters): string
    {
        return 'private_' . $this->sanitizeSegment($username) . '_notifications_' . $this->buildHash($filters);
    }

    /**
     * @param array<string, mixed> $filters
     * @throws JsonException
     */
    public function buildPrivateEventKey(string $username, array $filters): string
    {
        return 'private_' . $this->sanitizeSegment($username) . '_events_' . $this->buildHash($filters);
    }

    public function buildPrivateBlogKey(string $username, string $scope): string
    {
        return 'private_' . $this->sanitizeSegment($username) . '_blog_' . $this->sanitizeSegment($scope);
    }

    public function buildPrivateStoryListKey(string $userId, int $limit): string
    {
        return 'private_' . $this->sanitizeSegment($userId) . '_story_list_' . $limit;
    }

    /**
     * @param array<string, mixed> $filters
     * @throws JsonException
     */
    public function buildApplicationListKey(?string $userId, int $page, int $limit, array $filters): string
    {
        return 'application_list_' . md5((string)json_encode([
            'userId' => $userId,
            'page' => $page,
            'limit' => $limit,
            'filters' => $filters,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed> $filters
     * @throws JsonException
     */
    public function buildRecruitJobPublicListKey(string $applicationSlug, ?string $userId, int $page, int $limit, array $filters): string
    {
        return 'recruit_job_public_' . md5((string)json_encode([
            'applicationSlug' => $applicationSlug,
            'userId' => $userId,
            'page' => $page,
            'limit' => $limit,
            'filters' => $filters,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed> $filters
     * @throws JsonException
     */
    public function buildShopProductListKey(int $page, int $limit, array $filters): string
    {
        return 'shop_product_list_' . md5((string)json_encode([
            'page' => $page,
            'limit' => $limit,
            'filters' => $filters,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed> $filters
     * @throws JsonException
     */
    public function buildShopProductApplicationListKey(string $applicationSlug, int $page, int $limit, array $filters): string
    {
        return 'shop_product_application_list_' . md5((string)json_encode([
            'applicationSlug' => $applicationSlug,
            'page' => $page,
            'limit' => $limit,
            'filters' => $filters,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed> $filters
     * @throws JsonException
     */
    public function buildCrmTaskListKey(int $page, int $limit, array $filters): string
    {
        return 'crm_task_list_' . md5((string)json_encode([
            'page' => $page,
            'limit' => $limit,
            'filters' => $filters,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed> $filters
     * @throws JsonException
     */
    public function buildCrmCompanyApplicationListKey(string $applicationSlug, int $page, int $limit, array $filters): string
    {
        return 'crm_company_application_list_' . md5((string)json_encode([
            'applicationSlug' => $applicationSlug,
            'page' => $page,
            'limit' => $limit,
            'filters' => $filters,
        ], JSON_THROW_ON_ERROR));
    }

    public function buildCrmCompanyDetailKey(string $applicationSlug, string $companyId): string
    {
        return 'crm_company_detail_' . $this->sanitizeSegment($applicationSlug) . '_' . $this->sanitizeSegment($companyId);
    }

    /**
     * @param array<string, mixed> $filters
     * @throws JsonException
     */
    public function buildCrmBillingListKey(string $applicationSlug, int $page, int $limit, array $filters): string
    {
        return 'crm_billing_list_' . md5((string)json_encode([
            'applicationSlug' => $applicationSlug,
            'page' => $page,
            'limit' => $limit,
            'filters' => $filters,
        ], JSON_THROW_ON_ERROR));
    }

    public function buildCrmBillingDetailKey(string $applicationSlug, string $billingId): string
    {
        return 'crm_billing_detail_' . $this->sanitizeSegment($applicationSlug) . '_' . $this->sanitizeSegment($billingId);
    }

    /**
     * @param array<string, mixed> $filters
     * @throws JsonException
     */
    public function buildCrmContactListKey(string $applicationSlug, int $page, int $limit, array $filters): string
    {
        return 'crm_contact_list_' . md5((string)json_encode([
            'applicationSlug' => $applicationSlug,
            'page' => $page,
            'limit' => $limit,
            'filters' => $filters,
        ], JSON_THROW_ON_ERROR));
    }

    public function buildCrmContactDetailKey(string $applicationSlug, string $contactId): string
    {
        return 'crm_contact_detail_' . $this->sanitizeSegment($applicationSlug) . '_' . $this->sanitizeSegment($contactId);
    }

    /**
     * @param array<string, mixed> $filters
     * @throws JsonException
     */
    public function buildCrmProjectListKey(string $applicationSlug, int $page, int $limit, array $filters): string
    {
        return 'crm_project_list_' . md5((string)json_encode([
            'applicationSlug' => $applicationSlug,
            'page' => $page,
            'limit' => $limit,
            'filters' => $filters,
        ], JSON_THROW_ON_ERROR));
    }

    public function buildCrmProjectDetailKey(string $applicationSlug, string $projectId): string
    {
        return 'crm_project_detail_' . $this->sanitizeSegment($applicationSlug) . '_' . $this->sanitizeSegment($projectId);
    }

    /**
     * @param array<string, mixed> $filters
     * @throws JsonException
     */
    public function buildCrmRepositoryListKey(string $applicationSlug, int $page, int $limit, array $filters): string
    {
        return 'crm_repository_list_' . md5((string)json_encode([
            'applicationSlug' => $applicationSlug,
            'page' => $page,
            'limit' => $limit,
            'filters' => $filters,
        ], JSON_THROW_ON_ERROR));
    }

    public function buildCrmRepositoryDetailKey(string $applicationSlug, string $repositoryId): string
    {
        return 'crm_repository_detail_' . $this->sanitizeSegment($applicationSlug) . '_' . $this->sanitizeSegment($repositoryId);
    }

    /**
     * @param array<string, mixed> $filters
     * @throws JsonException
     */
    public function buildCrmIssueListKey(string $applicationSlug, int $page, int $limit, array $filters): string
    {
        return 'crm_issue_list_' . md5((string)json_encode([
            'applicationSlug' => $applicationSlug,
            'page' => $page,
            'limit' => $limit,
            'filters' => $filters,
        ], JSON_THROW_ON_ERROR));
    }

    public function buildCrmIssueDetailKey(string $applicationSlug, string $issueId): string
    {
        return 'crm_issue_detail_' . $this->sanitizeSegment($applicationSlug) . '_' . $this->sanitizeSegment($issueId);
    }

    /**
     * @param array<string, mixed> $filters
     * @throws JsonException
     */
    public function buildCrmEmployeeListKey(string $applicationSlug, int $page, int $limit, array $filters): string
    {
        return 'crm_employee_list_' . md5((string)json_encode([
            'applicationSlug' => $applicationSlug,
            'page' => $page,
            'limit' => $limit,
            'filters' => $filters,
        ], JSON_THROW_ON_ERROR));
    }

    public function buildCrmEmployeeDetailKey(string $applicationSlug, string $employeeId): string
    {
        return 'crm_employee_detail_' . $this->sanitizeSegment($applicationSlug) . '_' . $this->sanitizeSegment($employeeId);
    }

    /**
     * @param array<string, mixed> $filters
     * @throws JsonException
     */
    public function buildCrmTaskRequestListKey(string $applicationSlug, int $page, int $limit, array $filters): string
    {
        return 'crm_task_request_list_' . md5((string)json_encode([
            'applicationSlug' => $applicationSlug,
            'page' => $page,
            'limit' => $limit,
            'filters' => $filters,
        ], JSON_THROW_ON_ERROR));
    }

    public function buildCrmTaskRequestDetailKey(string $applicationSlug, string $taskRequestId): string
    {
        return 'crm_task_request_detail_' . $this->sanitizeSegment($applicationSlug) . '_' . $this->sanitizeSegment($taskRequestId);
    }

    /**
     * @param array<string, mixed> $filters
     * @throws JsonException
     */
    public function buildSchoolExamListKey(string $applicationSlug, int $page, int $limit, array $filters): string
    {
        return 'school_exam_list_' . md5((string)json_encode([
            'applicationSlug' => $applicationSlug,
            'page' => $page,
            'limit' => $limit,
            'filters' => $filters,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed> $filters
     * @throws JsonException
     */
    public function buildSchoolClassApplicationListKey(string $applicationSlug, int $page, int $limit, array $filters): string
    {
        return 'school_class_application_list_' . md5((string)json_encode([
            'applicationSlug' => $applicationSlug,
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
        return 'cache_recruit_job_list_' . $this->sanitizeSegment($applicationSlug);
    }

    public function tagPublicPage(): string
    {
        return 'cache_page_public';
    }

    public function tagPublicBlog(): string
    {
        return 'cache_public_blog';
    }

    public function tagPublicBlogByApplication(?string $applicationSlug): string
    {
        return 'cache_public_blog_' . $this->sanitizeSegment($applicationSlug !== null && $applicationSlug !== '' ? $applicationSlug : 'general');
    }

    public function tagPublicPlatformsList(): string
    {
        return 'cache_platform_public_list';
    }

    public function publicUserListTag(): string
    {
        return 'cache_public_users_list';
    }

    public function tagPublicApplicationsList(): string
    {
        return 'cache_public_applications_list';
    }

    public function tagPrivateProfile(string $userId): string
    {
        return 'cache_private_' . $this->sanitizeSegment($userId) . '_profile';
    }

    public function tagPrivateConversation(string $userId): string
    {
        return 'cache_private_' . $this->sanitizeSegment($userId) . '_conversation';
    }

    public function tagPublicConversationByChat(string $chatId): string
    {
        return 'cache_public_conversation_' . $this->sanitizeSegment($chatId);
    }

    public function tagPrivateNotification(string $userId): string
    {
        return 'cache_private_' . $this->sanitizeSegment($userId) . '_notifications';
    }

    public function tagPrivateEvents(string $userId): string
    {
        return 'cache_private_' . $this->sanitizeSegment($userId) . '_events';
    }

    public function tagPublicEventsByApplication(string $applicationSlug): string
    {
        return 'cache_public_events_' . $this->sanitizeSegment($applicationSlug);
    }

    public function tagPrivateBlog(string $userId): string
    {
        return 'cache_private_' . $this->sanitizeSegment($userId) . '_blog';
    }

    public function tagPrivateStoryList(): string
    {
        return 'cache_private_story_list';
    }

    public function shopProductListTag(): string
    {
        return 'cache_shop_product_list';
    }

    public function shopProductListByApplicationTag(string $applicationSlug): string
    {
        return 'cache_shop_product_list_' . $this->sanitizeSegment($applicationSlug);
    }

    public function crmTaskListTag(string $applicationSlug): string
    {
        return 'cache_crm_task_list_' . $this->sanitizeSegment($applicationSlug);
    }

    public function crmCompanyListByApplicationTag(string $applicationSlug): string
    {
        return 'cache_crm_company_list_' . $this->sanitizeSegment($applicationSlug);
    }

    public function crmCompanyDetailTag(string $applicationSlug, string $companyId): string
    {
        return 'cache_crm_company_detail_' . $this->sanitizeSegment($applicationSlug) . '_' . $this->sanitizeSegment($companyId);
    }

    public function crmBillingListTag(string $applicationSlug): string
    {
        return 'cache_crm_billing_list_' . $this->sanitizeSegment($applicationSlug);
    }

    public function crmBillingDetailTag(string $applicationSlug, string $billingId): string
    {
        return 'cache_crm_billing_detail_' . $this->sanitizeSegment($applicationSlug) . '_' . $this->sanitizeSegment($billingId);
    }

    public function crmContactListTag(string $applicationSlug): string
    {
        return 'cache_crm_contact_list_' . $this->sanitizeSegment($applicationSlug);
    }

    public function crmContactDetailTag(string $applicationSlug, string $contactId): string
    {
        return 'cache_crm_contact_detail_' . $this->sanitizeSegment($applicationSlug) . '_' . $this->sanitizeSegment($contactId);
    }

    public function crmProjectListTag(string $applicationSlug): string
    {
        return 'cache_crm_project_list_' . $this->sanitizeSegment($applicationSlug);
    }

    public function crmProjectDetailTag(string $applicationSlug, string $projectId): string
    {
        return 'cache_crm_project_detail_' . $this->sanitizeSegment($applicationSlug) . '_' . $this->sanitizeSegment($projectId);
    }

    public function crmEmployeeListTag(string $applicationSlug): string
    {
        return 'cache_crm_employee_list_' . $this->sanitizeSegment($applicationSlug);
    }

    public function crmEmployeeDetailTag(string $applicationSlug, string $employeeId): string
    {
        return 'cache_crm_employee_detail_' . $this->sanitizeSegment($applicationSlug) . '_' . $this->sanitizeSegment($employeeId);
    }

    public function crmTaskRequestListTag(string $applicationSlug): string
    {
        return 'cache_crm_task_request_list_' . $this->sanitizeSegment($applicationSlug);
    }

    public function crmTaskRequestDetailTag(string $applicationSlug, string $taskRequestId): string
    {
        return 'cache_crm_task_request_detail_' . $this->sanitizeSegment($applicationSlug) . '_' . $this->sanitizeSegment($taskRequestId);
    }

    public function crmRepositoryListTag(string $applicationSlug): string
    {
        return 'cache_crm_repository_list_' . $this->sanitizeSegment($applicationSlug);
    }

    public function crmRepositoryDetailTag(string $applicationSlug, string $repositoryId): string
    {
        return 'cache_crm_repository_detail_' . $this->sanitizeSegment($applicationSlug) . '_' . $this->sanitizeSegment($repositoryId);
    }

    public function crmIssueListTag(string $applicationSlug): string
    {
        return 'cache_crm_issue_list_' . $this->sanitizeSegment($applicationSlug);
    }

    public function crmIssueDetailTag(string $applicationSlug, string $issueId): string
    {
        return 'cache_crm_issue_detail_' . $this->sanitizeSegment($applicationSlug) . '_' . $this->sanitizeSegment($issueId);
    }

    public function crmTaskDetailTag(string $applicationSlug, string $taskId): string
    {
        return 'cache_crm_task_detail_' . $this->sanitizeSegment($applicationSlug) . '_' . $this->sanitizeSegment($taskId);
    }

    public function crmSprintListTag(string $applicationSlug): string
    {
        return 'cache_crm_sprint_list_' . $this->sanitizeSegment($applicationSlug);
    }

    public function crmSprintDetailTag(string $applicationSlug, string $sprintId): string
    {
        return 'cache_crm_sprint_detail_' . $this->sanitizeSegment($applicationSlug) . '_' . $this->sanitizeSegment($sprintId);
    }

    public function schoolExamListTag(): string
    {
        return 'cache_school_exam_list';
    }

    public function schoolExamListTagByApplication(string $applicationSlug): string
    {
        return 'cache_school_exam_list_' . $this->sanitizeSegment($applicationSlug);
    }

    public function schoolClassListByApplicationTag(string $applicationSlug): string
    {
        return 'cache_school_class_list_' . $this->sanitizeSegment($applicationSlug);
    }

    /**
     * @param array<string, mixed> $payload
     * @throws JsonException
     */
    private function buildHash(array $payload): string
    {
        return md5((string)json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function sanitizeSegment(string $segment): string
    {
        $normalized = strtolower(trim($segment));
        $normalized = (string)preg_replace('/[^a-z0-9_.-]+/', '_', $normalized);
        $normalized = trim($normalized, '._-');

        return $normalized !== '' ? $normalized : 'default';
    }
}
