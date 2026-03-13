<?php

declare(strict_types=1);

namespace App\Tests\Application\School\Transport\Controller\Api\V1;

use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

final class SchoolCreateReferenceValidationTest extends WebTestCase
{
    private const string UNKNOWN_UUID = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';

    #[TestDox('School create endpoints reject missing references with stable 404 messages.')]
    public function testCreateEndpointsRejectUnknownReferences(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        $client->request('POST', self::API_URL_PREFIX . '/v1/school/classes', [], [], [], JSON::encode([
            'name' => 'Classe invalide',
            'schoolId' => self::UNKNOWN_UUID,
        ]));
        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        self::assertSame('schoolId not found', $this->responsePayload($client)['message']);

        $client->request('POST', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/students', [], [], [], JSON::encode([
            'name' => 'Eleve API',
            'classId' => self::UNKNOWN_UUID,
        ]));
        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        self::assertSame('classId not found', $this->responsePayload($client)['message']);

        $client->request('POST', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/exams', [], [], [], JSON::encode([
            'title' => 'Examen API',
            'classId' => self::UNKNOWN_UUID,
            'teacherId' => self::UNKNOWN_UUID,
            'type' => 'quiz',
            'status' => 'draft',
            'term' => 'term_1',
        ]));
        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        self::assertSame('classId not found', $this->responsePayload($client)['message']);

        $client->request('POST', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/grades', [], [], [], JSON::encode([
            'score' => 12,
            'studentId' => self::UNKNOWN_UUID,
            'examId' => self::UNKNOWN_UUID,
        ]));
        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        self::assertSame('studentId not found', $this->responsePayload($client)['message']);
    }

    #[TestDox('School create endpoints reject cross-scope relations with stable 422 messages.')]
    public function testCreateEndpointsRejectCrossScopeRelations(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        $campusClassId = $this->firstResourceId($client, '/v1/school/applications/school-campus-core/classes');
        $courseTeacherId = $this->firstResourceId($client, '/v1/school/applications/school-course-flow/teachers');

        $client->request('POST', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/exams', [], [], [], JSON::encode([
            'title' => 'Examen incoherent',
            'classId' => $campusClassId,
            'teacherId' => $courseTeacherId,
            'type' => 'quiz',
            'status' => 'draft',
            'term' => 'term_1',
        ]));
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
        self::assertSame('teacherId is not assigned to classId', $this->responsePayload($client)['message']);

        $campusStudentId = $this->firstResourceId($client, '/v1/school/applications/school-campus-core/students');
        $courseExamId = $this->firstResourceId($client, '/v1/school/applications/school-course-flow/exams');

        $client->request('POST', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/grades', [], [], [], JSON::encode([
            'score' => 14,
            'studentId' => $campusStudentId,
            'examId' => $courseExamId,
        ]));
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
        self::assertSame('studentId and examId must belong to the same class', $this->responsePayload($client)['message']);
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
