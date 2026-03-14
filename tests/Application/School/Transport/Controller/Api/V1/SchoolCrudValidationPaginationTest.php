<?php

declare(strict_types=1);

namespace App\Tests\Application\School\Transport\Controller\Api\V1;

use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;

final class SchoolCrudValidationPaginationTest extends WebTestCase
{
    #[TestDox('School API supports CRUD with validation errors, pagination and filters on class/exam scoped listings.')]
    public function testSchoolCrudValidationPaginationAndFilters(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        $client->request('POST', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/classes', [], [], [], JSON::encode([
            'name' => '',
        ]));
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
        $classValidationPayload = JSON::decode((string)$client->getResponse()->getContent(), true);
        self::assertSame('SCHOOL_VALIDATION_FAILED', $classValidationPayload['code']);
        self::assertNotEmpty($classValidationPayload['details']);

        $client->request('POST', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/classes', [], [], [], JSON::encode([
            'name' => 'Classe API Test',
        ]));
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $createdClass = JSON::decode((string)$client->getResponse()->getContent(), true);
        $createdClassId = $createdClass['id'];

        $client->request('PATCH', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/classes/' . $createdClassId, [], [], [], JSON::encode([
            'name' => 'Classe API Test Updated',
        ]));
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $client->request('GET', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/classes?page=1&limit=5&q=Classe');
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $listPayload = JSON::decode((string)$client->getResponse()->getContent(), true);

        self::assertArrayHasKey('items', $listPayload);
        self::assertArrayHasKey('pagination', $listPayload);
        self::assertArrayHasKey('meta', $listPayload);
        self::assertSame(1, $listPayload['pagination']['page']);
        self::assertSame(5, $listPayload['pagination']['limit']);
        self::assertSame('Classe', $listPayload['meta']['filters']['q']);

        $client->request('GET', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/teachers');
        $teachersPayload = JSON::decode((string)$client->getResponse()->getContent(), true);
        $teacherId = $teachersPayload['items'][0]['id'];

        $client->request('POST', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/exams', [], [], [], JSON::encode([
            'title' => '',
            'classId' => $createdClassId,
            'teacherId' => $teacherId,
            'type' => 'invalid',
            'status' => 'invalid',
            'term' => 'invalid',
        ]));
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
        $examValidationPayload = JSON::decode((string)$client->getResponse()->getContent(), true);
        self::assertSame('SCHOOL_VALIDATION_FAILED', $examValidationPayload['code']);
        self::assertNotEmpty($examValidationPayload['details']);

        $client->request('POST', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/exams', [], [], [], JSON::encode([
            'title' => 'Exam CRUD API Test',
            'classId' => $createdClassId,
            'teacherId' => $teacherId,
            'type' => 'midterm',
            'status' => 'draft',
            'term' => 'term_2',
        ]));
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $createdExam = JSON::decode((string)$client->getResponse()->getContent(), true);
        $createdExamId = $createdExam['id'];

        $client->request('GET', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/exams/' . $createdExamId);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $client->request('PATCH', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/exams/' . $createdExamId, [], [], [], JSON::encode([
            'status' => 'published',
            'term' => 'term_3',
        ]));
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $client->request('DELETE', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/exams/' . $createdExamId);
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        $client->request('DELETE', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/classes/' . $createdClassId);
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
    }
}
