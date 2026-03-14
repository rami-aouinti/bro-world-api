<?php

declare(strict_types=1);

namespace App\Tests\Application\Crm\Transport\Controller\Api\V1;

use App\Crm\Domain\Entity\TaskRequest;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use App\Crm\Infrastructure\Repository\CrmRepository;
use App\Crm\Infrastructure\Repository\ProjectRepository;
use App\Crm\Infrastructure\Repository\TaskRepository;
use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;

final class CrmControllerTest extends WebTestCase
{
    private const string PRIMARY_APPLICATION_SLUG = 'crm-sales-hub';
    private const string FOREIGN_APPLICATION_SLUG = 'crm-pipeline-pro';

    #[TestDox('CreateProjectController rejects companyId from another CRM application scope.')]
    public function testCreateProjectRejectsForeignCompanyId(): void
    {
        $foreignIds = $this->getForeignScopeIds();

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/projects', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG),
            content: JSON::encode([
                'name' => 'Cross-scope project',
                'companyId' => $foreignIds['companyId'],
            ])
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    #[TestDox('CreateSprintController rejects projectId from another CRM application scope.')]
    public function testCreateSprintRejectsForeignProjectId(): void
    {
        $foreignIds = $this->getForeignScopeIds();

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/sprints', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG),
            content: JSON::encode([
                'name' => 'Cross-scope sprint',
                'projectId' => $foreignIds['projectId'],
            ])
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    #[TestDox('CreateTaskController rejects projectId from another CRM application scope.')]
    public function testCreateTaskRejectsForeignProjectId(): void
    {
        $foreignIds = $this->getForeignScopeIds();

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/tasks', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG),
            content: JSON::encode([
                'title' => 'Cross-scope task project',
                'projectId' => $foreignIds['projectId'],
            ])
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    #[TestDox('CreateTaskController rejects sprintId from another CRM application scope.')]
    public function testCreateTaskRejectsForeignSprintId(): void
    {
        $companyId = $this->createCompany();
        $projectId = $this->createProject($companyId);
        $foreignIds = $this->getForeignScopeIds();

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/tasks', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG),
            content: JSON::encode([
                'title' => 'Cross-scope task sprint',
                'projectId' => $projectId,
                'sprintId' => $foreignIds['sprintId'],
            ])
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    #[TestDox('CreateTaskRequestController rejects taskId from another CRM application scope.')]
    public function testCreateTaskRequestRejectsForeignTaskId(): void
    {
        $foreignIds = $this->getForeignScopeIds();

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/task-requests', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG),
            content: JSON::encode([
                'title' => 'Cross-scope task request',
                'taskId' => $foreignIds['taskId'],
            ])
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    #[TestDox('Delete endpoints reject IDs from another CRM application scope.')]
    #[DataProvider('crossScopeDeleteProvider')]
    public function testDeleteRejectsForeignScopeIds(string $resource, string $foreignIdKey): void
    {
        $foreignIds = $this->getForeignScopeIds();

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request(
            'DELETE',
            sprintf('%s/v1/crm/applications/%s/%s/%s', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG, $resource, $foreignIds[$foreignIdKey])
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function crossScopeDeleteProvider(): array
    {
        return [
            'company' => ['companies', 'companyId'],
            'project' => ['projects', 'projectId'],
            'sprint' => ['sprints', 'sprintId'],
            'task' => ['tasks', 'taskId'],
            'task-request' => ['task-requests', 'taskRequestId'],
        ];
    }

    /**
     * @return array{companyId:string,projectId:string,sprintId:string,taskId:string,taskRequestId:string}
     */
    private function getForeignScopeIds(): array
    {
        static::bootKernel();

        $container = static::getContainer();

        $crmRepository = $container->get(CrmRepository::class);
        $companyRepository = $container->get(CompanyRepository::class);
        $projectRepository = $container->get(ProjectRepository::class);
        $taskRepository = $container->get(TaskRepository::class);

        $crm = $crmRepository->findOneByApplicationSlug(self::FOREIGN_APPLICATION_SLUG);
        self::assertNotNull($crm);

        $company = $companyRepository->findScoped($crm->getId(), 1)[0] ?? null;
        self::assertNotNull($company);

        $project = $projectRepository->findScoped($crm->getId(), 1)[0] ?? null;
        self::assertNotNull($project);

        $task = $taskRepository->findScoped($crm->getId(), 1)[0] ?? null;
        self::assertNotNull($task);

        $sprint = $task->getSprint();
        self::assertNotNull($sprint);

        $taskRequest = $task->getTaskRequests()->first();
        self::assertInstanceOf(TaskRequest::class, $taskRequest);

        return [
            'companyId' => $company->getId(),
            'projectId' => $project->getId(),
            'sprintId' => $sprint->getId(),
            'taskId' => $task->getId(),
            'taskRequestId' => $taskRequest->getId(),
        ];
    }

    private function createCompany(): string
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $client->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/companies', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG),
            content: JSON::encode([
                'name' => 'Cross Scope Company ' . uniqid('', true),
                'contactEmail' => 'cross.scope.company@example.com',
            ])
        );

        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode(), "Response:\n" . $client->getResponse());
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());

        return (string)$payload['id'];
    }

    private function createProject(string $companyId): string
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $client->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/projects', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG),
            content: JSON::encode([
                'name' => 'Cross Scope Project ' . uniqid('', true),
                'companyId' => $companyId,
            ])
        );

        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode(), "Response:\n" . $client->getResponse());
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());

        return (string)$payload['id'];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonResponse(string|false $content): array
    {
        self::assertNotFalse($content);

        $decoded = JSON::decode($content, true);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
