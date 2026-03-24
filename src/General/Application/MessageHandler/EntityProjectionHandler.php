<?php

declare(strict_types=1);

namespace App\General\Application\MessageHandler;

use App\Crm\Application\Projection\CrmIssueProjection;
use App\Crm\Application\Projection\CrmRepositoryProjection;
use App\Crm\Application\Projection\CrmTaskProjection;
use App\Crm\Infrastructure\Repository\CrmGithubWebhookEventRepository;
use App\Crm\Infrastructure\Repository\CrmProjectRepositoryRepository;
use App\Crm\Infrastructure\Repository\TaskRepository;
use App\General\Application\Message\EntityCreated;
use App\General\Application\Message\EntityDeleted;
use App\General\Application\Message\EntityMutationMessage;
use App\General\Application\Message\EntityPatched;
use App\General\Application\Service\CacheInvalidationService;
use App\General\Application\Service\CriticalViewWarmer;
use App\General\Application\Service\MessageIdempotenceGuard;
use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use App\Platform\Application\Projection\ApplicationProjection;
use App\Platform\Infrastructure\Repository\ApplicationRepository;
use App\Recruit\Application\Projection\RecruitJobProjection;
use App\Recruit\Infrastructure\Repository\JobRepository;
use App\School\Application\Projection\SchoolExamProjection;
use App\School\Infrastructure\Repository\ExamRepository;
use App\Shop\Application\Projection\ShopProductProjection;
use App\Shop\Infrastructure\Repository\ProductRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

use function array_map;
use function is_array;

