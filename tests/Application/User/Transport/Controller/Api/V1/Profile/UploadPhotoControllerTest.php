<?php

declare(strict_types=1);

namespace App\Tests\Application\User\Transport\Controller\Api\V1\Profile;

use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function file_exists;
use function file_put_contents;
use function parse_url;
use function random_bytes;
use function sys_get_temp_dir;
use function unlink;

/**
 * @package App\Tests
 */
class UploadPhotoControllerTest extends WebTestCase
{
    private string $baseUrl = self::API_URL_PREFIX . '/v1/profile/photo';

    /**
     * @throws Throwable
     */
    #[TestDox('Test that `POST /v1/profile/photo` requires authentication.')]
    public function testThatUploadPhotoRequiresAuthentication(): void
    {
        $client = $this->getTestClient();

        $client->request('POST', $this->baseUrl);
        $response = $client->getResponse();

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode(), "Response:\n" . $response);
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that user can upload profile photo when MIME detection fallback is needed.')]
    public function testThatUserCanUploadProfilePhotoWhenMimeTypeFallbackIsNeeded(): void
    {
        $client = $this->getTestClient('john-root', 'password-root', null, [
            'CONTENT_TYPE' => 'multipart/form-data',
        ]);

        $tmpImage = sys_get_temp_dir() . '/profile_upload_' . bin2hex(random_bytes(8)) . '.png';
        file_put_contents($tmpImage, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO6p8b8AAAAASUVORK5CYII=', true));

        $photoFile = new UploadedFile(
            $tmpImage,
            'profile.png',
            'application/octet-stream',
            null,
            true
        );

        $client->request(
            'POST',
            $this->baseUrl,
            [],
            [
                'photo' => $photoFile,
            ],
        );

        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $responseData = JSON::decode($content, true);
        self::assertIsArray($responseData);
        self::assertArrayHasKey('photo', $responseData);
        self::assertIsString($responseData['photo']);
        self::assertStringContainsString('/uploads/profile/', $responseData['photo']);

        $photoPath = parse_url($responseData['photo'], PHP_URL_PATH);
        self::assertIsString($photoPath);

        $projectDir = (string)static::getContainer()->getParameter('kernel.project_dir');
        $absolutePhotoPath = $projectDir . '/public' . $photoPath;

        self::assertFileExists($absolutePhotoPath);

        if (file_exists($absolutePhotoPath)) {
            unlink($absolutePhotoPath);
        }
    }
}
