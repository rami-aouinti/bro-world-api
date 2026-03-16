<?php

declare(strict_types=1);

namespace App\Tests\Application\Crm;

use App\Crm\Domain\Entity\TaskRequest;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use App\Crm\Infrastructure\Repository\CrmRepository;
use App\Crm\Infrastructure\Repository\ProjectRepository;
use App\Crm\Infrastructure\Repository\TaskRepository;
use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class CrmPrioritizedMatrixTest extends WebTestCase
{
    private const string PRIMARY_APPLICATION_SLUG = 'crm-sales-hub';
    private const string FOREIGN_APPLICATION_SLUG = 'crm-pipeline-pro';

    public function testPriorityP0CrossTenantScopeIsStrictOnCreateAndDelete(): void
    {
        $foreign = $this->getForeignScopeIds();
        $client = $this->getTestClient('john-root', 'password-root');

        $client->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/projects', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG),
            content: JSON::encode(['name' => 'Cross tenant matrix', 'companyId' => $foreign['companyId']])
        );
        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());

        $client->request(
            'DELETE',
            sprintf('%s/v1/crm/applications/%s/tasks/%s', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG, $foreign['taskId'])
        );
        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    public function testPriorityP0AclReadVsWriteEndpoints(): void
    {
        $readClient = $this->getTestClient('john-crm_viewer', 'password-crm_viewer');
        $readClient->request('GET', sprintf('%s/v1/crm/applications/%s/tasks', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG));
        self::assertSame(Response::HTTP_OK, $readClient->getResponse()->getStatusCode());

        $writeClient = $this->getTestClient('john-crm_viewer', 'password-crm_viewer');
        $writeClient->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/tasks', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG),
            content: JSON::encode(['title' => 'ACL matrix task', 'projectId' => '00000000-0000-0000-0000-000000000000'])
        );
        self::assertSame(Response::HTTP_FORBIDDEN, $writeClient->getResponse()->getStatusCode());

        $managerClient = $this->getTestClient('john-crm_manager', 'password-crm_manager');
        $managerClient->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/tasks', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG),
            content: JSON::encode(['title' => 'ACL matrix task', 'projectId' => '00000000-0000-0000-0000-000000000000'])
        );
        self::assertSame(Response::HTTP_NOT_FOUND, $managerClient->getResponse()->getStatusCode());
    }

    public function testPriorityP1DtoValidationMatrixOnCreateAndPatch(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        $client->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/companies', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG),
            content: '{"name":'
        );
        self::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

        $client->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/companies', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG),
            content: JSON::encode(['name' => ''])
        );
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());

        $client->request(
            'PATCH',
            sprintf('%s/v1/crm/applications/%s/companies/%s', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG, '00000000-0000-0000-0000-000000000000'),
            content: JSON::encode(['name' => 'Updated'])
        );
        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    public function testPriorityP1TaskListServicePaginationFilteringNonRegression(): void
    {
        $companyId = $this->createCompany();
        $projectId = $this->createProject($companyId);
        $taskTitle = 'P1 matrix ' . uniqid('', true);
        $taskId = $this->createTask($projectId, $taskTitle);

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request(
            'GET',
            sprintf('%s/v1/crm/applications/%s/tasks?page=1&limit=5&title=%s', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG, urlencode($taskTitle))
        );

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());

        self::assertCount(1, $payload['items']);
        self::assertSame($taskId, $payload['items'][0]['id'] ?? null);
        self::assertSame(1, $payload['pagination']['totalItems'] ?? null);
        self::assertSame(1, $payload['pagination']['totalPages'] ?? null);
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
                'name' => 'Matrix Company ' . uniqid('', true),
                'contactEmail' => 'matrix.company@example.com',
            ])
        );

        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());

        return (string) $payload['id'];
    }

    private function createProject(string $companyId): string
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $client->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/projects', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG),
            content: JSON::encode([
                'name' => 'Matrix Project ' . uniqid('', true),
                'companyId' => $companyId,
            ])
        );

        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());

        return (string) $payload['id'];
    }

    private function createTask(string $projectId, string $title): string
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $client->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/tasks', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG),
            content: JSON::encode([
                'title' => $title,
                'projectId' => $projectId,
            ])
        );

        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());

        return (string) $payload['id'];
    }

    /** @return array<string, mixed> */
    private function decodeJsonResponse(string|false $content): array
    {
        self::assertNotFalse($content);

        $decoded = JSON::decode($content, true);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
