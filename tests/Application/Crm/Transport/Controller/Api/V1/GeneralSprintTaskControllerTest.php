<?php

declare(strict_types=1);

namespace App\Tests\Application\Crm\Transport\Controller\Api\V1;

use App\Crm\Infrastructure\Repository\CrmRepository;
use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class GeneralSprintTaskControllerTest extends WebTestCase
{
    private const string PRIMARY_APPLICATION_SLUG = 'crm-sales-hub';
    private const string UNKNOWN_UUID = '00000000-0000-0000-0000-000000000000';

    public function testGeneralSprintTaskEndpointsSuccess(): void
    {
        $companyId = $this->createGeneralCompany();
        $projectId = $this->createGeneralProject($companyId);
        $taskId = $this->createGeneralTask($projectId);
        $sprintId = $this->createGeneralSprint($projectId);

        $managerClient = $this->getTestClient('john-crm_manager', 'password-crm_manager');

        $managerClient->request('PUT', sprintf('%s/v1/crm/general/sprints/%s/tasks/%s', self::API_URL_PREFIX, $sprintId, $taskId));
        self::assertSame(Response::HTTP_NO_CONTENT, $managerClient->getResponse()->getStatusCode());

        $managerClient->request('GET', sprintf('%s/v1/crm/general/tasks/%s', self::API_URL_PREFIX, $taskId));
        self::assertSame(Response::HTTP_OK, $managerClient->getResponse()->getStatusCode());
        $payload = $this->decodeJsonResponse($managerClient->getResponse()->getContent());
        self::assertSame($sprintId, $payload['sprint']['id'] ?? null);

        $managerClient->request('DELETE', sprintf('%s/v1/crm/general/sprints/%s/tasks/%s', self::API_URL_PREFIX, $sprintId, $taskId));
        self::assertSame(Response::HTTP_NO_CONTENT, $managerClient->getResponse()->getStatusCode());

        $managerClient->request('GET', sprintf('%s/v1/crm/general/tasks/%s', self::API_URL_PREFIX, $taskId));
        self::assertSame(Response::HTTP_OK, $managerClient->getResponse()->getStatusCode());
        $payload = $this->decodeJsonResponse($managerClient->getResponse()->getContent());
        self::assertNull($payload['sprint']);
    }

    public function testGeneralSprintTaskEndpointsNotFound(): void
    {
        $companyId = $this->createGeneralCompany();
        $projectId = $this->createGeneralProject($companyId);
        $taskId = $this->createGeneralTask($projectId);
        $sprintId = $this->createGeneralSprint($projectId);

        $managerClient = $this->getTestClient('john-crm_manager', 'password-crm_manager');

        foreach ([
            sprintf('%s/v1/crm/general/sprints/%s/tasks/%s', self::API_URL_PREFIX, self::UNKNOWN_UUID, $taskId),
            sprintf('%s/v1/crm/general/sprints/%s/tasks/%s', self::API_URL_PREFIX, $sprintId, self::UNKNOWN_UUID),
        ] as $path) {
            $managerClient->request('PUT', $path);
            self::assertSame(Response::HTTP_NOT_FOUND, $managerClient->getResponse()->getStatusCode());

            $managerClient->request('DELETE', $path);
            self::assertSame(Response::HTTP_NOT_FOUND, $managerClient->getResponse()->getStatusCode());
        }
    }

    public function testGeneralSprintTaskEndpointsMismatchProject(): void
    {
        $companyId = $this->createGeneralCompany();
        $projectAId = $this->createGeneralProject($companyId);
        $projectBId = $this->createGeneralProject($companyId);
        $taskId = $this->createGeneralTask($projectAId);
        $sprintId = $this->createGeneralSprint($projectBId);

        $managerClient = $this->getTestClient('john-crm_manager', 'password-crm_manager');

        foreach (['PUT', 'DELETE'] as $method) {
            $managerClient->request($method, sprintf('%s/v1/crm/general/sprints/%s/tasks/%s', self::API_URL_PREFIX, $sprintId, $taskId));
            self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $managerClient->getResponse()->getStatusCode());
            $payload = $this->decodeJsonResponse($managerClient->getResponse()->getContent());
            self::assertSame('Task and sprint must belong to the same project.', $payload['message'] ?? null);
        }
    }

    private function createGeneralCompany(): string
    {
        $client = $this->getTestClient('john-crm_manager', 'password-crm_manager');
        $client->request(
            'POST',
            sprintf('%s/v1/crm/general/companies', self::API_URL_PREFIX),
            content: JSON::encode([
                'crmId' => $this->getPrimaryCrmId(),
                'name' => 'General Sprint Task Company ' . uniqid('', true),
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
                'name' => 'General Sprint Task Project ' . uniqid('', true),
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());

        return (string) $payload['id'];
    }

    private function createGeneralTask(string $projectId): string
    {
        $client = $this->getTestClient('john-crm_manager', 'password-crm_manager');
        $client->request(
            'POST',
            sprintf('%s/v1/crm/general/tasks', self::API_URL_PREFIX),
            content: JSON::encode([
                'projectId' => $projectId,
                'title' => 'General Sprint Task Task ' . uniqid('', true),
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());

        return (string) $payload['id'];
    }

    private function createGeneralSprint(string $projectId): string
    {
        $client = $this->getTestClient('john-crm_manager', 'password-crm_manager');
        $client->request(
            'POST',
            sprintf('%s/v1/crm/general/sprints', self::API_URL_PREFIX),
            content: JSON::encode([
                'projectId' => $projectId,
                'name' => 'General Sprint Task Sprint ' . uniqid('', true),
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
