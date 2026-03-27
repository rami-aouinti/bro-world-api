<?php

declare(strict_types=1);

namespace App\Tests\Application\Library\Transport\Controller\Api\V1;

use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function bin2hex;
use function file_exists;
use function file_put_contents;
use function is_array;
use function parse_url;
use function random_bytes;
use function str_starts_with;
use function sys_get_temp_dir;
use function unlink;

class LibraryControllerTest extends WebTestCase
{
    private string $createFolderUrl = self::API_URL_PREFIX . '/v1/library/folders';
    private string $uploadUrl = self::API_URL_PREFIX . '/v1/library/files/upload';
    private string $treeUrl = self::API_URL_PREFIX . '/v1/library/tree';

    /**
     * @throws Throwable
     */
    #[TestDox('Authenticated user can create folders, upload a file, and fetch full tree.')]
    public function testLibraryFolderUploadAndTree(): void
    {
        $jsonClient = $this->getTestClient('john-user', 'password-user');
        $jsonClient->request('POST', $this->createFolderUrl, [], [], [], JSON::encode([
            'name' => 'Racine',
        ]));

        self::assertSame(Response::HTTP_CREATED, $jsonClient->getResponse()->getStatusCode(), "Response:\n" . $jsonClient->getResponse());
        $rootPayload = JSON::decode((string)$jsonClient->getResponse()->getContent(), true);
        self::assertIsArray($rootPayload);
        self::assertArrayHasKey('id', $rootPayload);

        $jsonClient->request('POST', $this->createFolderUrl, [], [], [], JSON::encode([
            'name' => 'Sous Dossier',
            'parentId' => $rootPayload['id'],
        ]));

        self::assertSame(Response::HTTP_CREATED, $jsonClient->getResponse()->getStatusCode(), "Response:\n" . $jsonClient->getResponse());
        $subPayload = JSON::decode((string)$jsonClient->getResponse()->getContent(), true);
        self::assertIsArray($subPayload);

        $multipartClient = $this->getTestClient('john-user', 'password-user', null, [
            'CONTENT_TYPE' => 'multipart/form-data',
        ]);

        $tmpPdf = $this->createTempPdf();
        $multipartClient->request('POST', $this->uploadUrl, [
            'folderId' => $subPayload['id'],
        ], [
            'file' => new UploadedFile($tmpPdf, 'cv.pdf', 'application/pdf', null, true),
        ]);

        self::assertSame(Response::HTTP_CREATED, $multipartClient->getResponse()->getStatusCode(), "Response:\n" . $multipartClient->getResponse());
        $uploadPayload = JSON::decode((string)$multipartClient->getResponse()->getContent(), true);
        self::assertIsArray($uploadPayload);
        self::assertSame('pdf', $uploadPayload['fileType'] ?? null);
        self::assertSame($subPayload['id'], $uploadPayload['folderId'] ?? null);

        $path = parse_url((string)($uploadPayload['url'] ?? ''), PHP_URL_PATH);
        self::assertIsString($path);
        self::assertTrue(str_starts_with($path, '/uploads/library/'));

        $projectDir = (string)static::getContainer()->getParameter('kernel.project_dir');
        $absolutePath = $projectDir . '/public' . $path;
        self::assertFileExists($absolutePath);

        $jsonClient->request('GET', $this->treeUrl);
        self::assertSame(Response::HTTP_OK, $jsonClient->getResponse()->getStatusCode(), "Response:\n" . $jsonClient->getResponse());

        $treePayload = JSON::decode((string)$jsonClient->getResponse()->getContent(), true);
        self::assertIsArray($treePayload);
        self::assertArrayHasKey('folders', $treePayload);
        self::assertArrayHasKey('files', $treePayload);
        self::assertIsArray($treePayload['folders']);

        $rootNode = $this->findFolderNode($treePayload['folders'], 'Racine');
        self::assertNotNull($rootNode);

        $subNode = $this->findFolderNode($rootNode['folders'] ?? [], 'Sous Dossier');
        self::assertNotNull($subNode);
        self::assertIsArray($subNode['files'] ?? null);
        self::assertCount(1, $subNode['files']);
        self::assertSame('pdf', $subNode['files'][0]['fileType'] ?? null);

        if (file_exists($tmpPdf)) {
            unlink($tmpPdf);
        }

        if (file_exists($absolutePath)) {
            unlink($absolutePath);
        }
    }

    /**
     * @param mixed $nodes
     * @return array<string,mixed>|null
     */
    private function findFolderNode(mixed $nodes, string $name): ?array
    {
        if (!is_array($nodes)) {
            return null;
        }

        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }

            if (($node['name'] ?? null) === $name) {
                return $node;
            }
        }

        return null;
    }

    private function createTempPdf(): string
    {
        $tmpPdf = sys_get_temp_dir() . '/library_pdf_' . bin2hex(random_bytes(8)) . '.pdf';
        file_put_contents($tmpPdf, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF");

        return $tmpPdf;
    }
}
