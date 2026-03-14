<?php

declare(strict_types=1);

namespace App\Tests\Application\Crm\Transport\Controller\Api\V1;

use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;

final class CrmEndpointsSmokeTest extends WebTestCase
{
    private const string APPLICATION_SLUG = 'crm-sales-hub';
    private const string UNKNOWN_UUID = '00000000-0000-0000-0000-000000000000';

    #[TestDox('GET list CRM endpoints return 200 and items/pagination/meta structure.')]
    #[DataProvider('listEndpointsProvider')]
    public function testListEndpointsPayloadStructure(string $resource): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        $client->request('GET', sprintf('%s/v1/crm/applications/%s/%s?page=1&limit=5', self::API_URL_PREFIX, self::APPLICATION_SLUG, $resource));
        $response = $client->getResponse();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $payload = $this->decodeJsonResponse($response->getContent());
        self::assertArrayHasKey('items', $payload);
        self::assertArrayHasKey('pagination', $payload);
        self::assertArrayHasKey('meta', $payload);
    }

    #[TestDox('GET list CRM endpoints return 404 for unknown application slug.')]
    #[DataProvider('listEndpointsProvider')]
    public function testListEndpointsErrorCase(string $resource): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        $client->request('GET', sprintf('%s/v1/crm/applications/not-found-slug/%s', self::API_URL_PREFIX, $resource));

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());
        self::assertArrayHasKey('message', $payload);
        self::assertSame([], $payload['errors'] ?? null);
    }

    #[TestDox('POST create endpoints return 400 for invalid JSON payload.')]
    #[DataProvider('createEndpointsProvider')]
    public function testCreateEndpointsInvalidJson(string $resource): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        $client->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/%s', self::API_URL_PREFIX, self::APPLICATION_SLUG, $resource),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"invalid":'
        );

        self::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());
        self::assertSame('Invalid JSON payload.', $payload['message'] ?? null);
        self::assertSame([], $payload['errors'] ?? null);
    }

    #[TestDox('POST date-based endpoints return 400 for invalid date format.')]
    #[DataProvider('invalidDateProvider')]
    public function testCreateEndpointsInvalidDate(string $resource, string $titleOrNameField, string $dateField): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        $payload = [$titleOrNameField => 'Invalid date payload'];
        if ($resource === 'sprints') {
            $companyId = $this->createCompany();
            $payload['projectId'] = $this->createProject($companyId);
        }

        $payload[$dateField] = '2024-01-01';

        $client->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/%s', self::API_URL_PREFIX, self::APPLICATION_SLUG, $resource),
            content: JSON::encode($payload)
        );

        self::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        $decoded = $this->decodeJsonResponse($client->getResponse()->getContent());
        self::assertSame(sprintf('Invalid date format for "%s".', $dateField), $decoded['message'] ?? null);
        self::assertSame([], $decoded['errors'] ?? null);
    }

    #[TestDox('POST /companies returns 201 and then DELETE /companies/{id} returns 204.')]
    public function testCompanyCreateAndDelete(): void
    {
        $companyId = $this->createCompany();
        $this->assertDeleteSucceeds('companies', $companyId);
    }

    #[TestDox('POST /companies returns error for invalid payload.')]
    public function testCompanyCreateErrorCase(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('POST', sprintf('%s/v1/crm/applications/%s/companies', self::API_URL_PREFIX, self::APPLICATION_SLUG), content: JSON::encode(['name' => '']));

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    #[TestDox('DELETE /companies/{id} returns 404 for unknown entity.')]
    public function testCompanyDeleteErrorCase(): void
    {
        $this->assertDeleteNotFound('companies');
    }

    #[TestDox('POST /projects returns 201 and then DELETE /projects/{id} returns 204.')]
    public function testProjectCreateAndDelete(): void
    {
        $companyId = $this->createCompany();
        $projectId = $this->createProject($companyId);

        $this->assertDeleteSucceeds('projects', $projectId);
        $this->assertDeleteSucceeds('companies', $companyId);
    }

    #[TestDox('POST /projects returns error for invalid reference.')]
    public function testProjectCreateErrorCase(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $client->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/projects', self::API_URL_PREFIX, self::APPLICATION_SLUG),
            content: JSON::encode([
                'name' => 'Broken project',
                'companyId' => self::UNKNOWN_UUID,
            ])
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());
        self::assertArrayHasKey('message', $payload);
        self::assertSame([], $payload['errors'] ?? null);
    }

    #[TestDox('DELETE /projects/{id} returns 404 for unknown entity.')]
    public function testProjectDeleteErrorCase(): void
    {
        $this->assertDeleteNotFound('projects');
    }

    #[TestDox('POST /sprints returns 201 and then DELETE /sprints/{id} returns 204.')]
    public function testSprintCreateAndDelete(): void
    {
        $companyId = $this->createCompany();
        $projectId = $this->createProject($companyId);
        $sprintId = $this->createSprint($projectId);

        $this->assertDeleteSucceeds('sprints', $sprintId);
        $this->assertDeleteSucceeds('projects', $projectId);
        $this->assertDeleteSucceeds('companies', $companyId);
    }

    #[TestDox('POST /sprints returns error for invalid reference.')]
    public function testSprintCreateErrorCase(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $client->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/sprints', self::API_URL_PREFIX, self::APPLICATION_SLUG),
            content: JSON::encode([
                'name' => 'Broken sprint',
                'projectId' => self::UNKNOWN_UUID,
            ])
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());
        self::assertArrayHasKey('message', $payload);
        self::assertSame([], $payload['errors'] ?? null);
    }

    #[TestDox('DELETE /sprints/{id} returns 404 for unknown entity.')]
    public function testSprintDeleteErrorCase(): void
    {
        $this->assertDeleteNotFound('sprints');
    }

    #[TestDox('POST /tasks returns 201 and then DELETE /tasks/{id} returns 204.')]
    public function testTaskCreateAndDelete(): void
    {
        $companyId = $this->createCompany();
        $projectId = $this->createProject($companyId);
        $taskId = $this->createTask($projectId);

        $this->assertDeleteSucceeds('tasks', $taskId);
        $this->assertDeleteSucceeds('projects', $projectId);
        $this->assertDeleteSucceeds('companies', $companyId);
    }

    #[TestDox('POST /tasks returns error for invalid reference.')]
    public function testTaskCreateErrorCase(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $client->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/tasks', self::API_URL_PREFIX, self::APPLICATION_SLUG),
            content: JSON::encode([
                'title' => 'Broken task',
                'projectId' => self::UNKNOWN_UUID,
            ])
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());
        self::assertArrayHasKey('message', $payload);
        self::assertSame([], $payload['errors'] ?? null);
    }

    #[TestDox('DELETE /tasks/{id} returns 404 for unknown entity.')]
    public function testTaskDeleteErrorCase(): void
    {
        $this->assertDeleteNotFound('tasks');
    }

    #[TestDox('POST /task-requests returns 201 and then DELETE /task-requests/{id} returns 204.')]
    public function testTaskRequestCreateAndDelete(): void
    {
        $companyId = $this->createCompany();
        $projectId = $this->createProject($companyId);
        $taskId = $this->createTask($projectId);
        $taskRequestId = $this->createTaskRequest($taskId);

        $this->assertDeleteSucceeds('task-requests', $taskRequestId);
        $this->assertDeleteSucceeds('tasks', $taskId);
        $this->assertDeleteSucceeds('projects', $projectId);
        $this->assertDeleteSucceeds('companies', $companyId);
    }

    #[TestDox('POST /task-requests returns error for invalid reference.')]
    public function testTaskRequestCreateErrorCase(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $client->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/task-requests', self::API_URL_PREFIX, self::APPLICATION_SLUG),
            content: JSON::encode([
                'title' => 'Broken task request',
                'taskId' => self::UNKNOWN_UUID,
            ])
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());
        self::assertArrayHasKey('message', $payload);
        self::assertSame([], $payload['errors'] ?? null);
    }

    #[TestDox('DELETE /task-requests/{id} returns 404 for unknown entity.')]
    public function testTaskRequestDeleteErrorCase(): void
    {
        $this->assertDeleteNotFound('task-requests');
    }

    #[TestDox('GET /dashboard returns 200 and expected keys.')]
    public function testDashboardSuccess(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('GET', sprintf('%s/v1/crm/applications/%s/dashboard', self::API_URL_PREFIX, self::APPLICATION_SLUG));

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());

        self::assertArrayHasKey('companies', $payload);
        self::assertArrayHasKey('projects', $payload);
        self::assertArrayHasKey('tasks', $payload);
        self::assertArrayHasKey('taskRequests', $payload);
    }

    #[TestDox('GET /dashboard returns 404 for unknown application slug.')]
    public function testDashboardErrorCase(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('GET', self::API_URL_PREFIX . '/v1/crm/applications/not-found-slug/dashboard');

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());
        self::assertArrayHasKey('message', $payload);
        self::assertSame([], $payload['errors'] ?? null);
    }


    #[TestDox('Project and sprint list payloads expose "name" instead of legacy "title".')]
    public function testProjectAndSprintListUseNameField(): void
    {
        $companyId = $this->createCompany();
        $projectId = $this->createProject($companyId);
        $sprintId = $this->createSprint($projectId);

        $client = $this->getTestClient('john-root', 'password-root');

        $client->request('GET', sprintf('%s/v1/crm/applications/%s/projects?page=1&limit=100', self::API_URL_PREFIX, self::APPLICATION_SLUG));
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $projectsPayload = $this->decodeJsonResponse($client->getResponse()->getContent());
        $projectItem = $this->findItemById($projectsPayload['items'] ?? [], $projectId);
        self::assertNotNull($projectItem);
        self::assertArrayHasKey('name', $projectItem);
        self::assertArrayNotHasKey('title', $projectItem);

        $client->request('GET', sprintf('%s/v1/crm/applications/%s/sprints?page=1&limit=100', self::API_URL_PREFIX, self::APPLICATION_SLUG));
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $sprintsPayload = $this->decodeJsonResponse($client->getResponse()->getContent());
        $sprintItem = $this->findItemById($sprintsPayload['items'] ?? [], $sprintId);
        self::assertNotNull($sprintItem);
        self::assertArrayHasKey('name', $sprintItem);
        self::assertArrayNotHasKey('title', $sprintItem);

        $this->assertDeleteSucceeds('sprints', $sprintId);
        $this->assertDeleteSucceeds('projects', $projectId);
        $this->assertDeleteSucceeds('companies', $companyId);
    }

    #[TestDox('Tasks by sprint board payload exposes sprint.name instead of sprint.title.')]
    public function testTasksBySprintBoardUsesSprintNameField(): void
    {
        $companyId = $this->createCompany();
        $projectId = $this->createProject($companyId);
        $sprintId = $this->createSprint($projectId);

        $taskClient = $this->getTestClient('john-root', 'password-root');
        $taskClient->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/tasks', self::API_URL_PREFIX, self::APPLICATION_SLUG),
            content: JSON::encode([
                'title' => 'Board task ' . uniqid('', true),
                'projectId' => $projectId,
                'sprintId' => $sprintId,
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $taskClient->getResponse()->getStatusCode());
        $taskPayload = $this->decodeJsonResponse($taskClient->getResponse()->getContent());
        $taskId = (string)($taskPayload['id'] ?? '');
        self::assertNotSame('', $taskId);

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('GET', sprintf('%s/v1/crm/applications/%s/tasks/by-sprint', self::API_URL_PREFIX, self::APPLICATION_SLUG));
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());
        self::assertIsArray($payload['items'] ?? null);

        $group = null;
        foreach ($payload['items'] as $item) {
            if (($item['sprint']['id'] ?? null) === $sprintId) {
                $group = $item;
                break;
            }
        }

        self::assertIsArray($group);
        self::assertArrayHasKey('sprint', $group);
        self::assertArrayHasKey('name', $group['sprint']);
        self::assertArrayNotHasKey('title', $group['sprint']);

        $this->assertDeleteSucceeds('tasks', $taskId);
        $this->assertDeleteSucceeds('sprints', $sprintId);
        $this->assertDeleteSucceeds('projects', $projectId);
        $this->assertDeleteSucceeds('companies', $companyId);
    }

    #[TestDox('OpenAPI documentation exposes name for CRM projects/sprints and by-sprint board schema example.')]
    public function testOpenApiShowsNameForProjectSprintAndBoardPayloads(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('GET', '/api/doc.json');
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());
        $paths = $payload['paths'] ?? [];

        self::assertSame('Projects list with normalized name field.', $paths['/v1/crm/applications/{applicationSlug}/projects']['get']['responses']['200']['description'] ?? null);
        self::assertSame('Sprints list with normalized name field.', $paths['/v1/crm/applications/{applicationSlug}/sprints']['get']['responses']['200']['description'] ?? null);
        self::assertSame('Board payload grouped by sprint with sprint.name.', $paths['/v1/crm/applications/{applicationSlug}/tasks/by-sprint']['get']['responses']['200']['description'] ?? null);
    }

    /**
     * @return array<int, array{string}>
     */
    public static function createEndpointsProvider(): array
    {
        return [
            ['companies'],
            ['projects'],
            ['sprints'],
            ['tasks'],
            ['task-requests'],
        ];
    }

    /**
     * @return array<int, array{string,string,string}>
     */
    public static function invalidDateProvider(): array
    {
        return [
            ['projects', 'name', 'startedAt'],
            ['sprints', 'name', 'startDate'],
            ['tasks', 'title', 'dueAt'],
            ['task-requests', 'title', 'resolvedAt'],
        ];
    }

    /**
     * @return array<int, array{string}>
     */
    public static function listEndpointsProvider(): array
    {
        return [
            ['companies'],
            ['projects'],
            ['sprints'],
            ['tasks'],
            ['task-requests'],
        ];
    }

    private function createCompany(): string
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $client->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/companies', self::API_URL_PREFIX, self::APPLICATION_SLUG),
            content: JSON::encode([
                'name' => 'Smoke Company ' . uniqid('', true),
                'contactEmail' => 'smoke.company@example.com',
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
            sprintf('%s/v1/crm/applications/%s/projects', self::API_URL_PREFIX, self::APPLICATION_SLUG),
            content: JSON::encode([
                'name' => 'Smoke Project ' . uniqid('', true),
                'companyId' => $companyId,
            ])
        );

        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode(), "Response:\n" . $client->getResponse());
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());

        return (string)$payload['id'];
    }

    private function createSprint(string $projectId): string
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $client->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/sprints', self::API_URL_PREFIX, self::APPLICATION_SLUG),
            content: JSON::encode([
                'name' => 'Smoke Sprint ' . uniqid('', true),
                'projectId' => $projectId,
            ])
        );

        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode(), "Response:\n" . $client->getResponse());
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());

        return (string)$payload['id'];
    }

    private function createTask(string $projectId): string
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $client->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/tasks', self::API_URL_PREFIX, self::APPLICATION_SLUG),
            content: JSON::encode([
                'title' => 'Smoke Task ' . uniqid('', true),
                'projectId' => $projectId,
            ])
        );

        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode(), "Response:\n" . $client->getResponse());
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());

        return (string)$payload['id'];
    }

    private function createTaskRequest(string $taskId): string
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $client->request(
            'POST',
            sprintf('%s/v1/crm/applications/%s/task-requests', self::API_URL_PREFIX, self::APPLICATION_SLUG),
            content: JSON::encode([
                'title' => 'Smoke Task Request ' . uniqid('', true),
                'taskId' => $taskId,
            ])
        );

        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode(), "Response:\n" . $client->getResponse());
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());

        return (string)$payload['id'];
    }

    private function assertDeleteSucceeds(string $resource, string $id): void
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('DELETE', sprintf('%s/v1/crm/applications/%s/%s/%s', self::API_URL_PREFIX, self::APPLICATION_SLUG, $resource, $id));

        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
    }

    private function assertDeleteNotFound(string $resource): void
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('DELETE', sprintf('%s/v1/crm/applications/%s/%s/%s', self::API_URL_PREFIX, self::APPLICATION_SLUG, $resource, self::UNKNOWN_UUID));

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());
        self::assertArrayHasKey('message', $payload);
        self::assertSame([], $payload['errors'] ?? null);
    }

    /**
     * @param list<array<string,mixed>> $items
     * @return array<string,mixed>|null
     */
    private function findItemById(array $items, string $id): ?array
    {
        foreach ($items as $item) {
            if (($item['id'] ?? null) === $id) {
                return $item;
            }
        }

        return null;
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
