<?php

declare(strict_types=1);

namespace App\Tests\Application\School\Transport\Controller\Api\V1;

use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
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
        $forbiddenPayload = JSON::decode((string)$forbiddenClient->getResponse()->getContent(), true);
        self::assertSame('Forbidden application scope access.', $forbiddenPayload['message']);
        self::assertSame('SCHOOL_FORBIDDEN', $forbiddenPayload['code']);
        self::assertSame([], $forbiddenPayload['details']);

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

        $ownerClient->request('POST', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/students', [], [], [], JSON::encode([
            'name' => 'Eleve Scoped Create',
            'classId' => $classId,
        ]));
        self::assertSame(Response::HTTP_CREATED, $ownerClient->getResponse()->getStatusCode());

        $forbiddenClient->request('POST', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/students', [], [], [], JSON::encode([
            'name' => 'Eleve Forbidden',
            'classId' => $classId,
        ]));
        self::assertSame(Response::HTTP_FORBIDDEN, $forbiddenClient->getResponse()->getStatusCode());

        $ownerClient->request('POST', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/teachers', [], [], [], JSON::encode([
            'name' => 'Prof Scoped Create',
        ]));
        self::assertSame(Response::HTTP_CREATED, $ownerClient->getResponse()->getStatusCode());
        $teacherId = JSON::decode((string)$ownerClient->getResponse()->getContent(), true)['id'];

        $forbiddenClient->request('POST', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/teachers', [], [], [], JSON::encode([
            'name' => 'Prof Forbidden',
        ]));
        self::assertSame(Response::HTTP_FORBIDDEN, $forbiddenClient->getResponse()->getStatusCode());

        $ownerClient->request('POST', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/exams', [], [], [], JSON::encode([
            'title' => 'Examen Scoped Create',
            'classId' => $classId,
            'teacherId' => $teacherId,
            'type' => 'QUIZ',
            'status' => 'DRAFT',
            'term' => 'TERM_1',
        ]));
        self::assertSame(Response::HTTP_CREATED, $ownerClient->getResponse()->getStatusCode());
        $examId = JSON::decode((string)$ownerClient->getResponse()->getContent(), true)['id'];

        $forbiddenClient->request('POST', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/exams', [], [], [], JSON::encode([
            'title' => 'Examen Forbidden',
            'classId' => $classId,
            'teacherId' => $teacherId,
            'type' => 'QUIZ',
            'status' => 'DRAFT',
            'term' => 'TERM_1',
        ]));
        self::assertSame(Response::HTTP_FORBIDDEN, $forbiddenClient->getResponse()->getStatusCode());

        $ownerClient->request('GET', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/students');
        $studentsResponseData = JSON::decode((string)$ownerClient->getResponse()->getContent(), true);
        $studentId = $studentsResponseData['items'][0]['id'];

        $ownerClient->request('POST', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/grades', [], [], [], JSON::encode([
            'score' => 15.5,
            'studentId' => $studentId,
            'examId' => $examId,
        ]));
        self::assertSame(Response::HTTP_CREATED, $ownerClient->getResponse()->getStatusCode());

        $forbiddenClient->request('POST', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/grades', [], [], [], JSON::encode([
            'score' => 12.0,
            'studentId' => $studentId,
            'examId' => $examId,
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

    #[TestDox('School scoped POST endpoints deny forbidden user on students, teachers, exams and grades resources.')]
    public function testScopedPostRoutesAreForbiddenForForeignUser(): void
    {
        $ownerClient = $this->getTestClient('john-root', 'password-root');
        $forbiddenClient = $this->getTestClient('john-user', 'password-user');

        $ownerClient->request('GET', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/classes');
        self::assertSame(Response::HTTP_OK, $ownerClient->getResponse()->getStatusCode());
        $classId = JSON::decode((string)$ownerClient->getResponse()->getContent(), true)['items'][0]['id'];

        $ownerClient->request('POST', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/teachers', [], [], [], JSON::encode([
            'name' => 'Prof Forbidden Scope Setup',
        ]));
        self::assertSame(Response::HTTP_CREATED, $ownerClient->getResponse()->getStatusCode());
        $teacherId = JSON::decode((string)$ownerClient->getResponse()->getContent(), true)['id'];

        $ownerClient->request('POST', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/students', [], [], [], JSON::encode([
            'name' => 'Eleve Forbidden Scope Setup',
            'classId' => $classId,
        ]));
        self::assertSame(Response::HTTP_CREATED, $ownerClient->getResponse()->getStatusCode());
        $studentId = JSON::decode((string)$ownerClient->getResponse()->getContent(), true)['id'];

        $ownerClient->request('POST', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/exams', [], [], [], JSON::encode([
            'title' => 'Examen Forbidden Scope Setup',
            'classId' => $classId,
            'teacherId' => $teacherId,
            'type' => 'QUIZ',
            'status' => 'DRAFT',
            'term' => 'TERM_1',
        ]));
        self::assertSame(Response::HTTP_CREATED, $ownerClient->getResponse()->getStatusCode());
        $examId = JSON::decode((string)$ownerClient->getResponse()->getContent(), true)['id'];

        $forbiddenPayloads = [
            'students' => [
                'name' => 'Eleve Forbidden Scoped Post',
                'classId' => $classId,
            ],
            'teachers' => [
                'name' => 'Prof Forbidden Scoped Post',
            ],
            'exams' => [
                'title' => 'Examen Forbidden Scoped Post',
                'classId' => $classId,
                'teacherId' => $teacherId,
                'type' => 'QUIZ',
                'status' => 'DRAFT',
                'term' => 'TERM_1',
            ],
            'grades' => [
                'score' => 11.5,
                'studentId' => $studentId,
                'examId' => $examId,
            ],
        ];

        foreach ($forbiddenPayloads as $endpoint => $payload) {
            $forbiddenClient->request('POST', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/' . $endpoint, [], [], [], JSON::encode($payload));
            self::assertSame(Response::HTTP_FORBIDDEN, $forbiddenClient->getResponse()->getStatusCode());

            $forbiddenPayload = JSON::decode((string)$forbiddenClient->getResponse()->getContent(), true);
            self::assertSame('SCHOOL_FORBIDDEN', $forbiddenPayload['code']);
            self::assertSame([], $forbiddenPayload['details']);
        }

        self::assertStringContainsString('Forbidden application scope access.', (string)$forbiddenClient->getResponse()->getContent());
    }

    #[TestDox('School scoped POST endpoints reject references coming from another school application.')]
    public function testScopedPostRoutesRejectForeignApplicationReferencesWithNotFound(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        $campusClassId = $this->firstResourceId($client, '/v1/school/applications/school-campus-core/classes');

        $client->request('POST', self::API_URL_PREFIX . '/v1/school/applications/school-course-flow/students', [], [], [], JSON::encode([
            'name' => 'Eleve cross app',
            'classId' => $campusClassId,
        ]));
        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        self::assertSame('classId not found', $this->responsePayload($client)['message']);

        $courseTeacherId = $this->firstResourceId($client, '/v1/school/applications/school-course-flow/teachers');

        $client->request('POST', self::API_URL_PREFIX . '/v1/school/applications/school-course-flow/exams', [], [], [], JSON::encode([
            'title' => 'Examen cross app',
            'classId' => $campusClassId,
            'teacherId' => $courseTeacherId,
            'type' => 'QUIZ',
            'status' => 'DRAFT',
            'term' => 'TERM_1',
        ]));
        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        self::assertSame('classId not found', $this->responsePayload($client)['message']);

        $campusStudentId = $this->firstResourceId($client, '/v1/school/applications/school-campus-core/students');
        $campusExamId = $this->firstResourceId($client, '/v1/school/applications/school-campus-core/exams');

        $client->request('POST', self::API_URL_PREFIX . '/v1/school/applications/school-course-flow/grades', [], [], [], JSON::encode([
            'score' => 16.0,
            'studentId' => $campusStudentId,
            'examId' => $campusExamId,
        ]));
        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        self::assertSame('studentId not found', $this->responsePayload($client)['message']);
    }

    /**
     * @return array<string, mixed>
     */
    private function responsePayload(KernelBrowser $client): array
    {
        return JSON::decode((string)$client->getResponse()->getContent(), true);
    }

    private function firstResourceId(KernelBrowser $client, string $path): string
    {
        $client->request('GET', self::API_URL_PREFIX . $path);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        /** @var array{items: array<int, array{id: string}>} $payload */
        $payload = JSON::decode((string)$client->getResponse()->getContent(), true);

        return $payload['items'][0]['id'];
    }
}
