<?php

declare(strict_types=1);

namespace App\Tests\Application\Recruit\Transport\Controller\Api\V1\Resume;

use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MyResumeListControllerTest extends WebTestCase
{
    private string $baseUrl = self::API_URL_PREFIX . '/v1/recruit/private/me/resumes';

    /**
     * @throws Throwable
     */
    #[TestDox('Test that `GET /v1/recruit/private/me/resumes` requires authentication.')]
    public function testThatMyResumeListRequiresAuthentication(): void
    {
        $client = $this->getTestClient();

        $client->request('GET', $this->baseUrl);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that `GET /v1/recruit/private/me/resumes` returns only connected user resumes.')]
    public function testThatMyResumeListReturnsOnlyConnectedUserResumes(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        $client->request('GET', $this->baseUrl);

        $response = $client->getResponse();
        $content = $response->getContent();

        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $payload = JSON::decode($content, true);

        self::assertIsArray($payload);
        self::assertCount(1, $payload);

        $resume = $payload[0];
        self::assertArrayHasKey('id', $resume);
        self::assertArrayHasKey('documentUrl', $resume);

        foreach (['experiences', 'educations', 'skills', 'languages', 'certifications', 'projects', 'references', 'hobbies'] as $field) {
            self::assertArrayHasKey($field, $resume);
            self::assertIsArray($resume[$field]);
            self::assertNotEmpty($resume[$field]);

            self::assertArrayHasKey('id', $resume[$field][0]);
            self::assertArrayHasKey('title', $resume[$field][0]);
            self::assertArrayHasKey('description', $resume[$field][0]);
        }
    }
}
