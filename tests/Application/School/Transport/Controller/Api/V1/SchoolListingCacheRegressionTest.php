<?php

declare(strict_types=1);

namespace App\Tests\Application\School\Transport\Controller\Api\V1;

use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;

final class SchoolListingCacheRegressionTest extends WebTestCase
{
    #[TestDox('Exam list remains stable across repeated calls with pagination/filters on larger fixture datasets (cache regression).')]
    public function testExamListCacheAndPaginationRegression(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        $endpoint = self::API_URL_PREFIX . '/v1/school/exams?page=1&limit=10&title=Examen&q=Examen';

        $client->request('GET', $endpoint);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $firstPayload = JSON::decode((string)$client->getResponse()->getContent(), true);

        $client->request('GET', $endpoint);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $secondPayload = JSON::decode((string)$client->getResponse()->getContent(), true);

        self::assertSame($firstPayload['pagination'], $secondPayload['pagination']);
        self::assertSame($firstPayload['meta']['filters'], $secondPayload['meta']['filters']);
        self::assertCount(10, $firstPayload['items']);
        self::assertGreaterThanOrEqual(30, $firstPayload['pagination']['totalItems']);
    }


    #[TestDox('Exam list cache is isolated per application slug.')]
    public function testExamListCacheIsolationByApplicationSlug(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        $campusEndpoint = self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/exams?page=1&limit=10&q=Examen';
        $courseEndpoint = self::API_URL_PREFIX . '/v1/school/applications/school-course-flow/exams?page=1&limit=10&q=Examen';

        $client->request('GET', $campusEndpoint);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $campusPayload = JSON::decode((string)$client->getResponse()->getContent(), true);

        $client->request('GET', $courseEndpoint);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $coursePayload = JSON::decode((string)$client->getResponse()->getContent(), true);

        self::assertNotSame($campusPayload['items'], $coursePayload['items']);

        $client->request('GET', $campusEndpoint);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $campusPayloadSecondCall = JSON::decode((string)$client->getResponse()->getContent(), true);

        self::assertSame($campusPayload['items'], $campusPayloadSecondCall['items']);
        self::assertSame($campusPayload['pagination'], $campusPayloadSecondCall['pagination']);
    }

    #[TestDox('Class list by application remains stable across repeated calls with pagination/filters on larger fixture datasets (cache regression).')]
    public function testClassApplicationListCacheAndPaginationRegression(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        $endpoint = self::API_URL_PREFIX . '/v1/school/applications/school-course-flow/classes?page=1&limit=2&q=Classe';

        $client->request('GET', $endpoint);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $firstPayload = JSON::decode((string)$client->getResponse()->getContent(), true);

        $client->request('GET', $endpoint);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $secondPayload = JSON::decode((string)$client->getResponse()->getContent(), true);

        self::assertSame($firstPayload['pagination'], $secondPayload['pagination']);
        self::assertSame($firstPayload['meta']['filters'], $secondPayload['meta']['filters']);
        self::assertSame(2, $firstPayload['pagination']['limit']);
        self::assertGreaterThanOrEqual(3, $firstPayload['pagination']['totalItems']);
        self::assertCount(2, $firstPayload['items']);
    }
}
