<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

use App\Crm\Domain\Entity\Task;
use App\Crm\Domain\Entity\TaskRequest;
use App\Crm\Infrastructure\Repository\TaskRepository;
use App\User\Domain\Entity\User;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;

final readonly class TaskBoardService
{
    public function __construct(
        private TaskRepository $taskRepository,
        private CrmApplicationScopeResolver $applicationScopeResolver,
        private CrmApiNormalizer $crmApiNormalizer,
    ) {
    }

    /**
     * @return array{items:list<array<string,mixed>>}
     */
    public function listBySprint(string $applicationSlug): array
    {
        $crm = $this->applicationScopeResolver->resolveOrFail($applicationSlug);

        /** @var list<Task> $tasks */
        $tasks = $this->taskRepository->createQueryBuilder('task')
            ->leftJoin('task.sprint', 'sprint')->addSelect('sprint')
            ->leftJoin('task.taskRequests', 'taskRequest')->addSelect('taskRequest')
            ->leftJoin('task.assignees', 'taskAssignee')->addSelect('taskAssignee')
            ->leftJoin('taskRequest.assignees', 'taskRequestAssignee')->addSelect('taskRequestAssignee')
            ->leftJoin('task.project', 'project')
            ->leftJoin('project.company', 'company')
            ->andWhere('company.crm = :crm')
            ->setParameter('crm', $crm->getId(), UuidBinaryOrderedTimeType::NAME)
            ->orderBy('sprint.createdAt', 'DESC')
            ->addOrderBy('task.createdAt', 'DESC')
            ->addOrderBy('taskRequest.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $items = [];
        foreach ($tasks as $task) {
            $sprintId = $task->getSprint()?->getId() ?? 'no-sprint';
            if (!isset($items[$sprintId])) {
                $items[$sprintId] = [
                    'sprint' => [
                        'id' => $task->getSprint()?->getId(),
                        'name' => $task->getSprint()?->getName(),
                        'status' => $task->getSprint()?->getStatus()->value,
                    ],
                    'tasks' => [],
                ];
            }

            $items[$sprintId]['tasks'][] = $this->normalizeTask($task);
        }

        return [
            'items' => array_values($items),
        ];
    }

    /**
     * @return array{items:array{tasks:list<array<string,mixed>>,taskRequests:list<array<string,mixed>>}}
     */
    public function listMine(string $applicationSlug, User $loggedInUser): array
    {
        $crm = $this->applicationScopeResolver->resolveOrFail($applicationSlug);

        /** @var list<Task> $tasks */
        $tasks = $this->taskRepository->createQueryBuilder('task')
            ->leftJoin('task.assignees', 'taskAssignee')->addSelect('taskAssignee')
            ->leftJoin('task.taskRequests', 'taskRequest')->addSelect('taskRequest')
            ->leftJoin('taskRequest.assignees', 'taskRequestAssignee')->addSelect('taskRequestAssignee')
            ->leftJoin('task.project', 'project')
            ->leftJoin('project.company', 'company')
            ->andWhere('company.crm = :crm')
            ->andWhere('taskAssignee = :user OR taskRequestAssignee = :user')
            ->setParameter('crm', $crm->getId(), UuidBinaryOrderedTimeType::NAME)
            ->setParameter('user', $loggedInUser->getId(), UuidBinaryOrderedTimeType::NAME)
            ->orderBy('task.createdAt', 'DESC')
            ->addOrderBy('taskRequest.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $taskItems = [];
        $taskRequestItems = [];
        foreach ($tasks as $task) {
            if ($task->getAssignees()->contains($loggedInUser)) {
                $taskItems[$task->getId()] = $this->normalizeTask($task);
            }

            foreach ($task->getTaskRequests() as $taskRequest) {
                if (!$taskRequest->getAssignees()->contains($loggedInUser)) {
                    continue;
                }

                $taskRequestItems[$taskRequest->getId()] = $this->normalizeTaskRequest($taskRequest);
            }
        }

        return [
            'items' => [
                'tasks' => array_values($taskItems),
                'taskRequests' => array_values($taskRequestItems),
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function normalizeTask(Task $task): array
    {
        return $this->crmApiNormalizer->normalizeTask($task);
    }

    /**
     * @return array<string,mixed>
     */
    private function normalizeTaskRequest(TaskRequest $taskRequest): array
    {
        return $this->crmApiNormalizer->normalizeTaskRequest($taskRequest);
    }
}
