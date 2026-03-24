<?php

declare(strict_types=1);

namespace App\Crm\Application\MessageHandler;

use App\Crm\Application\Message\GithubWebhookReceived;
use App\Crm\Application\Service\CrmReadCacheInvalidator;
use App\Crm\Application\Service\CrmTaskRequestGithubStatusMapper;
use App\Crm\Domain\Enum\TaskRequestStatus;
use App\Crm\Infrastructure\Repository\CrmGithubWebhookEventRepository;
use App\Crm\Infrastructure\Repository\CrmProjectRepositoryRepository;
use App\Crm\Infrastructure\Repository\TaskRequestGithubBranchRepository;
use App\Crm\Infrastructure\Repository\TaskRequestRepository;
use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use DateTimeImmutable;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

use function array_key_exists;
use function in_array;
use function is_array;
use function is_string;
use function preg_match;
use function strtolower;
use function trim;

#[AsMessageHandler]
final readonly class GithubWebhookReceivedHandler
{
    private const string INDEX_NAME = 'crm_github_event';

    public function __construct(
        private CrmGithubWebhookEventRepository $webhookEventRepository,
        private CrmProjectRepositoryRepository $crmProjectRepositoryRepository,
        private TaskRequestGithubBranchRepository $taskRequestGithubBranchRepository,
        private TaskRequestRepository $taskRequestRepository,
        private CrmReadCacheInvalidator $crmReadCacheInvalidator,
        private CrmTaskRequestGithubStatusMapper $statusMapper,
        private ElasticsearchServiceInterface $elasticsearchService,
    ) {
    }

    public function __invoke(GithubWebhookReceived $message): void
    {
        $webhookEvent = $this->webhookEventRepository->find($message->webhookEventId);
        if ($webhookEvent === null) {
            return;
        }

        $repository = null;
        $applicationSlug = null;

        if ($message->repositoryFullName !== null && $message->repositoryFullName !== '') {
            $repository = $this->crmProjectRepositoryRepository->findOneByProviderAndFullName('github', $message->repositoryFullName);
        }

        if ($repository !== null) {
            $repositoryPayload = $repository->getPayload() ?? [];
            $repositoryPayload['lastWebhook'] = [
                'deliveryId' => $message->deliveryId,
                'event' => $message->eventName,
                'action' => $message->action,
                'checksum' => $message->checksum,
                'receivedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
            ];

            if (isset($message->payload['repository']) && is_array($message->payload['repository'])) {
                $repoPayload = $message->payload['repository'];
                $repositoryPayload['updatedAt'] = is_string($repoPayload['updated_at'] ?? null) ? $repoPayload['updated_at'] : null;
            }

            $repository
                ->setLastSyncedAt(new DateTimeImmutable())
                ->setSyncStatus('synced')
                ->setPayload($repositoryPayload);

            $this->crmProjectRepositoryRepository->save($repository, true);

            $project = $repository->getProject();
            $applicationSlug = $project?->getCompany()?->getCrm()?->getApplication()?->getSlug();
            if ($applicationSlug !== null && $applicationSlug !== '') {
                $this->crmReadCacheInvalidator->invalidateProjectCaches($applicationSlug, $project?->getId());
                $this->crmReadCacheInvalidator->invalidateRepository($applicationSlug, $repository->getId());
                if ($message->eventName === 'issues' || $message->eventName === 'issue_comment') {
                    $this->crmReadCacheInvalidator->invalidateIssue($applicationSlug, $webhookEvent->getId());
                }
            }
        }

        if ($repository !== null && ($message->eventName === 'issues' || $message->eventName === 'issue_comment')) {
            $this->synchronizeMappedTaskRequest($message, $repository->getFullName(), $applicationSlug);
        }

        if ($repository !== null && ($message->eventName === 'create' || $message->eventName === 'delete')) {
            $this->synchronizeMappedTaskRequestBranches($message, $repository->getFullName());
        }

        $this->elasticsearchService->index(self::INDEX_NAME, $webhookEvent->getId(), [
            'id' => $webhookEvent->getId(),
            'deliveryId' => $message->deliveryId,
            'event' => $message->eventName,
            'action' => $message->action,
            'repositoryFullName' => $message->repositoryFullName,
            'applicationSlug' => $applicationSlug,
            'checksum' => $message->checksum,
            'receivedAt' => $webhookEvent->getCreatedAt()?->format(DATE_ATOM),
            'processedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        $webhookEvent->setStatus('processed')->setProcessedAt(new DateTimeImmutable());
        $this->webhookEventRepository->save($webhookEvent, true);
    }

    private function synchronizeMappedTaskRequest(GithubWebhookReceived $message, string $repositoryFullName, ?string $applicationSlug): void
    {
        $issuePayload = $this->extractIssuePayload($message->payload);
        if ($issuePayload === null) {
            return;
        }

        $issueNumber = isset($issuePayload['number']) ? (int)$issuePayload['number'] : 0;
        if ($issueNumber <= 0) {
            return;
        }

        $taskRequest = $this->taskRequestRepository->findOneByGithubIssueMapping($repositoryFullName, $issueNumber);
        if ($taskRequest === null) {
            return;
        }

        $githubIssue = $taskRequest->getGithubIssue();
        if ($githubIssue === null) {
            return;
        }

        $metadata = $githubIssue->getMetadata();
        $pendingOutbound = is_array($metadata['pendingOutbound'] ?? null) ? $metadata['pendingOutbound'] : null;
        $issueState = strtolower(trim((string)($issuePayload['state'] ?? '')));

        if ($pendingOutbound !== null) {
            $expectedIssueState = strtolower(trim((string)($pendingOutbound['expectedIssueState'] ?? '')));
            if ($expectedIssueState !== '' && $expectedIssueState === $issueState) {
                unset($metadata['pendingOutbound']);
                $metadata['lastIgnoredWebhook'] = [
                    'deliveryId' => $message->deliveryId,
                    'event' => $message->eventName,
                    'action' => $message->action,
                    'ignoredAt' => (new DateTimeImmutable())->format(DATE_ATOM),
                ];
                $githubIssue->setMetadata($metadata)->setLastSyncedAt(new DateTimeImmutable());
                $this->taskRequestRepository->save($taskRequest, true);

                return;
            }
        }

        if ($message->eventName === 'issue_comment' && $this->isOutboundMarkerComment($message->payload, $pendingOutbound)) {
            $metadata['lastIgnoredWebhook'] = [
                'deliveryId' => $message->deliveryId,
                'event' => $message->eventName,
                'action' => $message->action,
                'ignoredAt' => (new DateTimeImmutable())->format(DATE_ATOM),
            ];
            unset($metadata['pendingOutbound']);
            $githubIssue->setMetadata($metadata)->setLastSyncedAt(new DateTimeImmutable());
            $this->taskRequestRepository->save($taskRequest, true);

            return;
        }

        if ($message->eventName === 'issues') {
            $allowedActions = ['opened', 'edited', 'closed', 'reopened'];
            if (!in_array((string)$message->action, $allowedActions, true)) {
                return;
            }
        }

        $resolvedStatus = $this->statusMapper->resolveTaskRequestStatusFromIssuePayload($issuePayload);
        if ($taskRequest->getStatus() !== $resolvedStatus) {
            $taskRequest->setStatus($resolvedStatus);
            if (in_array($resolvedStatus, [TaskRequestStatus::APPROVED, TaskRequestStatus::REJECTED], true)) {
                $taskRequest->setResolvedAt(new DateTimeImmutable());
            } else {
                $taskRequest->setResolvedAt(null);
            }
        }

        $metadata['lastInboundWebhook'] = [
            'deliveryId' => $message->deliveryId,
            'event' => $message->eventName,
            'action' => $message->action,
            'status' => $resolvedStatus->value,
            'receivedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];

        $githubIssue
            ->setIssueNodeId(is_string($issuePayload['node_id'] ?? null) ? (string)$issuePayload['node_id'] : $githubIssue->getIssueNodeId())
            ->setIssueUrl(is_string($issuePayload['html_url'] ?? null) ? (string)$issuePayload['html_url'] : $githubIssue->getIssueUrl())
            ->setSyncStatus('synced')
            ->setLastSyncedAt(new DateTimeImmutable())
            ->setMetadata($metadata);

        $this->taskRequestRepository->save($taskRequest, true);
        if ($applicationSlug !== null && $applicationSlug !== '') {
            $this->crmReadCacheInvalidator->invalidateTaskRequest($applicationSlug, $taskRequest->getId());
        }
    }

    private function synchronizeMappedTaskRequestBranches(GithubWebhookReceived $message, string $repositoryFullName): void
    {
        $branchName = trim((string)($message->payload['ref'] ?? ''));
        $refType = strtolower(trim((string)($message->payload['ref_type'] ?? '')));
        if ($branchName === '' || $refType !== 'branch') {
            return;
        }

        $mappedBranches = $this->taskRequestGithubBranchRepository->findByRepositoryAndBranch($repositoryFullName, $branchName);
        if ($mappedBranches === []) {
            return;
        }

        $syncedAt = new DateTimeImmutable();
        $nextStatus = $message->eventName === 'delete' ? 'deleted' : 'synced';
        $repositoryHtmlUrl = trim((string)($message->payload['repository']['html_url'] ?? ''));
        $invalidatedTaskRequests = [];

        foreach ($mappedBranches as $mappedBranch) {
            $metadata = $mappedBranch->getMetadata();
            $metadata['lastInboundWebhook'] = [
                'deliveryId' => $message->deliveryId,
                'event' => $message->eventName,
                'action' => $message->action,
                'status' => $nextStatus,
                'receivedAt' => $syncedAt->format(DATE_ATOM),
            ];

            $mappedBranch
                ->setSyncStatus($nextStatus)
                ->setLastSyncedAt($syncedAt)
                ->setMetadata($metadata);

            if ($repositoryHtmlUrl !== '') {
                $mappedBranch->setBranchUrl($repositoryHtmlUrl . '/tree/' . $mappedBranch->getBranchName());
            }

            if ($message->eventName === 'delete') {
                $mappedBranch->setBranchSha(null);
            }

            $this->taskRequestGithubBranchRepository->save($mappedBranch, true);

            $taskRequest = $mappedBranch->getTaskRequest();
            $applicationSlug = $taskRequest?->getTask()?->getProject()?->getCompany()?->getCrm()?->getApplication()?->getSlug();
            $taskRequestId = $taskRequest?->getId();
            if ($applicationSlug === null || $applicationSlug === '' || $taskRequestId === null || $taskRequestId === '') {
                continue;
            }

            $cacheKey = $applicationSlug . ':' . $taskRequestId;
            if (isset($invalidatedTaskRequests[$cacheKey])) {
                continue;
            }

            $this->crmReadCacheInvalidator->invalidateTaskRequest($applicationSlug, $taskRequestId);
            $invalidatedTaskRequests[$cacheKey] = true;
        }
    }

    /**
     * @param array<string,mixed> $payload
     *
     * @return array<string,mixed>|null
     */
    private function extractIssuePayload(array $payload): ?array
    {
        if (!is_array($payload['issue'] ?? null)) {
            return null;
        }

        return $payload['issue'];
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed>|null $pendingOutbound
     */
    private function isOutboundMarkerComment(array $payload, ?array $pendingOutbound): bool
    {
        if ($pendingOutbound === null || !array_key_exists('marker', $pendingOutbound)) {
            return false;
        }

        if (!is_array($payload['comment'] ?? null)) {
            return false;
        }

        $commentBody = trim((string)($payload['comment']['body'] ?? ''));
        if ($commentBody === '') {
            return false;
        }

        if (preg_match('/crm-source:([a-zA-Z0-9._:-]+)/', $commentBody, $matches) !== 1) {
            return false;
        }

        return trim((string)$matches[1]) === trim((string)$pendingOutbound['marker']);
    }
}
