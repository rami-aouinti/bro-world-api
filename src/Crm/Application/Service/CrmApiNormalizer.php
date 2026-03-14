<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

use App\Crm\Domain\Entity\Task;
use App\Crm\Domain\Entity\TaskRequest;
use App\User\Domain\Entity\User;
use DateTimeInterface;

final class CrmApiNormalizer
{
    /**
     * @return array<string,mixed>
     */
    public function normalizeTask(Task $task): array
    {
        $assignees = [];
        foreach ($task->getAssignees() as $assignee) {
            if (!$assignee instanceof User) {
                continue;
            }

            $assignees[] = $this->normalizeAssignee($assignee);
        }

        $children = [];
        foreach ($task->getTaskRequests() as $taskRequest) {
            if (!$taskRequest instanceof TaskRequest) {
                continue;
            }

            $children[] = $this->normalizeTaskRequest($taskRequest);
        }

        return [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'status' => $task->getStatus()->value,
            'priority' => $task->getPriority()->value,
            'projectId' => $task->getProject()?->getId(),
            'projectName' => $task->getProject()?->getName(),
            'sprintId' => $task->getSprint()?->getId(),
            'sprintName' => $task->getSprint()?->getName(),
            'dueAt' => $this->normalizeDate($task->getDueAt()),
            'estimatedHours' => $task->getEstimatedHours(),
            'updatedAt' => $this->normalizeDate($task->getUpdatedAt()),
            'assignees' => $assignees,
            'children' => $children,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function normalizeTaskRequest(TaskRequest $taskRequest): array
    {
        $assignees = [];
        foreach ($taskRequest->getAssignees() as $assignee) {
            if (!$assignee instanceof User) {
                continue;
            }

            $assignees[] = $this->normalizeAssignee($assignee);
        }

        return [
            'id' => $taskRequest->getId(),
            'taskId' => $taskRequest->getTask()?->getId(),
            'title' => $taskRequest->getTitle(),
            'status' => $taskRequest->getStatus()->value,
            'requestedAt' => $this->normalizeDate($taskRequest->getRequestedAt()),
            'resolvedAt' => $this->normalizeDate($taskRequest->getResolvedAt()),
            'assignees' => $assignees,
        ];
    }

    /** @param array<string,mixed> $item
     * @return array<string,mixed>
     */
    public function normalizeSprintProjection(array $item): array
    {
        return [
            'id' => (string)($item['id'] ?? ''),
            'title' => (string)($item['name'] ?? ''),
            'status' => (string)($item['status'] ?? ''),
            'projectId' => $item['projectId'] ?? null,
            'startDate' => $this->normalizeDateValue($item['startDate'] ?? null),
            'endDate' => $this->normalizeDateValue($item['endDate'] ?? null),
        ];
    }

    /** @param array<string,mixed> $item
     * @return array<string,mixed>
     */
    public function normalizeProjectProjection(array $item): array
    {
        return [
            'id' => (string)($item['id'] ?? ''),
            'title' => (string)($item['name'] ?? ''),
            'status' => (string)($item['status'] ?? ''),
            'companyId' => $item['companyId'] ?? null,
        ];
    }

    /** @param array<string,mixed> $item
     * @return array<string,mixed>
     */
    public function normalizeTaskRequestProjection(array $item): array
    {
        $assignees = [];
        foreach ((array)($item['assignees'] ?? []) as $assignee) {
            if (!is_array($assignee)) {
                continue;
            }

            $assignees[] = [
                'id' => $assignee['id'] ?? null,
                'username' => $assignee['username'] ?? null,
                'firstName' => $assignee['firstName'] ?? null,
                'lastName' => $assignee['lastName'] ?? null,
                'photo' => $assignee['photo'] ?? null,
            ];
        }

        return [
            'id' => (string)($item['id'] ?? ''),
            'taskId' => $item['taskId'] ?? null,
            'title' => (string)($item['title'] ?? ''),
            'status' => (string)($item['status'] ?? ''),
            'requestedAt' => $this->normalizeDateValue($item['requestedAt'] ?? null),
            'resolvedAt' => $this->normalizeDateValue($item['resolvedAt'] ?? null),
            'assignees' => $assignees,
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
