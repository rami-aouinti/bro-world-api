<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

use App\Crm\Domain\Entity\Task;
use App\Crm\Domain\Entity\TaskRequest;
use App\Crm\Domain\Entity\TaskRequestGithubBranch;
use App\User\Domain\Entity\User;
use DateTimeInterface;

use function array_map;
use function array_values;
use function is_array;
use function is_string;
use function max;

final readonly class CrmApiNormalizer
{
    public function __construct(
        private CrmBlogNormalizer $crmBlogNormalizer,
        private TaskRequestWorklogReadService $taskRequestWorklogReadService,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function normalizeTask(Task $task): array
    {
        return $this->normalizeTaskWithOptions($task, true);
    }

    /**
     * Board-safe variant that skips blog hydration.
     *
     * @return array<string,mixed>
     */
    public function normalizeTaskForBoard(Task $task): array
    {
        return $this->normalizeTaskWithOptions($task, false);
    }

    /**
     * @return array<string,mixed>
     */
    private function normalizeTaskWithOptions(Task $task, bool $includeBlog): array
    {
        $assignees = $this->mapUserAssignees($task->getAssignees());
        $taskRequests = $task->getTaskRequests()->toArray();
        $taskRequestIds = array_values(array_map(
            static fn (TaskRequest $taskRequest): string => $taskRequest->getId(),
            $taskRequests,
        ));
        $consumedHoursByTaskRequestId = $this->taskRequestWorklogReadService->getConsumedHoursByTaskRequestIds($taskRequestIds);

        $payload = [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'status' => $task->getStatus()->value,
            'priority' => $task->getPriority()->value,
            'projectId' => $task->getProject()?->getId(),
            'projectName' => $task->getProject()?->getName(),
            'sprintId' => $task->getSprint()?->getId(),
            'sprintName' => $task->getSprint()?->getName(),
            'parentTaskId' => $task->getParentTask()?->getId(),
            'dueAt' => $this->normalizeDate($task->getDueAt()),
            'estimatedHours' => $task->getEstimatedHours(),
            'updatedAt' => $this->normalizeDate($task->getUpdatedAt()),
            'attachments' => $task->getAttachments(),
            'blogId' => $task->getBlog()?->getId(),
            'assignees' => $assignees,
            'subTasks' => array_map(
                function (Task $subTask): array {
                    $subTaskAssignees = $this->mapUserAssignees($subTask->getAssignees());

                    return [
                        'id' => $subTask->getId(),
                        'title' => $subTask->getTitle(),
                        'description' => $subTask->getDescription(),
                        'status' => $subTask->getStatus()->value,
                        'priority' => $subTask->getPriority()->value,
                        'parentTaskId' => $subTask->getParentTask()?->getId(),
                        'projectId' => $subTask->getProject()?->getId(),
                        'projectName' => $subTask->getProject()?->getName(),
                        'sprintId' => $subTask->getSprint()?->getId(),
                        'sprintName' => $subTask->getSprint()?->getName(),
                        'dueAt' => $this->normalizeDate($subTask->getDueAt()),
                        'estimatedHours' => $subTask->getEstimatedHours(),
                        'updatedAt' => $this->normalizeDate($subTask->getUpdatedAt()),
                        'attachments' => $subTask->getAttachments(),
                        'blogId' => $subTask->getBlog()?->getId(),
                        'assignees' => $subTaskAssignees,
                    ];
                },
                $task->getSubTasks()->toArray()
            ),
            'children' => array_map(
                function (TaskRequest $taskRequest) use ($consumedHoursByTaskRequestId): array {
                    $consumedHours = $consumedHoursByTaskRequestId[$taskRequest->getId()] ?? 0.0;

                    return [
                        'id' => $taskRequest->getId(),
                        'title' => $taskRequest->getTitle(),
                        'description' => $taskRequest->getDescription(),
                        'status' => $taskRequest->getStatus(),
                        'plannedHours' => $taskRequest->getPlannedHours(),
                        'consumedHours' => $consumedHours,
                        'remainingHours' => $this->taskRequestWorklogReadService->getRemainingHours($taskRequest->getPlannedHours(), $consumedHours),
                    ];
                },
                $taskRequests
            ),
        ];

        if ($includeBlog) {
            $payload['blog'] = $this->crmBlogNormalizer->normalizeBlog($task->getBlog());
        }

        return $payload;
    }

    /**
     * @return array<string,mixed>
     */
    public function normalizeTaskRequest(TaskRequest $taskRequest): array
    {
        $assignees = $this->mapUserAssignees($taskRequest->getAssignees());
        $consumedHours = $this->taskRequestWorklogReadService->getConsumedHours($taskRequest->getId());

        return [
            'id' => $taskRequest->getId(),
            'taskId' => $taskRequest->getTask()?->getId(),
            'repositoryId' => $taskRequest->getRepository()?->getId(),
            'title' => $taskRequest->getTitle(),
            'status' => $taskRequest->getStatus()->value,
            'requestedAt' => $this->normalizeDate($taskRequest->getRequestedAt()),
            'resolvedAt' => $this->normalizeDate($taskRequest->getResolvedAt()),
            'attachments' => $taskRequest->getAttachments(),
            'blogId' => $taskRequest->getBlog()?->getId(),
            'assignees' => $assignees,
            'plannedHours' => $taskRequest->getPlannedHours(),
            'consumedHours' => $consumedHours,
            'remainingHours' => $this->taskRequestWorklogReadService->getRemainingHours($taskRequest->getPlannedHours(), $consumedHours),
            'githubIssue' => $taskRequest->getGithubIssue()?->toArray(),
            'githubBranches' => array_map(static fn (TaskRequestGithubBranch $branch): array => $branch->toArray(), $taskRequest->getGithubBranches()->toArray()),
            'blog' => $this->crmBlogNormalizer->normalizeBlog($taskRequest->getBlog()),
        ];
    }

    /** @param array<string,mixed> $item
     * @return array<string,mixed>
     */
    public function normalizeSprintProjection(array $item): array
    {
        return [
            'id' => (string)($item['id'] ?? ''),
            'name' => (string)($item['name'] ?? ''),
            'status' => ($item['status'] ?? ''),
            'startDate' => $this->normalizeDateValue($item['startDate'] ?? null),
            'endDate' => $this->normalizeDateValue($item['endDate'] ?? null),
            'blogId' => $item['blogId'] ?? null,
        ];
    }

    /** @param array<string,mixed> $item
     * @return array<string,mixed>
     */
    public function normalizeProjectProjection(array $item): array
    {
        $repositoriesCount = (int)($item['githubRepositoriesCount'] ?? 0);

        $provisioningState = is_string($item['provisioningStatus'] ?? null) ? $item['provisioningStatus'] : 'pending';
        $githubResourceIds = is_array($item['githubResourceIds'] ?? null) ? $item['githubResourceIds'] : [];

        return [
            'id' => (string)($item['id'] ?? ''),
            'name' => (string)($item['name'] ?? ''),
            'status' => ($item['status'] ?? ''),
            'blogId' => $item['blogId'] ?? null,
            'githubRepositoriesCount' => $repositoriesCount,
            'provisioning' => [
                'state' => $provisioningState,
                'error' => is_array($githubResourceIds['provisioningError'] ?? null) ? $githubResourceIds['provisioningError'] : null,
            ],
        ];
    }

    /** @param array<string,mixed> $item
     * @return array<string,mixed>
     */
    public function normalizeTaskRequestProjection(array $item): array
    {
        $plannedHours = (float)($item['plannedHours'] ?? 0);
        $consumedHours = (float)($item['consumedHours'] ?? 0);

        return [
            'id' => (string)($item['id'] ?? ''),
            'title' => (string)($item['title'] ?? ''),
            'status' => ($item['status'] ?? ''),
            'requestedAt' => $this->normalizeDateValue($item['requestedAt'] ?? null),
            'resolvedAt' => $this->normalizeDateValue($item['resolvedAt'] ?? null),
            'plannedHours' => $plannedHours,
            'consumedHours' => $consumedHours,
            'remainingHours' => max(0.0, $plannedHours - $consumedHours),
            'assignees' => $this->mapTaskRequestAssigneesProjection((array)($item['assignees'] ?? [])),
            'githubIssue' => [
                'provider' => $item['githubIssueProvider'] ?? null,
                'repositoryFullName' => $item['githubIssueRepositoryFullName'] ?? null,
                'issueNumber' => $item['githubIssueIssueNumber'] ?? null,
                'issueNodeId' => $item['githubIssueIssueNodeId'] ?? null,
                'issueUrl' => $item['githubIssueIssueUrl'] ?? null,
                'syncStatus' => $item['githubIssueSyncStatus'] ?? null,
                'lastSyncedAt' => $this->normalizeDateValue($item['githubIssueLastSyncedAt'] ?? null),
            ],
            'githubBranches' => $this->normalizeTaskRequestProjectionBranches((array)($item['githubBranches'] ?? [])),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function normalizeAssignee(User $user): array
    {
        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'photo' => $user->getPhoto(),
        ];
    }

    /**
     * @param iterable<mixed> $assignees
     * @return array<int,array<string,mixed>>
     */
    private function mapUserAssignees(iterable $assignees): array
    {
        $normalizedAssignees = [];

        foreach ($assignees as $assignee) {
            if (!$assignee instanceof User) {
                continue;
            }

            $normalizedAssignees[] = $this->normalizeAssignee($assignee);
        }

        return $normalizedAssignees;
    }

    /**
     * @param array<int,mixed> $assignees
     * @return array<int,array<string,mixed>>
     */
    private function mapTaskRequestAssigneesProjection(array $assignees): array
    {
        $normalizedAssignees = [];

        foreach ($assignees as $assignee) {
            if (!is_array($assignee)) {
                continue;
            }

            $normalizedAssignees[] = [
                'id' => $assignee['id'] ?? null,
                'username' => $assignee['username'] ?? $assignee['email'] ?? null,
                'firstName' => $assignee['firstName'] ?? null,
                'lastName' => $assignee['lastName'] ?? null,
                'photo' => $assignee['photo'] ?? null,
            ];
        }

        return $normalizedAssignees;
    }

    /**
     * @param array<int,mixed> $branches
     * @return array<int,array<string,mixed>>
     */
    private function normalizeTaskRequestProjectionBranches(array $branches): array
    {
        $normalizedBranches = [];

        foreach ($branches as $branch) {
            if (!is_array($branch)) {
                continue;
            }

            $normalizedBranches[] = [
                'id' => $branch['id'] ?? null,
                'repositoryFullName' => $branch['repositoryFullName'] ?? null,
                'branchName' => $branch['branchName'] ?? null,
                'branchSha' => $branch['branchSha'] ?? null,
                'branchUrl' => $branch['branchUrl'] ?? null,
                'issueNumber' => $branch['issueNumber'] ?? null,
                'syncStatus' => $branch['syncStatus'] ?? null,
                'createdAt' => $this->normalizeDateValue($branch['createdAt'] ?? null),
                'lastSyncedAt' => $this->normalizeDateValue($branch['lastSyncedAt'] ?? null),
                'metadata' => is_array($branch['metadata'] ?? null) ? $branch['metadata'] : [],
            ];
        }

        return $normalizedBranches;
    }

    private function normalizeDate(?DateTimeInterface $date): ?string
    {
        return $date?->format(DATE_ATOM);
    }

    private function normalizeDateValue(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_string($value)) {
            return $value;
        }

        return null;
    }
}
