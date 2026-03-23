<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

use App\Crm\Domain\Enum\TaskPriority;
use App\Crm\Domain\Enum\TaskRequestStatus;
use App\Crm\Domain\Enum\TaskStatus;
use DateTimeImmutable;

use function in_array;
use function is_array;
use function is_string;
use function strtolower;
use function trim;

final class IssueToCrmMapper
{
    /**
     * @param array<string,mixed> $issue
     * @return array{title:string,description:?string,status:TaskStatus,priority:TaskPriority}
     */
    public function mapIssueToTask(array $issue): array
    {
        return [
            'title' => (string)($issue['title'] ?? 'GitHub issue'),
            'description' => isset($issue['body']) ? (string)$issue['body'] : null,
            'status' => $this->mapIssueToTaskStatus((string)($issue['state'] ?? 'open')),
            'priority' => $this->estimateTaskPriority($issue),
        ];
    }

    /**
     * @param array<string,mixed> $issue
     * @return array{title:string,description:?string,status:TaskRequestStatus,resolvedAt:?DateTimeImmutable}
     */
    public function mapIssueToTaskRequest(array $issue): array
    {
        $status = $this->resolveTaskRequestStatus($issue);

        return [
            'title' => (string)($issue['title'] ?? 'GitHub issue'),
            'description' => isset($issue['body']) ? (string)$issue['body'] : null,
            'status' => $status,
            'resolvedAt' => $status === TaskRequestStatus::PENDING ? null : new DateTimeImmutable(),
        ];
    }

    public function mapIssueToTaskStatus(string $state): TaskStatus
    {
        return strtolower(trim($state)) === 'closed' ? TaskStatus::DONE : TaskStatus::TODO;
    }

    /**
     * open -> pending ; closed -> approved or rejected.
     *
     * @param array<string,mixed> $issue
     */
    public function resolveTaskRequestStatus(array $issue): TaskRequestStatus
    {
        $state = strtolower(trim((string)($issue['state'] ?? 'open')));
        if ($state === 'open') {
            return TaskRequestStatus::PENDING;
        }

        return $this->isRejectedIssue($issue)
            ? TaskRequestStatus::REJECTED
            : TaskRequestStatus::APPROVED;
    }

    /**
     * @param array<string,mixed> $issue
     */
    private function estimateTaskPriority(array $issue): TaskPriority
    {
        $labels = $issue['labels'] ?? null;
        if (!is_array($labels)) {
            return TaskPriority::MEDIUM;
        }

        foreach ($labels as $label) {
            $name = $this->extractLabelName($label);
            if ($name === null) {
                continue;
            }

            if (in_array($name, ['priority:critical', 'p0', 'sev0', 'critical', 'blocker'], true)) {
                return TaskPriority::CRITICAL;
            }

            if (in_array($name, ['priority:high', 'p1', 'sev1', 'high'], true)) {
                return TaskPriority::HIGH;
            }

            if (in_array($name, ['priority:low', 'p3', 'low'], true)) {
                return TaskPriority::LOW;
            }
        }

        return TaskPriority::MEDIUM;
    }

    /**
     * @param array<string,mixed> $issue
     */
    private function isRejectedIssue(array $issue): bool
    {
        $stateReason = strtolower(trim((string)($issue['stateReason'] ?? $issue['state_reason'] ?? '')));
        if ($stateReason === 'not_planned' || $stateReason === 'rejected') {
            return true;
        }

        $labels = $issue['labels'] ?? null;
        if (!is_array($labels)) {
            return false;
        }

        foreach ($labels as $label) {
            $name = $this->extractLabelName($label);
            if ($name === null) {
                continue;
            }

            if (in_array($name, ['rejected', 'reject', 'declined', 'crm:rejected'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $label
     */
    private function extractLabelName(mixed $label): ?string
    {
        if (is_string($label)) {
            $name = strtolower(trim($label));

            return $name !== '' ? $name : null;
        }

        if (!is_array($label)) {
            return null;
        }

        $name = strtolower(trim((string)($label['name'] ?? '')));

        return $name !== '' ? $name : null;
    }
}
