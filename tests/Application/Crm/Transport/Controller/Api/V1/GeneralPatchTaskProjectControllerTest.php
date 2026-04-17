<?php

declare(strict_types=1);

namespace App\Tests\Application\Crm\Transport\Controller\Api\V1;

use App\Crm\Infrastructure\Repository\CrmRepository;
use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class GeneralPatchTaskProjectControllerTest extends WebTestCase
{
    private const string PRIMARY_APPLICATION_SLUG = 'crm-sales-hub';
    private const string UNKNOWN_UUID = '00000000-0000-0000-0000-000000000000';

    public function testPatchGeneralTaskCanReassignProjectAndClearMismatchedSprint(): void
    {
        $companyId = $this->createGeneralCompany();
        $projectAId = $this->createGeneralProject($companyId);
        $projectBId = $this->createGeneralProject($companyId);
        $sprintAId = $this->createGeneralSprint($projectAId);
        $taskId = $this->createGeneralTask($projectAId, $sprintAId);

        $managerClient = $this->getTestClient('john-crm_manager', 'password-crm_manager');
        $managerClient->request(
            'PATCH',
            sprintf('%s/v1/crm/general/tasks/%s', self::API_URL_PREFIX, $taskId),
            content: JSON::encode([
                'projectId' => $projectBId,
            ])
        );
        self::assertSame(Response::HTTP_OK, $managerClient->getResponse()->getStatusCode());

        $managerClient->request('GET', sprintf('%s/v1/crm/general/tasks/%s', self::API_URL_PREFIX, $taskId));
        self::assertSame(Response::HTTP_OK, $managerClient->getResponse()->getStatusCode());

        $payload = $this->decodeJsonResponse($managerClient->getResponse()->getContent());
        self::assertSame($projectBId, $payload['projectId'] ?? null);
        self::assertNull($payload['sprintId'] ?? null);
    }

    public function testPatchGeneralTaskReturns404WhenProjectDoesNotExist(): void
    {
        $companyId = $this->createGeneralCompany();
        $projectId = $this->createGeneralProject($companyId);
        $taskId = $this->createGeneralTask($projectId);

        $managerClient = $this->getTestClient('john-crm_manager', 'password-crm_manager');
        $managerClient->request(
            'PATCH',
            sprintf('%s/v1/crm/general/tasks/%s', self::API_URL_PREFIX, $taskId),
            content: JSON::encode([
                'projectId' => self::UNKNOWN_UUID,
            ])
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $managerClient->getResponse()->getStatusCode());
        $payload = $this->decodeJsonResponse($managerClient->getResponse()->getContent());
        self::assertSame('Project not found.', $payload['message'] ?? null);
    }

    private function createGeneralCompany(): string
    {
        $client = $this->getTestClient('john-crm_manager', 'password-crm_manager');
        $client->request(
            'POST',
            sprintf('%s/v1/crm/general/companies', self::API_URL_PREFIX),
            content: JSON::encode([
                'crmId' => $this->getPrimaryCrmId(),
                'name' => 'General Patch Task Project Company ' . uniqid('', true),
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
                'name' => 'General Patch Task Project ' . uniqid('', true),
            ])
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        $payload = $this->decodeJsonResponse($client->getResponse()->getContent());

        return (string) $payload['id'];
    }

    private function createGeneralTask(string $projectId, ?string $sprintId = null): string
    {
        $payload = [
            'projectId' => $projectId,
            'title' => 'General Patch Task Project Task ' . uniqid('', true),
        ];

        if ($sprintId !== null) {
            $payload['sprintId'] = $sprintId;
        }

        $client = $this->getTestClient('john-crm_manager', 'password-crm_manager');
        $client->request(
            'POST',
            sprintf('%s/v1/crm/general/tasks', self::API_URL_PREFIX),
            content: JSON::encode($payload)
        );
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        $responsePayload = $this->decodeJsonResponse($client->getResponse()->getContent());

        return (string) $responsePayload['id'];
    }

    private function createGeneralSprint(string $projectId): string
    {
        $client = $this->getTestClient('john-crm_manager', 'password-crm_manager');
        $client->request(
            'POST',
            sprintf('%s/v1/crm/general/sprints', self::API_URL_PREFIX),
            content: JSON::encode([
                'projectId' => $projectId,
                'name' => 'General Patch Task Project Sprint ' . uniqid('', true),
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
