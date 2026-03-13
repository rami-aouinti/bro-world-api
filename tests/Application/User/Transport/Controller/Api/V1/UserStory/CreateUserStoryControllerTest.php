<?php

declare(strict_types=1);

namespace App\Tests\Application\User\Transport\Controller\Api\V1\UserStory;

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

class CreateUserStoryControllerTest extends WebTestCase
{
    private string $baseUrl = self::API_URL_PREFIX . '/v1/private/stories';

    /**
     * @throws Throwable
     */
    #[TestDox('Test that `POST /v1/private/stories` requires authentication.')]
    public function testThatCreateStoryRequiresAuthentication(): void
    {
        $client = $this->getTestClient();

        $client->request('POST', $this->baseUrl);
        $response = $client->getResponse();

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode(), "Response:\n" . $response);
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that authenticated user can upload a story photo and create a story.')]
    public function testThatAuthenticatedUserCanCreateStoryWithUploadedPhoto(): void
    {
        $client = $this->getTestClient('john-root', 'password-root', null, [
            'CONTENT_TYPE' => 'multipart/form-data',
        ]);

        $tmpImage = sys_get_temp_dir() . '/story_upload_' . bin2hex(random_bytes(8)) . '.png';
        file_put_contents($tmpImage, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO6p8b8AAAAASUVORK5CYII=', true));

        $photoFile = new UploadedFile(
            $tmpImage,
            'story.png',
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
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), "Response:\n" . $response);

        $responseData = JSON::decode($content, true);
        self::assertIsArray($responseData);
        self::assertArrayHasKey('id', $responseData);
        self::assertArrayHasKey('imageUrl', $responseData);
        self::assertIsString($responseData['imageUrl']);
        self::assertStringContainsString('/uploads/stories/', $responseData['imageUrl']);

        $photoPath = parse_url($responseData['imageUrl'], PHP_URL_PATH);
        self::assertIsString($photoPath);

        $projectDir = (string)static::getContainer()->getParameter('kernel.project_dir');
        $absolutePhotoPath = $projectDir . '/public' . $photoPath;

        self::assertFileExists($absolutePhotoPath);

        if (file_exists($absolutePhotoPath)) {
            unlink($absolutePhotoPath);
        }
    }
}
