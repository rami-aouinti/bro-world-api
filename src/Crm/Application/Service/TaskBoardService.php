<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

use App\Crm\Domain\Entity\Task;
use App\Crm\Domain\Entity\TaskRequest;
use App\Crm\Infrastructure\Repository\TaskRepository;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final readonly class TaskBoardService
{
    public function __construct(
        private TaskRepository $taskRepository,
        private EntityManagerInterface $entityManager,
        private CrmApplicationScopeResolver $applicationScopeResolver,
    ) {
    }

    /** @return array{items:list<array<string,mixed>>} */
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
            ->setParameter('crm', $crm)
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

    /** @return array{items:array{tasks:list<array<string,mixed>>,taskRequests:list<array<string,mixed>>}} */
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
            ->setParameter('crm', $crm)
            ->setParameter('user', $loggedInUser)
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

    /** @return array<string,mixed> */
    private function normalizeTask(Task $task): array
    {
        return [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'status' => $task->getStatus()->value,
            'priority' => $task->getPriority()->value,
            'sprintId' => $task->getSprint()?->getId(),
            'projectId' => $task->getProject()?->getId(),
            'assignees' => array_map(static fn (User $user): array => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
            ], $task->getAssignees()->toArray()),
            'children' => array_map(fn (TaskRequest $taskRequest): array => $this->normalizeTaskRequest($taskRequest), $task->getTaskRequests()->toArray()),
        ];
    }

    /** @return array<string,mixed> */
    private function normalizeTaskRequest(TaskRequest $taskRequest): array
    {
        return [
            'id' => $taskRequest->getId(),
            'taskId' => $taskRequest->getTask()?->getId(),
            'title' => $taskRequest->getTitle(),
            'status' => $taskRequest->getStatus()->value,
            'requestedAt' => $taskRequest->getRequestedAt()->format(DATE_ATOM),
            'resolvedAt' => $taskRequest->getResolvedAt()?->format(DATE_ATOM),
            'assignees' => array_map(static fn (User $user): array => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
            ], $taskRequest->getAssignees()->toArray()),
        ];
    }
}
