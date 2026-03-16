<?php

declare(strict_types=1);

namespace App\Tests\Unit\Crm\Application\Service;

use App\Crm\Application\Exception\CrmOutOfScopeException;
use App\Crm\Application\Exception\CrmReferenceNotFoundException;
use App\Crm\Application\Service\CreateTaskHandler;
use App\Crm\Domain\Entity\Project;
use App\Crm\Domain\Entity\Sprint;
use App\Crm\Infrastructure\Repository\ProjectRepository;
use App\Crm\Infrastructure\Repository\SprintRepository;
use App\Crm\Transport\Request\CreateTaskRequest;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;

final class CreateTaskHandlerTest extends TestCase
{
    public function testHandleThrowsWhenProjectNotFoundInScope(): void
    {
        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->method('findOneScopedById')->willReturn(null);

        $handler = new CreateTaskHandler($projectRepository, $this->createMock(SprintRepository::class), $this->createMock(EntityManagerInterface::class));
        $request = CreateTaskRequest::fromArray(['title' => 'task', 'projectId' => 'p-1']);

        $this->expectException(CrmReferenceNotFoundException::class);
        $handler->handle($request, 'crm-1', null);
    }

    public function testHandleThrowsWhenSprintNotFoundInScope(): void
    {
        $project = $this->createMock(Project::class);
        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->method('findOneScopedById')->willReturn($project);

        $sprintRepository = $this->createMock(SprintRepository::class);
        $sprintRepository->method('findOneScopedById')->willReturn(null);

        $handler = new CreateTaskHandler($projectRepository, $sprintRepository, $this->createMock(EntityManagerInterface::class));
        $request = CreateTaskRequest::fromArray(['title' => 'task', 'projectId' => 'p-1', 'sprintId' => 's-1']);

        $this->expectException(CrmReferenceNotFoundException::class);
        $handler->handle($request, 'crm-1', null);
    }

    public function testHandleThrowsWhenSprintDoesNotBelongToProject(): void
    {
        $project = $this->createMock(Project::class);
        $project->method('getId')->willReturn('project-a');

        $otherProject = $this->createMock(Project::class);
        $otherProject->method('getId')->willReturn('project-b');

        $sprint = $this->createMock(Sprint::class);
        $sprint->method('getProject')->willReturn($otherProject);

        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->method('findOneScopedById')->willReturn($project);

        $sprintRepository = $this->createMock(SprintRepository::class);
        $sprintRepository->method('findOneScopedById')->willReturn($sprint);

        $handler = new CreateTaskHandler($projectRepository, $sprintRepository, $this->createMock(EntityManagerInterface::class));
        $request = CreateTaskRequest::fromArray(['title' => 'task', 'projectId' => 'p-1', 'sprintId' => 's-1']);

        $this->expectException(CrmOutOfScopeException::class);
        $handler->handle($request, 'crm-1', null);
    }

    public function testHandleThrowsWhenAssigneeIsUnknown(): void
    {
        $project = $this->createMock(Project::class);

        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->method('findOneScopedById')->willReturn($project);

        $userRepository = $this->createMock(ObjectRepository::class);
        $userRepository->method('find')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->with(User::class)->willReturn($userRepository);

        $handler = new CreateTaskHandler($projectRepository, $this->createMock(SprintRepository::class), $entityManager);
        $request = CreateTaskRequest::fromArray(['title' => 'task', 'projectId' => 'p-1', 'assigneeIds' => ['u-1']]);

        $this->expectException(CrmReferenceNotFoundException::class);
        $handler->handle($request, 'crm-1', null);
    }
}
