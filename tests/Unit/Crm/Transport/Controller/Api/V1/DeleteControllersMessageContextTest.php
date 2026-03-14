<?php

declare(strict_types=1);

namespace App\Tests\Unit\Crm\Transport\Controller\Api\V1;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Entity\Company;
use App\Crm\Domain\Entity\Crm;
use App\Crm\Domain\Entity\Project;
use App\Crm\Domain\Entity\Sprint;
use App\Crm\Domain\Entity\Task;
use App\Crm\Domain\Entity\TaskRequest;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use App\Crm\Infrastructure\Repository\ProjectRepository;
use App\Crm\Infrastructure\Repository\SprintRepository;
use App\Crm\Infrastructure\Repository\TaskRepository;
use App\Crm\Infrastructure\Repository\TaskRequestRepository;
use App\Crm\Transport\Controller\Api\V1\Company\DeleteCompanyController;
use App\Crm\Transport\Controller\Api\V1\Project\DeleteProjectController;
use App\Crm\Transport\Controller\Api\V1\Sprint\DeleteSprintController;
use App\Crm\Transport\Controller\Api\V1\Task\DeleteTaskController;
use App\Crm\Transport\Controller\Api\V1\TaskRequest\DeleteTaskRequestController;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\General\Application\Message\EntityDeleted;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;

final class DeleteControllersMessageContextTest extends TestCase
{
    public function testDeleteCompanyDispatchesContext(): void
    {
        $crm = new Crm();
        $company = new Company();
        $company->setCrm($crm);

        $scopeResolver = $this->createMock(CrmApplicationScopeResolver::class);
        $scopeResolver->method('resolveOrFail')->with('crm-sales-hub')->willReturn($crm);

        $repository = $this->createMock(CompanyRepository::class);
        $repository->method('findOneScopedById')->willReturn($company);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($company);
        $entityManager->expects(self::once())->method('flush');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->with(self::callback(function (mixed $message) use ($crm): bool {
            return $message instanceof EntityDeleted
                && $message->entityType === 'crm_company'
                && ($message->context['applicationSlug'] ?? null) === 'crm-sales-hub'
                && ($message->context['crmId'] ?? null) === $crm->getId();
        }));

        $controller = new DeleteCompanyController(
            $repository,
            $scopeResolver,
            $this->createMock(CrmApiErrorResponseFactory::class),
            $entityManager,
            $bus,
        );

        $controller->__invoke('crm-sales-hub', $company->getId());
    }

    public function testDeleteProjectDispatchesContext(): void
    {
        $crm = new Crm();
        $project = new Project();

        $scopeResolver = $this->createMock(CrmApplicationScopeResolver::class);
        $scopeResolver->method('resolveOrFail')->willReturn($crm);

        $repository = $this->createMock(ProjectRepository::class);
        $repository->method('findOneScopedById')->willReturn($project);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($project);
        $entityManager->expects(self::once())->method('flush');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->with(self::callback(function (mixed $message) use ($crm): bool {
            return $message instanceof EntityDeleted
                && $message->entityType === 'crm_project'
                && ($message->context['applicationSlug'] ?? null) === 'crm-sales-hub'
                && ($message->context['crmId'] ?? null) === $crm->getId();
        }));

        $controller = new DeleteProjectController(
            $repository,
            $scopeResolver,
            $this->createMock(CrmApiErrorResponseFactory::class),
            $entityManager,
            $bus,
        );

        $controller->__invoke('crm-sales-hub', $project->getId());
    }

    public function testDeleteSprintDispatchesContext(): void
    {
        $crm = new Crm();
        $sprint = new Sprint();

        $scopeResolver = $this->createMock(CrmApplicationScopeResolver::class);
        $scopeResolver->method('resolveOrFail')->willReturn($crm);

        $repository = $this->createMock(SprintRepository::class);
        $repository->method('findOneScopedById')->willReturn($sprint);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($sprint);
        $entityManager->expects(self::once())->method('flush');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->with(self::callback(function (mixed $message) use ($crm): bool {
            return $message instanceof EntityDeleted
                && $message->entityType === 'crm_sprint'
                && ($message->context['applicationSlug'] ?? null) === 'crm-sales-hub'
                && ($message->context['crmId'] ?? null) === $crm->getId();
        }));

        $controller = new DeleteSprintController(
            $repository,
            $scopeResolver,
            $this->createMock(CrmApiErrorResponseFactory::class),
            $entityManager,
            $bus,
        );

        $controller->__invoke('crm-sales-hub', $sprint->getId());
    }

    public function testDeleteTaskDispatchesContext(): void
    {
        $crm = new Crm();
        $task = new Task();

        $scopeResolver = $this->createMock(CrmApplicationScopeResolver::class);
        $scopeResolver->method('resolveOrFail')->willReturn($crm);

        $repository = $this->createMock(TaskRepository::class);
        $repository->method('findOneScopedById')->willReturn($task);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($task);
        $entityManager->expects(self::once())->method('flush');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->with(self::callback(function (mixed $message) use ($crm): bool {
            return $message instanceof EntityDeleted
                && $message->entityType === 'crm_task'
                && ($message->context['applicationSlug'] ?? null) === 'crm-sales-hub'
                && ($message->context['crmId'] ?? null) === $crm->getId();
        }));

        $controller = new DeleteTaskController(
            $repository,
            $scopeResolver,
            $this->createMock(CrmApiErrorResponseFactory::class),
            $entityManager,
            $bus,
        );

        $controller->__invoke('crm-sales-hub', $task->getId());
    }

    public function testDeleteTaskRequestDispatchesContext(): void
    {
        $crm = new Crm();
        $taskRequest = new TaskRequest();

        $scopeResolver = $this->createMock(CrmApplicationScopeResolver::class);
        $scopeResolver->method('resolveOrFail')->willReturn($crm);

        $repository = $this->createMock(TaskRequestRepository::class);
        $repository->method('findOneScopedById')->willReturn($taskRequest);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($taskRequest);
        $entityManager->expects(self::once())->method('flush');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->with(self::callback(function (mixed $message) use ($crm): bool {
            return $message instanceof EntityDeleted
                && $message->entityType === 'crm_task_request'
                && ($message->context['applicationSlug'] ?? null) === 'crm-sales-hub'
                && ($message->context['crmId'] ?? null) === $crm->getId();
        }));

        $controller = new DeleteTaskRequestController(
            $repository,
            $scopeResolver,
            $this->createMock(CrmApiErrorResponseFactory::class),
            $entityManager,
            $bus,
        );

        $controller->__invoke('crm-sales-hub', $taskRequest->getId());
    }
}
