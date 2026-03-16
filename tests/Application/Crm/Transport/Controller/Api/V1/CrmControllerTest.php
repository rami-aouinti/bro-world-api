<?php

declare(strict_types=1);

namespace App\Tests\Application\Crm\Transport\Controller\Api\V1;

use App\Crm\Application\Message\CreateBillingCommand;
use App\Crm\Application\Message\CreateContactCommand;
use App\Crm\Application\Message\CreateEmployeeCommand;
use App\Crm\Domain\Entity\TaskRequest;
use App\Crm\Infrastructure\Repository\BillingRepository;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use App\Crm\Infrastructure\Repository\ContactRepository;
use App\Crm\Infrastructure\Repository\CrmRepository;
use App\Crm\Infrastructure\Repository\EmployeeRepository;
use App\Crm\Infrastructure\Repository\ProjectRepository;
use App\Crm\Infrastructure\Repository\TaskRepository;
use App\General\Application\Message\EntityCreated;
use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class CrmControllerTest extends WebTestCase
{
    private const string PRIMARY_APPLICATION_SLUG = 'crm-sales-hub';
    private const string FOREIGN_APPLICATION_SLUG = 'crm-pipeline-pro';

    #[TestDox('ROLE_ADMIN is forbidden from CRM endpoints when no CRM role is assigned.')]
    public function testGlobalAdminCannotAccessCrmListWithoutCrmRole(): void
    {
        $client = $this->getTestClient('john-admin', 'password-admin');
        $client->request('GET', sprintf('%s/v1/crm/applications/%s/companies', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG));

        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
    }

    #[TestDox('ROLE_CRM_ADMIN can access and manage CRM resources without global ROLE_ADMIN.')]
    public function testCrmAdminCanAccessAndManageCrmResources(): void
    {
        $viewerClient = $this->getTestClient('john-crm_admin', 'password-crm_admin');
        $viewerClient->request('GET', sprintf('%s/v1/crm/applications/%s/companies', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG));
        self::assertSame(Response::HTTP_OK, $viewerClient->getResponse()->getStatusCode());

        $companyId = $this->createCompany();
        $managerClient = $this->getTestClient('john-crm_admin', 'password-crm_admin');
        $managerClient->request(
            'DELETE',
            sprintf('%s/v1/crm/applications/%s/companies/%s', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG, $companyId)
        );

        self::assertSame(Response::HTTP_NO_CONTENT, $managerClient->getResponse()->getStatusCode());
    }

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
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());
        self::assertArrayHasKey('message', $payload);
        self::assertSame([], $payload['errors'] ?? null);
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
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());
        self::assertArrayHasKey('message', $payload);
        self::assertSame([], $payload['errors'] ?? null);
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
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());
        self::assertArrayHasKey('message', $payload);
        self::assertSame([], $payload['errors'] ?? null);
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
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());
        self::assertArrayHasKey('message', $payload);
        self::assertSame([], $payload['errors'] ?? null);
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
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());
        self::assertArrayHasKey('message', $payload);
        self::assertSame([], $payload['errors'] ?? null);
    }

    #[TestDox('CreateTaskController returns 422 for sprint/project mismatch in same CRM scope.')]
    public function testCreateTaskRejectsSprintProjectMismatch(): void
    {
        $companyId = $this->createCompany();
        $projectId = $this->createProject($companyId);

        $otherCompanyId = $this->createCompany();
        $otherProjectId = $this->createProject($otherCompanyId);
        $sprintId = $this->createSprint($otherProjectId);

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/tasks', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG),
            content: JSON::encode([
                'title' => 'Task with mismatched sprint/project',
                'projectId' => $projectId,
                'sprintId' => $sprintId,
            ])
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());
        self::assertSame('Provided "sprintId" does not belong to the provided "projectId".', $payload['message'] ?? null);
        self::assertSame([], $payload['errors'] ?? null);
    }

    #[TestDox('CreateCompanyByApplicationController publishes EntityCreated with applicationSlug and crmId context.')]
    public function testCreateCompanyPublishesMessageContext(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async_priority_high');
        $transport->reset();

        $client->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/companies', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG),
            content: JSON::encode([
                'name' => 'Context Company ' . uniqid('', true),
                'contactEmail' => 'context.company@example.com',
            ])
        );

        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode(), "Response:\n" . $client->getResponse());

        $message = $this->getLastCreatedMessage($transport);
        self::assertSame(self::PRIMARY_APPLICATION_SLUG, $message->context['applicationSlug'] ?? null);
        self::assertSame($this->getCrmIdByApplicationSlug(self::PRIMARY_APPLICATION_SLUG), $message->context['crmId'] ?? null);
    }

    #[TestDox('CreateProjectController publishes EntityCreated with applicationSlug and crmId context.')]
    public function testCreateProjectPublishesMessageContext(): void
    {
        $companyId = $this->createCompany();
        $client = $this->getTestClient('john-root', 'password-root');

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async_priority_high');
        $transport->reset();

        $client->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/projects', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG),
            content: JSON::encode([
                'name' => 'Context Project ' . uniqid('', true),
                'companyId' => $companyId,
            ])
        );

        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode(), "Response:\n" . $client->getResponse());

        $message = $this->getLastCreatedMessage($transport);
        self::assertSame(self::PRIMARY_APPLICATION_SLUG, $message->context['applicationSlug'] ?? null);
        self::assertSame($this->getCrmIdByApplicationSlug(self::PRIMARY_APPLICATION_SLUG), $message->context['crmId'] ?? null);
    }

    #[TestDox('CreateTaskController publishes EntityCreated with applicationSlug and crmId context.')]
    public function testCreateTaskPublishesMessageContext(): void
    {
        $companyId = $this->createCompany();
        $projectId = $this->createProject($companyId);
        $sprintId = $this->createSprint($projectId);
        $client = $this->getTestClient('john-root', 'password-root');

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async_priority_high');
        $transport->reset();

        $client->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/tasks', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG),
            content: JSON::encode([
                'title' => 'Context Task ' . uniqid('', true),
                'projectId' => $projectId,
                'sprintId' => $sprintId,
            ])
        );

        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode(), "Response:\n" . $client->getResponse());

        $message = $this->getLastCreatedMessage($transport);
        self::assertSame(self::PRIMARY_APPLICATION_SLUG, $message->context['applicationSlug'] ?? null);
        self::assertSame($this->getCrmIdByApplicationSlug(self::PRIMARY_APPLICATION_SLUG), $message->context['crmId'] ?? null);
    }

    #[TestDox('CreateTaskRequestController publishes EntityCreated with applicationSlug and crmId context.')]
    public function testCreateTaskRequestPublishesMessageContext(): void
    {
        $companyId = $this->createCompany();
        $projectId = $this->createProject($companyId);
        $sprintId = $this->createSprint($projectId);
        $taskId = $this->createTask($projectId, $sprintId);
        $client = $this->getTestClient('john-root', 'password-root');

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async_priority_high');
        $transport->reset();

        $client->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/task-requests', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG),
            content: JSON::encode([
                'title' => 'Context Task Request ' . uniqid('', true),
                'taskId' => $taskId,
            ])
        );

        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode(), "Response:\n" . $client->getResponse());

        $message = $this->getLastCreatedMessage($transport);
        self::assertSame(self::PRIMARY_APPLICATION_SLUG, $message->context['applicationSlug'] ?? null);
        self::assertSame($this->getCrmIdByApplicationSlug(self::PRIMARY_APPLICATION_SLUG), $message->context['crmId'] ?? null);
    }

    #[TestDox('CreateBillingController dispatches CreateBillingCommand and does not flush in controller.')]
    public function testCreateBillingDispatchesCommandWithoutImmediatePersistence(): void
    {
        $companyId = $this->createCompany();
        $client = $this->getTestClient('john-root', 'password-root');

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async_priority_high');
        $transport->reset();

        $client->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/billings', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG),
            content: JSON::encode([
                'companyId' => $companyId,
                'label' => 'Billing ' . uniqid('', true),
                'amount' => 42.5,
                'currency' => 'EUR',
            ])
        );

        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode(), "Response:\n" . $client->getResponse());
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());

        self::assertArrayHasKey('id', $payload);
        self::assertSame($companyId, $payload['companyId'] ?? null);

        $message = $this->getLastDispatchedMessage($transport);
        self::assertInstanceOf(CreateBillingCommand::class, $message);
        self::assertSame(self::PRIMARY_APPLICATION_SLUG, $message->applicationSlug);
        self::assertSame($this->getCrmIdByApplicationSlug(self::PRIMARY_APPLICATION_SLUG), $message->crmId);

        $billingRepository = static::getContainer()->get(BillingRepository::class);
        self::assertNull($billingRepository->find($payload['id']));
    }

    #[TestDox('CreateContactController dispatches CreateContactCommand and does not flush in controller.')]
    public function testCreateContactDispatchesCommandWithoutImmediatePersistence(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async_priority_high');
        $transport->reset();

        $client->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/contacts', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG),
            content: JSON::encode([
                'firstName' => 'Test',
                'lastName' => 'Contact',
                'email' => 'test.contact@example.com',
            ])
        );

        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode(), "Response:\n" . $client->getResponse());
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());

        $message = $this->getLastDispatchedMessage($transport);
        self::assertInstanceOf(CreateContactCommand::class, $message);
        self::assertSame(self::PRIMARY_APPLICATION_SLUG, $message->applicationSlug);
        self::assertSame($this->getCrmIdByApplicationSlug(self::PRIMARY_APPLICATION_SLUG), $message->crmId);

        $contactRepository = static::getContainer()->get(ContactRepository::class);
        self::assertNull($contactRepository->find($payload['id']));
    }

    #[TestDox('CreateEmployeeController dispatches CreateEmployeeCommand and does not flush in controller.')]
    public function testCreateEmployeeDispatchesCommandWithoutImmediatePersistence(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async_priority_high');
        $transport->reset();

        $client->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/employees', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG),
            content: JSON::encode([
                'firstName' => 'Test',
                'lastName' => 'Employee',
                'email' => 'test.employee@example.com',
            ])
        );

        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode(), "Response:\n" . $client->getResponse());
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());

        $message = $this->getLastDispatchedMessage($transport);
        self::assertInstanceOf(CreateEmployeeCommand::class, $message);
        self::assertSame(self::PRIMARY_APPLICATION_SLUG, $message->applicationSlug);
        self::assertSame($this->getCrmIdByApplicationSlug(self::PRIMARY_APPLICATION_SLUG), $message->crmId);

        $employeeRepository = static::getContainer()->get(EmployeeRepository::class);
        self::assertNull($employeeRepository->find($payload['id']));
    }

    #[TestDox('ListEmployeesController returns a paginated employee list scoped by CRM application.')]
    public function testListEmployeesReturnsPaginatedList(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        $client->request(
            'GET',
            sprintf('%s/v1/crm/applications/%s/employees?page=1&limit=5', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG)
        );

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), "Response:\n" . $client->getResponse());
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());

        self::assertArrayHasKey('items', $payload);
        self::assertArrayHasKey('pagination', $payload);
        self::assertSame(1, $payload['pagination']['page'] ?? null);
        self::assertSame(5, $payload['pagination']['limit'] ?? null);
        self::assertIsInt($payload['pagination']['totalItems'] ?? null);
        self::assertIsInt($payload['pagination']['totalPages'] ?? null);

        if (($payload['items'][0] ?? null) !== null) {
            self::assertArrayHasKey('id', $payload['items'][0]);
            self::assertArrayHasKey('firstName', $payload['items'][0]);
            self::assertArrayHasKey('lastName', $payload['items'][0]);
            self::assertArrayHasKey('email', $payload['items'][0]);
        }
    }


    #[TestDox('CreateTaskController returns standardized date parsing error payload.')]
    public function testCreateTaskInvalidDateUsesStandardizedError(): void
    {
        $companyId = $this->createCompany();
        $projectId = $this->createProject($companyId);

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/tasks', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG),
            content: JSON::encode([
                'title' => 'Task invalid dueAt',
                'projectId' => $projectId,
                'dueAt' => 'invalid-date',
            ])
        );

        self::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());
        self::assertSame('Invalid date format for "dueAt".', $payload['message'] ?? null);
        self::assertSame([], $payload['errors'] ?? null);
    }

    #[TestDox('PatchTaskController returns standardized date parsing error payload.')]
    public function testPatchTaskInvalidDateUsesStandardizedError(): void
    {
        $companyId = $this->createCompany();
        $projectId = $this->createProject($companyId);
        $sprintId = $this->createSprint($projectId);
        $taskId = $this->createTask($projectId, $sprintId);

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request(
            'PATCH',
            sprintf('%s/v1/crm/applications/%s/tasks/%s', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG, $taskId),
            content: JSON::encode([
                'dueAt' => 'still-not-a-date',
            ])
        );

        self::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());
        self::assertSame('Invalid date format for "dueAt".', $payload['message'] ?? null);
        self::assertSame([], $payload['errors'] ?? null);
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
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());
        self::assertArrayHasKey('message', $payload);
        self::assertSame([], $payload['errors'] ?? null);
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

    private function createTask(string $projectId, string $sprintId): string
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $client->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/tasks', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG),
            content: JSON::encode([
                'title' => 'Cross Scope Task ' . uniqid('', true),
                'projectId' => $projectId,
                'sprintId' => $sprintId,
            ])
        );

        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode(), "Response:\n" . $client->getResponse());
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());

        return (string)$payload['id'];
    }

    private function getCrmIdByApplicationSlug(string $applicationSlug): string
    {
        static::bootKernel();

        $crmRepository = static::getContainer()->get(CrmRepository::class);
        $crm = $crmRepository->findOneByApplicationSlug($applicationSlug);

        self::assertNotNull($crm);

        return $crm->getId();
    }

    private function getLastCreatedMessage(InMemoryTransport $transport): EntityCreated
    {
        $envelopes = $transport->getSent();
        self::assertNotEmpty($envelopes);

        $message = $envelopes[array_key_last($envelopes)]->getMessage();
        self::assertInstanceOf(EntityCreated::class, $message);

        return $message;
    }

    private function getLastDispatchedMessage(InMemoryTransport $transport): object
    {
        $envelopes = $transport->getSent();
        self::assertNotEmpty($envelopes);

        return $envelopes[array_key_last($envelopes)]->getMessage();
    }

    private function createSprint(string $projectId): string
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $client->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/sprints', self::API_URL_PREFIX, self::PRIMARY_APPLICATION_SLUG),
            content: JSON::encode([
                'name' => 'Cross Scope Sprint ' . uniqid('', true),
                'projectId' => $projectId,
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
