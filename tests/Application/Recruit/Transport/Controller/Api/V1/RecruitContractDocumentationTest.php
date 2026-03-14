<?php

declare(strict_types=1);

namespace App\Tests\Application\Recruit\Transport\Controller\Api\V1;

use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function array_key_exists;

class RecruitContractDocumentationTest extends WebTestCase
{
    /**
     * @throws Throwable
     */
    #[TestDox('Recruit interview and pipeline endpoints are documented with expected methods and contract blocks.')]
    public function testRecruitEndpointsOpenApiContract(): void
    {
        $client = $this->getTestClient();
        $client->request('GET', '/api/doc.json');

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        /** @var array<string,mixed> $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        /** @var array<string,mixed> $paths */
        $paths = $payload['paths'] ?? [];

        $this->assertPathMethodContract($paths, '/v1/recruit/private/applications/{applicationId}/interviews', 'post', true, true);
        $this->assertPathMethodContract($paths, '/v1/recruit/private/applications/{applicationId}/interviews', 'get', false, true);
        $this->assertPathMethodContract($paths, '/v1/recruit/private/interviews/{interviewId}', 'patch', true, true);
        $this->assertPathMethodContract($paths, '/v1/recruit/private/interviews/{interviewId}', 'delete', false, true);
        $this->assertPathMethodContract($paths, '/v1/recruit/applications/{applicationSlug}/private/applications/{applicationId}/status', 'patch', true, true);
        $this->assertPathMethodContract($paths, '/v1/recruit/applications/{applicationSlug}/private/applications/{applicationId}/status-history', 'get', false, true);
    }

    /**
     * @param array<string,mixed> $paths
     */
    private function assertPathMethodContract(array $paths, string $path, string $method, bool $expectsRequestBody, bool $expectsResponses): void
    {
        self::assertArrayHasKey($path, $paths, 'Missing documented path: ' . $path);
        self::assertIsArray($paths[$path]);
        self::assertArrayHasKey($method, $paths[$path], 'Missing documented method: ' . strtoupper($method) . ' ' . $path);
        self::assertIsArray($paths[$path][$method]);

        /** @var array<string,mixed> $operation */
        $operation = $paths[$path][$method];

        if ($expectsRequestBody) {
            self::assertArrayHasKey('requestBody', $operation, 'Missing requestBody in operation: ' . strtoupper($method) . ' ' . $path);
        }

        if ($expectsResponses) {
            self::assertArrayHasKey('responses', $operation, 'Missing responses in operation: ' . strtoupper($method) . ' ' . $path);
            self::assertTrue(array_key_exists('200', $operation['responses']) || array_key_exists('201', $operation['responses']) || array_key_exists('204', $operation['responses']));
        }
    }
}
