<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

use App\Crm\Domain\Enum\TaskRequestStatus;

use function in_array;
use function is_array;
use function strtolower;
use function trim;

final class CrmTaskRequestGithubStatusMapper
{
    /**
     * @param array<string,mixed> $issuePayload
     */
    public function resolveTaskRequestStatusFromIssuePayload(array $issuePayload): TaskRequestStatus
    {
        $state = strtolower(trim((string)($issuePayload['state'] ?? 'open')));
        if ($state === 'open') {
            return TaskRequestStatus::PENDING;
        }

        return $this->isRejectedIssue($issuePayload)
            ? TaskRequestStatus::REJECTED
            : TaskRequestStatus::APPROVED;
    }

    public function toGithubIssueState(TaskRequestStatus $status): string
    {
        return $status === TaskRequestStatus::PENDING ? 'open' : 'closed';
    }

    /**
     * @param array<string,mixed> $issuePayload
     */
    private function isRejectedIssue(array $issuePayload): bool
    {
        $stateReason = strtolower(trim((string)($issuePayload['state_reason'] ?? '')));
        if ($stateReason === 'not_planned' || $stateReason === 'rejected') {
            return true;
        }

        $labels = $issuePayload['labels'] ?? null;
        if (!is_array($labels)) {
            return false;
        }

        foreach ($labels as $label) {
            if (!is_array($label)) {
                continue;
            }

            $name = strtolower(trim((string)($label['name'] ?? '')));
            if (in_array($name, ['rejected', 'reject', 'declined', 'crm:rejected'], true)) {
                return true;
            }
        }

        return false;
    }
}
