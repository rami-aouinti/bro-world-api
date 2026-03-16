<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

use App\Crm\Application\Exception\CrmOutOfScopeException;
use App\Crm\Application\Exception\CrmReferenceNotFoundException;
use App\Crm\Domain\Entity\Task;
use App\Crm\Domain\Enum\TaskPriority;
use App\Crm\Domain\Enum\TaskStatus;
use App\Crm\Infrastructure\Repository\ProjectRepository;
use App\Crm\Infrastructure\Repository\SprintRepository;
use App\Crm\Transport\Request\CreateTaskRequest;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use App\User\Domain\Entity\User;

final readonly class CreateTaskHandler
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private SprintRepository $sprintRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function handle(CreateTaskRequest $input, string $crmId, ?DateTimeImmutable $dueAt): Task
    {
        $task = new Task();
        $task->setTitle((string) $input->title)
            ->setDescription($input->description)
            ->setStatus(TaskStatus::tryFrom((string) $input->status) ?? TaskStatus::TODO)
            ->setPriority(TaskPriority::tryFrom((string) $input->priority) ?? TaskPriority::MEDIUM)
            ->setDueAt($dueAt)
            ->setEstimatedHours($input->estimatedHours !== null ? (float) $input->estimatedHours : null);

        $project = null;
        if (is_string($input->projectId)) {
            $project = $this->projectRepository->findOneScopedById($input->projectId, $crmId);
            if ($project === null) {
                throw new CrmReferenceNotFoundException('projectId');
            }

            $task->setProject($project);
        }

        if (is_string($input->sprintId)) {
            $sprint = $this->sprintRepository->findOneScopedById($input->sprintId, $crmId);
            if ($sprint === null) {
                throw new CrmReferenceNotFoundException('sprintId');
            }

            if ($project !== null && $sprint->getProject()?->getId() !== $project->getId()) {
                throw new CrmOutOfScopeException('Provided "sprintId" does not belong to the provided "projectId".');
            }

            $task->setSprint($sprint);
        }

        if (is_array($input->assigneeIds)) {
            foreach ($input->assigneeIds as $assigneeId) {
                $assignee = $this->entityManager->getRepository(User::class)->find($assigneeId);
                if (!$assignee instanceof User) {
                    throw new CrmReferenceNotFoundException('assigneeIds');
                }

                $task->addAssignee($assignee);
            }
        }

        return $task;
    }
}
