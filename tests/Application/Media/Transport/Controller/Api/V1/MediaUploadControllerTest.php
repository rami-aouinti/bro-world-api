<?php

declare(strict_types=1);

namespace App\Tests\Application\Media\Transport\Controller\Api\V1;

use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function bin2hex;
use function file_exists;
use function file_put_contents;
use function parse_url;
use function random_bytes;
use function str_starts_with;
use function sys_get_temp_dir;
use function unlink;

class MediaUploadControllerTest extends WebTestCase
{
    private string $baseUrl = self::API_URL_PREFIX . '/v1/media/upload';

    /**
     * @throws Throwable
     */
    #[TestDox('Test that `POST /v1/media/upload` requires authentication.')]
    public function testThatUploadRequiresAuthentication(): void
    {
        $client = $this->getTestClient();

        $client->request('POST', $this->baseUrl);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that single media upload works.')]
    public function testThatSingleMediaUploadWorks(): void
    {
        $client = $this->getTestClient('john-root', 'password-root', null, [
            'CONTENT_TYPE' => 'multipart/form-data',
        ]);

        $tmpFile = $this->createTempPng('media_single_');
        $uploadedFile = new UploadedFile($tmpFile, 'single.png', 'image/png', null, true);

        $client->request('POST', $this->baseUrl, [], [
            'file' => $uploadedFile,
        ]);

        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), "Response:\n" . $response);

        $payload = JSON::decode($content, true);
        self::assertIsArray($payload);
        self::assertArrayHasKey('files', $payload);
        self::assertIsArray($payload['files']);
        self::assertCount(1, $payload['files']);

        $this->assertValidUploadedMediaPayload($payload['files'][0]);

        if (file_exists($tmpFile)) {
            unlink($tmpFile);
        }
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that multiple media upload works.')]
    public function testThatMultipleMediaUploadWorks(): void
    {
        $client = $this->getTestClient('john-root', 'password-root', null, [
            'CONTENT_TYPE' => 'multipart/form-data',
        ]);

        $tmpPngA = $this->createTempPng('media_multi_a_');
        $tmpPngB = $this->createTempPng('media_multi_b_');

        $client->request('POST', $this->baseUrl, [], [
            'files' => [
                new UploadedFile($tmpPngA, 'a.png', 'image/png', null, true),
                new UploadedFile($tmpPngB, 'b.png', 'image/png', null, true),
            ],
        ]);

        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), "Response:\n" . $response);

        $payload = JSON::decode($content, true);
        self::assertIsArray($payload);
        self::assertArrayHasKey('files', $payload);
        self::assertIsArray($payload['files']);
        self::assertCount(2, $payload['files']);

        foreach ($payload['files'] as $item) {
            self::assertIsArray($item);
            $this->assertValidUploadedMediaPayload($item);
        }

        foreach ([$tmpPngA, $tmpPngB] as $tempFile) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that upload with unsupported media type fails.')]
    public function testThatUploadWithUnsupportedTypeFails(): void
    {
        $client = $this->getTestClient('john-root', 'password-root', null, [
            'CONTENT_TYPE' => 'multipart/form-data',
        ]);

        $tmpExe = sys_get_temp_dir() . '/media_bad_' . bin2hex(random_bytes(8)) . '.exe';
        file_put_contents($tmpExe, 'MZ');

        $client->request('POST', $this->baseUrl, [], [
            'file' => new UploadedFile($tmpExe, 'bad.exe', 'application/x-msdownload', null, true),
        ]);

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), "Response:\n" . $response);

        if (file_exists($tmpExe)) {
            unlink($tmpExe);
        }
    }

    /**
     * @param array<string, mixed> $filePayload
     */
    private function assertValidUploadedMediaPayload(array $filePayload): void
    {
        self::assertArrayHasKey('url', $filePayload);
        self::assertArrayHasKey('originalName', $filePayload);
        self::assertArrayHasKey('mimeType', $filePayload);
        self::assertArrayHasKey('size', $filePayload);

        self::assertIsString($filePayload['url']);
        self::assertIsString($filePayload['originalName']);
        self::assertIsString($filePayload['mimeType']);
        self::assertIsInt($filePayload['size']);

        $path = parse_url($filePayload['url'], PHP_URL_PATH);
        self::assertIsString($path);
        self::assertTrue(str_starts_with($path, '/uploads/media/'));

        $projectDir = (string)static::getContainer()->getParameter('kernel.project_dir');
        $absoluteFilePath = $projectDir . '/public' . $path;
        self::assertFileExists($absoluteFilePath);

        if (file_exists($absoluteFilePath)) {
            unlink($absoluteFilePath);
        }
    }

    private function createTempPng(string $prefix): string
    {
        $tmpImage = sys_get_temp_dir() . '/' . $prefix . bin2hex(random_bytes(8)) . '.png';
        file_put_contents($tmpImage, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO6p8b8AAAAASUVORK5CYII=', true));

        return $tmpImage;
    }
}
