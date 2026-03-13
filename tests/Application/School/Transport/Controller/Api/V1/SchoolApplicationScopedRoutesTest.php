<?php

declare(strict_types=1);

namespace App\Tests\Application\School\Transport\Controller\Api\V1;

use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;

final class SchoolApplicationScopedRoutesTest extends WebTestCase
{
    #[TestDox('School scoped application routes enforce owner/foreign-owner access control across list, detail and mutation endpoints.')]
    public function testScopedRoutesAccessControlForOwnerAndForeignOwner(): void
    {
        $ownerClient = $this->getTestClient('john-root', 'password-root');
        $forbiddenClient = $this->getTestClient('john-user', 'password-user');

        $ownerClient->request('GET', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/classes');
        self::assertSame(Response::HTTP_OK, $ownerClient->getResponse()->getStatusCode());

        $forbiddenClient->request('GET', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/classes');
        self::assertSame(Response::HTTP_FORBIDDEN, $forbiddenClient->getResponse()->getStatusCode());

        $ownerClient->request('GET', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/students');
        self::assertSame(Response::HTTP_OK, $ownerClient->getResponse()->getStatusCode());

        $forbiddenClient->request('GET', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/students');
        self::assertSame(Response::HTTP_FORBIDDEN, $forbiddenClient->getResponse()->getStatusCode());

        $ownerClient->request('GET', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/teachers');
        self::assertSame(Response::HTTP_OK, $ownerClient->getResponse()->getStatusCode());

        $forbiddenClient->request('GET', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/teachers');
        self::assertSame(Response::HTTP_FORBIDDEN, $forbiddenClient->getResponse()->getStatusCode());
        self::assertStringContainsString('Forbidden application scope access.', (string)$forbiddenClient->getResponse()->getContent());

        $ownerClient->request('GET', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/exams');
        self::assertSame(Response::HTTP_OK, $ownerClient->getResponse()->getStatusCode());

        $forbiddenClient->request('GET', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/exams');
        self::assertSame(Response::HTTP_FORBIDDEN, $forbiddenClient->getResponse()->getStatusCode());

        $ownerClient->request('GET', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/grades');
        self::assertSame(Response::HTTP_OK, $ownerClient->getResponse()->getStatusCode());

        $forbiddenClient->request('GET', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/grades');
        self::assertSame(Response::HTTP_FORBIDDEN, $forbiddenClient->getResponse()->getStatusCode());

        $ownerClient->request('GET', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/classes');
        $responseData = JSON::decode((string)$ownerClient->getResponse()->getContent(), true);
        $classId = $responseData['items'][0]['id'];

        $ownerClient->request('GET', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/classes/' . $classId);
        self::assertSame(Response::HTTP_OK, $ownerClient->getResponse()->getStatusCode());

        $forbiddenClient->request('GET', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/classes/' . $classId);
        self::assertSame(Response::HTTP_FORBIDDEN, $forbiddenClient->getResponse()->getStatusCode());
        self::assertStringContainsString('Forbidden application scope access.', (string)$forbiddenClient->getResponse()->getContent());

        $ownerClient->request('PATCH', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/classes/' . $classId, [], [], [], JSON::encode([
            'name' => 'Classe Scoped Updated',
        ]));
        self::assertSame(Response::HTTP_OK, $ownerClient->getResponse()->getStatusCode());

        $forbiddenClient->request('PATCH', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/classes/' . $classId, [], [], [], JSON::encode([
            'name' => 'Classe Should Fail',
        ]));
        self::assertSame(Response::HTTP_FORBIDDEN, $forbiddenClient->getResponse()->getStatusCode());

        $ownerClient->request('POST', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/classes', [], [], [], JSON::encode([
            'name' => 'Classe Scoped Create',
        ]));
        self::assertSame(Response::HTTP_CREATED, $ownerClient->getResponse()->getStatusCode());

        $forbiddenClient->request('POST', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/classes', [], [], [], JSON::encode([
            'name' => 'Classe Forbidden',
        ]));
        self::assertSame(Response::HTTP_FORBIDDEN, $forbiddenClient->getResponse()->getStatusCode());
    }

    #[TestDox('School scoped list endpoints only return data for the current school application.')]
    public function testScopedListsAreIsolatedBySchoolApplication(): void
    {
        $ownerClient = $this->getTestClient('john-root', 'password-root');

        foreach (['classes', 'students', 'teachers', 'exams', 'grades'] as $resource) {
            $ownerClient->request('GET', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/' . $resource);
            self::assertSame(Response::HTTP_OK, $ownerClient->getResponse()->getStatusCode());
            $campus = JSON::decode((string)$ownerClient->getResponse()->getContent(), true);
            self::assertNotEmpty($campus['items']);

            $ownerClient->request('GET', self::API_URL_PREFIX . '/v1/school/applications/school-course-flow/' . $resource);
            self::assertSame(Response::HTTP_OK, $ownerClient->getResponse()->getStatusCode());
            $course = JSON::decode((string)$ownerClient->getResponse()->getContent(), true);
            self::assertNotEmpty($course['items']);

            $campusIds = array_column($campus['items'], 'id');
            $courseIds = array_column($course['items'], 'id');

            self::assertEmpty(array_intersect($campusIds, $courseIds), sprintf('Resource %s leaked entities across schools.', $resource));
        }
    }
}
