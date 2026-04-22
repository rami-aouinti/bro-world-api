<?php

declare(strict_types=1);

namespace App\Tests\Application\Recruit\Transport\Controller\Api\V1\General;

use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;

final class ListGeneralJobsControllerTest extends WebTestCase
{
    #[TestDox('Test that `GET /v1/recruit/general/jobs` keeps the expected payload structure.')]
    public function testThatGeneralJobsListKeepsExpectedPayloadStructure(): void
    {
        $client = $this->getTestClient();
        $client->request('GET', self::API_URL_PREFIX . '/v1/recruit/general/jobs?limit=1');

        $response = $client->getResponse();
        $content = $response->getContent();

        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $payload = JSON::decode($content, true);
        self::assertIsArray($payload['items'] ?? null);
        self::assertNotEmpty($payload['items']);

        $item = $payload['items'][0];
        self::assertIsArray($item);
        self::assertArrayHasKey('missionTitle', $item);
        self::assertArrayHasKey('missionDescription', $item);
        self::assertArrayHasKey('responsibilities', $item);
        self::assertArrayHasKey('benefits', $item);
        self::assertArrayHasKey('matchScore', $item);
        self::assertArrayNotHasKey('owner', $item);
        self::assertArrayNotHasKey('apply', $item);
    }
}
