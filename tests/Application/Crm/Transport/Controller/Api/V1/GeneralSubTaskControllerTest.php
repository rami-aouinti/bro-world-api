<?php

declare(strict_types=1);

namespace App\Tests\Application\Crm\Transport\Controller\Api\V1;

use App\Crm\Infrastructure\Repository\CrmRepository;
use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class GeneralSubTaskControllerTest extends WebTestCase
{
    private const string PRIMARY_APPLICATION_SLUG = 'crm-sales-hub';

    public function testGeneralTaskCreateAndPatchSupportParentTaskId(): void
    {
        $companyId = $this->createGeneralCompany();
        $projectId = $this->createGeneralProject($companyId);
        $parentTaskId = $this->createGeneralTask($projectId, 'Parent task');

        $managerClient = $this->getTestClient('john-crm_manager', 'password-crm_manager');
        $managerClient->request(
            'POST',
            sprintf('%s/v1/crm/general/tasks', self::API_URL_PREFIX),
            content: JSON::encode([
                'projectId' => $projectId,
                'title' => 'Child task with parentTaskId',
                'parentTaskId' => $parentTaskId,
            ])
        );

        self::assertSame(Response::HTTP_CREATED, $managerClient->getResponse()->getStatusCode());
        $createdPayload = $this->decodeJsonResponse($managerClient->getResponse()->getContent());
        $childTaskId = (string) $createdPayload['id'];

        $managerClient->request('GET', sprintf('%s/v1/crm/general/tasks/%s', self::API_URL_PREFIX, $childTaskId));
        self::assertSame(Response::HTTP_OK, $managerClient->getResponse()->getStatusCode());
        $taskPayload = $this->decodeJsonResponse($managerClient->getResponse()->getContent());
        self::assertSame($parentTaskId, $taskPayload['parentTaskId'] ?? null);

        $managerClient->request(
            'PATCH',
            sprintf('%s/v1/crm/general/tasks/%s', self::API_URL_PREFIX, $childTaskId),
            content: JSON::encode([
                'parentTaskId' => null,
            ])
        );
        self::assertSame(Response::HTTP_OK, $managerClient->getResponse()->getStatusCode());

        $managerClient->request('GET', sprintf('%s/v1/crm/general/tasks/%s', self::API_URL_PREFIX, $childTaskId));
        self::assertSame(Response::HTTP_OK, $managerClient->getResponse()->getStatusCode());
        $patchedPayload = $this->decodeJsonResponse($managerClient->getResponse()->getContent());
        self::assertNull($patchedPayload['parentTaskId'] ?? null);
    }

    public function testDedicatedGeneralSubTaskEndpointsCrudAndPermissions(): void
    {
        $companyId = $this->createGeneralCompany();
        $projectId = $this->createGeneralProject($companyId);
        $parentTaskId = $this->createGeneralTask($projectId, 'Parent task for dedicated endpoints');
        $newParentTaskId = $this->createGeneralTask($projectId, 'Another parent');

        $viewerClient = $this->getTestClient('john-crm_viewer', 'password-crm_viewer');
        $viewerClient->request(
            'POST',
            sprintf('%s/v1/crm/general/tasks/%s/subtasks', self::API_URL_PREFIX, $parentTaskId),
            content: JSON::encode([
                'title' => 'Should fail for viewer',
            ])
        );
        self::assertSame(Response::HTTP_FORBIDDEN, $viewerClient->getResponse()->getStatusCode());

        $managerClient = $this->getTestClient('john-crm_manager', 'password-crm_manager');
        $managerClient->request(
            'POST',
            sprintf('%s/v1/crm/general/tasks/%s/subtasks', self::API_URL_PREFIX, $parentTaskId),
            content: JSON::encode([
                'title' => 'Dedicated subtask',
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $managerClient->getResponse()->getStatusCode());
        $subTaskPayload = $this->decodeJsonResponse($managerClient->getResponse()->getContent());
        $subTaskId = (string) $subTaskPayload['id'];

        $managerClient->request('GET', sprintf('%s/v1/crm/general/tasks/%s', self::API_URL_PREFIX, $parentTaskId));
        self::assertSame(Response::HTTP_OK, $managerClient->getResponse()->getStatusCode());
        $parentPayload = $this->decodeJsonResponse($managerClient->getResponse()->getContent());
        self::assertNotEmpty($parentPayload['subTasks'] ?? []);

        $managerClient->request(
            'PATCH',
            sprintf('%s/v1/crm/general/subtasks/%s', self::API_URL_PREFIX, $subTaskId),
            content: JSON::encode([
                'title' => 'Dedicated subtask updated',
                'parentTaskId' => $newParentTaskId,
            ])
        );
        self::assertSame(Response::HTTP_OK, $managerClient->getResponse()->getStatusCode());

        $managerClient->request('GET', sprintf('%s/v1/crm/general/tasks/%s', self::API_URL_PREFIX, $subTaskId));
        self::assertSame(Response::HTTP_OK, $managerClient->getResponse()->getStatusCode());
        $updatedSubTaskPayload = $this->decodeJsonResponse($managerClient->getResponse()->getContent());
        self::assertSame($newParentTaskId, $updatedSubTaskPayload['parentTaskId'] ?? null);
        self::assertSame('Dedicated subtask updated', $updatedSubTaskPayload['title'] ?? null);

        $managerClient->request(
            'DELETE',
            sprintf('%s/v1/crm/general/subtasks/%s', self::API_URL_PREFIX, $subTaskId)
        );
        self::assertSame(Response::HTTP_NO_CONTENT, $managerClient->getResponse()->getStatusCode());

        $managerClient->request('GET', sprintf('%s/v1/crm/general/tasks/%s', self::API_URL_PREFIX, $subTaskId));
        self::assertSame(Response::HTTP_NOT_FOUND, $managerClient->getResponse()->getStatusCode());
    }

    public function testDedicatedGeneralSubTaskAttachDetachEndpointsAndBusinessValidations(): void
    {
        $companyId = $this->createGeneralCompany();
        $projectId = $this->createGeneralProject($companyId);
        $rootTaskId = $this->createGeneralTask($projectId, 'Root task');
        $childTaskId = $this->createGeneralTask($projectId, 'Child task');
        $grandChildTaskId = $this->createGeneralTask($projectId, 'Grand child task');

        $secondCompanyId = $this->createGeneralCompany();
        $secondProjectId = $this->createGeneralProject($secondCompanyId);
        $otherProjectTaskId = $this->createGeneralTask($secondProjectId, 'Task from another project');

        $managerClient = $this->getTestClient('john-crm_manager', 'password-crm_manager');
        $managerClient->request('PUT', sprintf('%s/v1/crm/general/tasks/%s/subtasks/%s', self::API_URL_PREFIX, $rootTaskId, $childTaskId));
        self::assertSame(Response::HTTP_NO_CONTENT, $managerClient->getResponse()->getStatusCode());

        $managerClient->request('PUT', sprintf('%s/v1/crm/general/tasks/%s/subtasks/%s', self::API_URL_PREFIX, $childTaskId, $grandChildTaskId));
        self::assertSame(Response::HTTP_NO_CONTENT, $managerClient->getResponse()->getStatusCode());

        $managerClient->request('PUT', sprintf('%s/v1/crm/general/tasks/%s/subtasks/%s', self::API_URL_PREFIX, $grandChildTaskId, $rootTaskId));
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $managerClient->getResponse()->getStatusCode());

        $managerClient->request('PUT', sprintf('%s/v1/crm/general/tasks/%s/subtasks/%s', self::API_URL_PREFIX, $rootTaskId, $otherProjectTaskId));
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $managerClient->getResponse()->getStatusCode());

        $managerClient->request('DELETE', sprintf('%s/v1/crm/general/tasks/%s/subtasks/%s', self::API_URL_PREFIX, $rootTaskId, $childTaskId));
        self::assertSame(Response::HTTP_NO_CONTENT, $managerClient->getResponse()->getStatusCode());

        $managerClient->request('GET', sprintf('%s/v1/crm/general/tasks/%s', self::API_URL_PREFIX, $childTaskId));
        self::assertSame(Response::HTTP_OK, $managerClient->getResponse()->getStatusCode());
        $childPayload = $this->decodeJsonResponse($managerClient->getResponse()->getContent());
        self::assertNull($childPayload['parentTaskId'] ?? null);

        $managerClient->request('DELETE', sprintf('%s/v1/crm/general/tasks/%s/subtasks/%s', self::API_URL_PREFIX, $rootTaskId, $childTaskId));
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $managerClient->getResponse()->getStatusCode());
    }

    private function createGeneralCompany(): string
    {
        $client = $this->getTestClient('john-crm_manager', 'password-crm_manager');
        $client->request(
            'POST',
            sprintf('%s/v1/crm/general/companies', self::API_URL_PREFIX),
            content: JSON::encode([
                'crmId' => $this->getPrimaryCrmId(),
                'name' => 'General Subtask Company ' . uniqid('', true),
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());

        return (string) $payload['id'];
    }

    private function createGeneralProject(string $companyId): string
    {
        $client = $this->getTestClient('john-crm_manager', 'password-crm_manager');
        $client->request(
            'POST',
            sprintf('%s/v1/crm/general/projects', self::API_URL_PREFIX),
            content: JSON::encode([
                'companyId' => $companyId,
                'name' => 'General Subtask Project ' . uniqid('', true),
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());

        return (string) $payload['id'];
    }

    private function createGeneralTask(string $projectId, string $title): string
    {
        $client = $this->getTestClient('john-crm_manager', 'password-crm_manager');
        $client->request(
            'POST',
            sprintf('%s/v1/crm/general/tasks', self::API_URL_PREFIX),
            content: JSON::encode([
                'projectId' => $projectId,
                'title' => $title,
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());

        return (string) $payload['id'];
    }

    private function getPrimaryCrmId(): string
    {
        static::bootKernel();
        $crmRepository = static::getContainer()->get(CrmRepository::class);
        $crm = $crmRepository->findOneByApplicationSlug(self::PRIMARY_APPLICATION_SLUG);
        self::assertNotNull($crm);

        return $crm->getId();
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeJsonResponse(string|false $content): array
    {
        self::assertNotFalse($content);
        $decoded = JSON::decode($content, true);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
