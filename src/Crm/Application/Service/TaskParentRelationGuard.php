<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

use App\Crm\Domain\Entity\Task;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class TaskParentRelationGuard
{
    public function assertCanAssignParent(Task $task, Task $parentTask, string $projectErrorMessage): void
    {
        if ($parentTask->getId() === $task->getId()) {
            throw new HttpException(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'Task cannot be its own parent.');
        }

        if ($parentTask->getProject()?->getId() !== $task->getProject()?->getId()) {
            throw new HttpException(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, $projectErrorMessage);
        }

        if ($this->isDescendantOf($parentTask, $task)) {
            throw new HttpException(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'Circular parent relation is not allowed.');
        }
    }

    private function isDescendantOf(Task $candidate, Task $task): bool
    {
        $current = $candidate->getParentTask();
        while ($current !== null) {
            if ($current->getId() === $task->getId()) {
                return true;
            }

            $current = $current->getParentTask();
        }

        return false;
    }
}