#[AsMessageHandler]
final readonly class EntityProjectionHandler
{
    private const string PLATFORM_APPLICATION = 'platform_application';
    private const string RECRUIT_JOB = 'recruit_job';
    private const string SHOP_PRODUCT = 'shop_product';
    private const string CRM_TASK = 'crm_task';
    private const string CRM_COMPANY = 'crm_company';
    private const string CRM_PROJECT = 'crm_project';
    private const string CRM_TASK_REQUEST = 'crm_task_request';
    private const string CRM_SPRINT = 'crm_sprint';
    private const string CRM_REPOSITORY = 'crm_repository';
    private const string CRM_ISSUE = 'crm_issue';
    private const string SCHOOL_EXAM = 'school_exam';
    private const string SCHOOL_CLASS = 'school_class';
    private const string SCHOOL_TEACHER = 'school_teacher';
    private const string SCHOOL_STUDENT = 'school_student';
    private const string SCHOOL_GRADE = 'school_grade';
    private const string SHOP_CATEGORY = 'shop_category';
    private const string SHOP_TAG = 'shop_tag';

    public function __construct(
        private ApplicationRepository $applicationRepository,
        private JobRepository $jobRepository,
        private ProductRepository $productRepository,
        private TaskRepository $taskRepository,
        private CrmProjectRepositoryRepository $crmProjectRepositoryRepository,
        private CrmGithubWebhookEventRepository $crmGithubWebhookEventRepository,
        private ExamRepository $examRepository,
        private CacheInvalidationService $cacheInvalidationService,
        private CriticalViewWarmer $criticalViewWarmer,
        private ElasticsearchServiceInterface $elasticsearchService,
        private MessageIdempotenceGuard $messageIdempotenceGuard,
    ) {
    }

    public function __invoke(EntityCreated|EntityPatched|EntityDeleted $message): void
    {
        if ($this->messageIdempotenceGuard->shouldProcess($message->eventId) === false) {
            return;
        }

        if ($message->entityType === self::PLATFORM_APPLICATION) {
            $this->projectPlatformApplication($message);

            return;
        }

        if ($message->entityType === self::RECRUIT_JOB) {
            $this->projectRecruitJob($message);

            return;
        }

        if ($message->entityType === self::SHOP_PRODUCT) {
            $this->projectShopProduct($message);

            return;
        }

        if ($message->entityType === self::SHOP_CATEGORY || $message->entityType === self::SHOP_TAG) {
            $this->projectShopCatalog();

            return;
        }

        if ($message->entityType === self::CRM_TASK) {
            $this->projectCrmTask($message);

            return;
        }

        if ($message->entityType === self::CRM_REPOSITORY) {
            $this->projectCrmRepository($message);

            return;
        }

        if ($message->entityType === self::CRM_ISSUE) {
            $this->projectCrmIssue($message);

            return;
        }

        if (
            $message->entityType === self::CRM_COMPANY
            || $message->entityType === self::CRM_PROJECT
            || $message->entityType === self::CRM_TASK_REQUEST
            || $message->entityType === self::CRM_SPRINT
        ) {
            $this->projectCrmSupportEntities($message);

            return;
        }

        if ($message->entityType === self::SCHOOL_EXAM) {
            $this->projectSchoolExam($message);

            return;
        }

        if (
            $message->entityType === self::SCHOOL_CLASS
            || $message->entityType === self::SCHOOL_TEACHER
            || $message->entityType === self::SCHOOL_STUDENT
            || $message->entityType === self::SCHOOL_GRADE
        ) {
            $this->projectSchoolSupportEntities();
        }
    }

    private function projectPlatformApplication(EntityMutationMessage $message): void
    {
        if ($message instanceof EntityDeleted) {
            $this->elasticsearchService->delete(ApplicationProjection::INDEX_NAME, $message->entityId);
            $this->cacheInvalidationService->invalidateApplicationListCaches();
            $this->criticalViewWarmer->warmApplicationList();

            return;
        }

        $application = $this->applicationRepository->find($message->entityId);
        if ($application === null) {
            return;
        }

        $this->elasticsearchService->index(ApplicationProjection::INDEX_NAME, $application->getId(), [
            'id' => $application->getId(),
            'title' => $application->getTitle(),
            'description' => $application->getDescription(),
            'slug' => $application->getSlug(),
            'platformName' => $application->getPlatform()?->getName() ?? '',
            'platformKey' => $application->getPlatform()?->getPlatformKeyValue() ?? '',
            'status' => $application->getStatus()->value,
            'private' => $application->isPrivate(),
            'updatedAt' => $application->getUpdatedAt()?->format(DATE_ATOM),
        ]);

        $this->cacheInvalidationService->invalidateApplicationListCaches();
        $this->criticalViewWarmer->warmApplicationList();
    }

    private function projectRecruitJob(EntityMutationMessage $message): void
    {
        $applicationSlug = (string)($message->context['applicationSlug'] ?? '');

        if ($message instanceof EntityDeleted) {
            $this->elasticsearchService->delete(RecruitJobProjection::INDEX_NAME, $message->entityId);
            if ($applicationSlug !== '') {
                $this->cacheInvalidationService->invalidateRecruitJobListCaches($applicationSlug);
                $this->criticalViewWarmer->warmRecruitJobList($applicationSlug);
            }

            return;
        }

        $job = $this->jobRepository->find($message->entityId);
        if ($job === null) {
            return;
        }

        $applicationSlug = $job->getRecruit()?->getApplication()?->getSlug() ?? $applicationSlug;

        $this->elasticsearchService->index(RecruitJobProjection::INDEX_NAME, $job->getId(), [
            'id' => $job->getId(),
            'slug' => $job->getSlug(),
            'title' => $job->getTitle(),
            'summary' => $job->getSummary(),
            'location' => $job->getLocation(),
            'contractType' => $job->getContractTypeValue(),
            'workMode' => $job->getWorkModeValue(),
            'schedule' => $job->getScheduleValue(),
            'experienceLevel' => $job->getExperienceLevelValue(),
            'yearsExperienceMin' => $job->getYearsExperienceMin(),
            'yearsExperienceMax' => $job->getYearsExperienceMax(),
            'tags' => array_map(static fn ($tag): string => $tag->getLabel(), $job->getTags()->toArray()),
            'applicationSlug' => $applicationSlug,
            'updatedAt' => $job->getUpdatedAt()?->format(DATE_ATOM),
        ]);

        if ($applicationSlug !== '') {
            $this->cacheInvalidationService->invalidateRecruitJobListCaches($applicationSlug);
            $this->criticalViewWarmer->warmRecruitJobList($applicationSlug);
        }
    }

    private function projectShopProduct(EntityMutationMessage $message): void
    {
        if ($message instanceof EntityDeleted) {
            $this->elasticsearchService->delete(ShopProductProjection::INDEX_NAME, $message->entityId);
            $this->cacheInvalidationService->invalidateShopProductListCaches();

            return;
        }

        $product = $this->productRepository->find($message->entityId);
        if ($product === null) {
            return;
        }

        $this->elasticsearchService->index(ShopProductProjection::INDEX_NAME, $product->getId(), [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'price' => $product->getPrice(),
            'sku' => $product->getSku(),
            'status' => $product->getStatus()->value,
            'stock' => $product->getStock(),
            'currencyCode' => $product->getCurrencyCode(),
            'categoryId' => $product->getCategory()?->getId(),
            'categoryName' => $product->getCategory()?->getName() ?? '',
            'tags' => array_map(static fn ($tag): string => $tag->getLabel(), $product->getTags()->toArray()),
            'updatedAt' => $product->getUpdatedAt()?->format(DATE_ATOM),
        ]);

        $this->cacheInvalidationService->invalidateShopProductListCaches();
    }

    private function projectCrmTask(EntityMutationMessage $message): void
    {
        $applicationSlug = (string)($message->context['applicationSlug'] ?? '');

        if ($message instanceof EntityDeleted) {
            $this->elasticsearchService->delete(CrmTaskProjection::INDEX_NAME, $message->entityId);
            if ($applicationSlug !== '') {
                $this->cacheInvalidationService->invalidateCrmTaskListCaches($applicationSlug);
            }

            return;
        }

        $task = $this->taskRepository->find($message->entityId);
        if ($task === null) {
            return;
        }

        $this->elasticsearchService->index(CrmTaskProjection::INDEX_NAME, $task->getId(), [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'projectName' => $task->getProject()?->getName() ?? '',
            'sprintName' => $task->getSprint()?->getName() ?? '',
            'taskRequests' => array_map(static fn ($request): string => $request->getTitle(), $task->getTaskRequests()->toArray()),
            'updatedAt' => $task->getUpdatedAt()?->format(DATE_ATOM),
        ]);

        $applicationSlug = $task->getProject()?->getCompany()?->getCrm()?->getApplication()?->getSlug() ?? $applicationSlug;
        if ($applicationSlug !== '') {
            $this->cacheInvalidationService->invalidateCrmTaskListCaches($applicationSlug);
        }
    }

    private function projectSchoolExam(EntityMutationMessage $message): void
    {
        if ($message instanceof EntityDeleted) {
            $this->elasticsearchService->delete(SchoolExamProjection::INDEX_NAME, $message->entityId);
            $this->cacheInvalidationService->invalidateSchoolExamListCaches(isset($message->context['applicationSlug']) ? (string)$message->context['applicationSlug'] : null);

            return;
        }

        $exam = $this->examRepository->find($message->entityId);
        if ($exam === null) {
            return;
        }

        $this->elasticsearchService->index(SchoolExamProjection::INDEX_NAME, $exam->getId(), [
            'id' => $exam->getId(),
            'title' => $exam->getTitle(),
            'className' => $exam->getSchoolClass()?->getName() ?? '',
            'teacherName' => $exam->getTeacher()?->getName() ?? '',
            'grades' => array_map(static fn ($grade): float => $grade->getScore(), $exam->getGrades()->toArray()),
            'updatedAt' => $exam->getUpdatedAt()?->format(DATE_ATOM),
        ]);

        $applicationSlug = $exam->getSchoolClass()?->getSchool()?->getApplication()?->getSlug();
        $this->cacheInvalidationService->invalidateSchoolExamListCaches($applicationSlug);
    }

    private function projectShopCatalog(): void
    {
        $this->cacheInvalidationService->invalidateShopProductListCaches();
    }

    private function projectCrmSupportEntities(EntityMutationMessage $message): void
    {
        $applicationSlug = (string)($message->context['applicationSlug'] ?? '');
        if ($applicationSlug !== '') {
            $this->cacheInvalidationService->invalidateCrmTaskListCaches($applicationSlug);
        }
    }

    private function projectSchoolSupportEntities(): void
    {
        $this->cacheInvalidationService->invalidateSchoolExamListCaches(null);
    }

    private function projectCrmRepository(EntityMutationMessage $message): void
    {
        $applicationSlug = (string)($message->context['applicationSlug'] ?? '');

        if ($message instanceof EntityDeleted) {
            $this->elasticsearchService->delete(CrmRepositoryProjection::INDEX_NAME, $message->entityId);
            if ($applicationSlug !== '') {
                $this->cacheInvalidationService->invalidateCrmTaskListCaches($applicationSlug);
            }

            return;
        }

        $repository = $this->crmProjectRepositoryRepository->find($message->entityId);
        if ($repository === null) {
            return;
        }

        $this->elasticsearchService->index(CrmRepositoryProjection::INDEX_NAME, $repository->getId(), [
            'id' => $repository->getId(),
            'projectId' => $repository->getProject()?->getId(),
            'projectName' => $repository->getProject()?->getName(),
            'provider' => $repository->getProvider(),
            'owner' => $repository->getOwner(),
            'name' => $repository->getName(),
            'fullName' => $repository->getFullName(),
            'defaultBranch' => $repository->getDefaultBranch(),
            'isPrivate' => $repository->isPrivate(),
            'htmlUrl' => $repository->getHtmlUrl(),
            'externalId' => $repository->getExternalId(),
            'syncStatus' => $repository->getSyncStatus(),
            'lastSyncedAt' => $repository->getLastSyncedAt()?->format(DATE_ATOM),
            'updatedAt' => $repository->getUpdatedAt()?->format(DATE_ATOM),
        ]);

        $applicationSlug = $repository->getProject()?->getCompany()?->getCrm()?->getApplication()?->getSlug() ?? $applicationSlug;
        if ($applicationSlug !== '') {
            $this->cacheInvalidationService->invalidateCrmTaskListCaches($applicationSlug);
        }
    }

    private function projectCrmIssue(EntityMutationMessage $message): void
    {
        $applicationSlug = (string)($message->context['applicationSlug'] ?? '');

        if ($message instanceof EntityDeleted) {
            $this->elasticsearchService->delete(CrmIssueProjection::INDEX_NAME, $message->entityId);
            if ($applicationSlug !== '') {
                $this->cacheInvalidationService->invalidateCrmTaskListCaches($applicationSlug);
            }

            return;
        }

        $event = $this->crmGithubWebhookEventRepository->find($message->entityId);
        if ($event === null) {
            return;
        }

        $payload = $event->getPayload();
        $issuePayload = isset($payload['issue']) && is_array($payload['issue']) ? $payload['issue'] : [];

        $this->elasticsearchService->index(CrmIssueProjection::INDEX_NAME, $event->getId(), [
            'id' => $event->getId(),
            'deliveryId' => $event->getDeliveryId(),
            'repositoryFullName' => $event->getRepositoryFullName(),
            'eventAction' => $event->getEventAction(),
            'issueNumber' => $issuePayload['number'] ?? null,
            'issueTitle' => $issuePayload['title'] ?? null,
            'issueState' => $issuePayload['state'] ?? null,
            'updatedAt' => $event->getUpdatedAt()?->format(DATE_ATOM),
        ]);

        if ($applicationSlug !== '') {
            $this->cacheInvalidationService->invalidateCrmTaskListCaches($applicationSlug);
        }
    }
}
