<?php

declare(strict_types=1);

namespace App\Tests\Application\Recruit\Transport\Controller\Api\V1\Resume;

use App\General\Domain\Utils\JSON;
use App\Recruit\Domain\Entity\Resume;
use App\Tests\TestCase\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function bin2hex;
use function file_exists;
use function file_put_contents;
use function json_encode;
use function parse_url;
use function random_bytes;
use function sys_get_temp_dir;
use function unlink;

class ResumeCreateControllerTest extends WebTestCase
{
    private string $baseUrl = self::API_URL_PREFIX . '/v1/recruit/resumes';

    /**
     * @throws Throwable
     */
    #[TestDox('Test that `POST /v1/recruit/resumes` requires authentication.')]
    public function testThatCreateResumeRequiresAuthentication(): void
    {
        $client = $this->getTestClient();

        $client->request('POST', $this->baseUrl);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that `POST /v1/recruit/resumes` creates resume with uploaded PDF document.')]
    public function testThatCreateResumeWithPdfUploadWorks(): void
    {
        $client = $this->getTestClient('john-root', 'password-root', null, [
            'CONTENT_TYPE' => 'multipart/form-data',
        ]);

        $tmpPdf = sys_get_temp_dir() . '/resume_upload_' . bin2hex(random_bytes(8)) . '.pdf';
        file_put_contents($tmpPdf, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");

        $documentFile = new UploadedFile(
            $tmpPdf,
            'resume.pdf',
            'application/pdf',
            null,
            true
        );

        $client->request(
            'POST',
            $this->baseUrl,
            [
                'experiences' => json_encode([
                    [
                        'title' => 'Backend Developer',
                        'description' => 'API development',
                    ],
                ], JSON_THROW_ON_ERROR),
            ],
            [
                'document' => $documentFile,
            ],
        );

        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), "Response:\n" . $response);

        $payload = JSON::decode($content, true);
        self::assertArrayHasKey('id', $payload);
        self::assertArrayHasKey('documentUrl', $payload);
        self::assertArrayHasKey('resumeInformation', $payload);
        self::assertIsString($payload['documentUrl']);
        self::assertStringContainsString('/uploads/resumes/', $payload['documentUrl']);
        self::assertNotEmpty($payload['resumeInformation']['fullName']);
        self::assertNotEmpty($payload['resumeInformation']['email']);

        $documentPath = parse_url($payload['documentUrl'], PHP_URL_PATH);
        self::assertIsString($documentPath);

        $projectDir = (string)static::getContainer()->getParameter('kernel.project_dir');
        $absoluteDocumentPath = $projectDir . '/public' . $documentPath;
        self::assertFileExists($absoluteDocumentPath);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $resume = $entityManager->getRepository(Resume::class)->find($payload['id']);
        self::assertInstanceOf(Resume::class, $resume);
        self::assertSame($payload['documentUrl'], $resume->getDocumentUrl());
        self::assertSame($payload['resumeInformation']['email'], $resume->getInformationEmail());

        if (file_exists($absoluteDocumentPath)) {
            unlink($absoluteDocumentPath);
        }
    }
}
