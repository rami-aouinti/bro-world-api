<?php

declare(strict_types=1);

namespace App\Library\Transport\Controller\Api\V1;

use App\Library\Application\Service\LibraryTreeService;
use App\Library\Domain\Entity\LibraryFile;
use App\Library\Domain\Entity\LibraryFolder;
use App\Library\Domain\Enum\LibraryFileType;
use App\Library\Infrastructure\Repository\LibraryFileRepository;
use App\Library\Infrastructure\Repository\LibraryFolderRepository;
use App\Media\Application\Service\MediaUploaderService;
use App\Media\Application\Service\MediaUploadValidationPolicy;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function is_string;
use function parse_url;
use function str_starts_with;
use function trim;

#[AsController]
#[OA\Tag(name: 'Library')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
readonly class LibraryController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LibraryFolderRepository $folderRepository,
        private LibraryFileRepository $fileRepository,
        private MediaUploaderService $mediaUploaderService,
        private LibraryTreeService $libraryTreeService,
        private Filesystem $filesystem,
        private string $projectDir,
    ) {
    }

    #[Route(path: '/v1/library/folders', methods: [Request::METHOD_POST])]
    #[OA\Post(
        summary: 'Créer un dossier dans la library utilisateur (racine ou sous-dossier).',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Documents'),
                    new OA\Property(property: 'parentId', type: 'string', nullable: true, example: '0195df8e-9f4a-7cf2-9f51-b6ed8b4e5bf8'),
                ],
                type: 'object',
                example: [
                    'name' => 'Factures',
                    'parentId' => '0195df8e-9f4a-7cf2-9f51-b6ed8b4e5bf8',
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Dossier créé.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', example: '0195df93-91d2-7eaa-a16e-2abaf2ff57f4'),
                        new OA\Property(property: 'name', type: 'string', example: 'Factures'),
                        new OA\Property(property: 'parentId', type: 'string', nullable: true, example: '0195df8e-9f4a-7cf2-9f51-b6ed8b4e5bf8'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 400, description: 'Payload invalide.'),
            new OA\Response(response: 401, description: 'Authentification requise.'),
            new OA\Response(response: 404, description: 'Dossier parent introuvable.'),
        ],
    )]
    public function createFolder(Request $request, User $loggedInUser): JsonResponse
    {
        /** @var array<string,mixed> $payload */
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $name = trim((string)($payload['name'] ?? ''));
        if ($name === '') {
            throw new HttpException(Response::HTTP_BAD_REQUEST, 'Field "name" is required.');
        }

        $parent = null;
        if (is_string($payload['parentId'] ?? null) && trim($payload['parentId']) !== '') {
            $parent = $this->folderRepository->findOneByIdAndOwner(trim($payload['parentId']), $loggedInUser);
            if (!$parent instanceof LibraryFolder) {
                throw new HttpException(Response::HTTP_NOT_FOUND, 'Parent folder not found.');
            }
        }

        $folder = (new LibraryFolder())
            ->setOwner($loggedInUser)
            ->setName($name)
            ->setParent($parent);

        $this->entityManager->persist($folder);
        $this->entityManager->flush();

        return new JsonResponse([
            'id' => $folder->getId(),
            'name' => $folder->getName(),
            'parentId' => $folder->getParent()?->getId(),
        ], Response::HTTP_CREATED);
    }

    #[Route(path: '/v1/library/files/upload', methods: [Request::METHOD_POST])]
    #[OA\Post(
        summary: 'Uploader un fichier dans la library utilisateur.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['file'],
                    properties: [
                        new OA\Property(property: 'file', description: 'Fichier à uploader.', type: 'string', format: 'binary'),
                        new OA\Property(property: 'folderId', description: 'Optionnel. ID du dossier de destination.', type: 'string', nullable: true),
                    ],
                    type: 'object',
                ),
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Fichier uploadé.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', example: '0195df99-f0f8-7b45-8e22-31f0f5acef52'),
                        new OA\Property(property: 'folderId', type: 'string', nullable: true, example: '0195df8e-9f4a-7cf2-9f51-b6ed8b4e5bf8'),
                        new OA\Property(property: 'name', type: 'string', example: 'contrat.pdf'),
                        new OA\Property(property: 'url', type: 'string', example: 'https://localhost/uploads/library/a3f9f63e7d5f4a7fa0c4efed6f12f9dd.pdf'),
                        new OA\Property(property: 'mimeType', type: 'string', example: 'application/pdf'),
                        new OA\Property(property: 'size', type: 'integer', example: 32145),
                        new OA\Property(property: 'extension', type: 'string', example: 'pdf'),
                        new OA\Property(property: 'fileType', type: 'string', example: 'pdf'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 400, description: 'Fichier manquant ou invalide.'),
            new OA\Response(response: 401, description: 'Authentification requise.'),
            new OA\Response(response: 404, description: 'Dossier introuvable.'),
        ],
    )]
    public function uploadFile(Request $request, User $loggedInUser): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw new HttpException(Response::HTTP_BAD_REQUEST, 'Field "file" is required.');
        }

        $folder = null;
        $folderId = $request->request->get('folderId');
        if (is_string($folderId) && trim($folderId) !== '') {
            $folder = $this->folderRepository->findOneByIdAndOwner(trim($folderId), $loggedInUser);
            if (!$folder instanceof LibraryFolder) {
                throw new HttpException(Response::HTTP_NOT_FOUND, 'Folder not found.');
            }
        }

        $policy = new MediaUploadValidationPolicy(
            maxSizeInBytes: 100 * 1024 * 1024,
            allowedMimeTypes: [
                'image/jpeg',
                'image/png',
                'image/webp',
                'image/gif',
                'image/svg+xml',
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/csv',
                'video/mp4',
                'video/quicktime',
                'video/x-msvideo',
                'video/x-matroska',
                'video/webm',
            ],
            allowedExtensions: ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'mp4', 'mov', 'avi', 'mkv', 'webm'],
        );

        $uploaded = $this->mediaUploaderService->upload($request, [$file], '/uploads/library', $policy)[0];
        $libraryFile = (new LibraryFile())
            ->setOwner($loggedInUser)
            ->setFolder($folder)
            ->setName($uploaded['originalName'])
            ->setUrl($uploaded['url'])
            ->setMimeType($uploaded['mimeType'])
            ->setSize($uploaded['size'])
            ->setExtension($uploaded['extension'])
            ->setFileType(LibraryFileType::detect($uploaded['mimeType'], $uploaded['extension']));

        $this->entityManager->persist($libraryFile);
        $this->entityManager->flush();

        return new JsonResponse([
            'id' => $libraryFile->getId(),
            'folderId' => $libraryFile->getFolder()?->getId(),
            'name' => $libraryFile->getName(),
            'url' => $libraryFile->getUrl(),
            'mimeType' => $libraryFile->getMimeType(),
            'size' => $libraryFile->getSize(),
            'extension' => $libraryFile->getExtension(),
            'fileType' => $libraryFile->getFileType()->value,
        ], Response::HTTP_CREATED);
    }

    #[Route(path: '/v1/library/tree', methods: [Request::METHOD_GET])]
    #[OA\Get(
        summary: 'Retourner tout l’arbre de la library de l’utilisateur connecté.',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Arbre de la library.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'children', type: 'array', items: new OA\Items(type: 'object')),
                    ],
                    type: 'object',
                    example: [
                        'children' => [
                            [
                                'id' => '0195df8e-9f4a-7cf2-9f51-b6ed8b4e5bf8',
                                'name' => 'Documents',
                                'type' => 'folder',
                                'children' => [
                                    [
                                        'id' => '0195df99-f0f8-7b45-8e22-31f0f5acef52',
                                        'name' => 'contrat.pdf',
                                        'type' => 'file',
                                        'fileType' => 'pdf',
                                        'mimeType' => 'application/pdf',
                                        'size' => 32145,
                                        'extension' => 'pdf',
                                        'url' => 'https://localhost/uploads/library/a3f9f63e7d5f4a7fa0c4efed6f12f9dd.pdf',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Authentification requise.'),
        ],
    )]
    public function tree(User $loggedInUser): JsonResponse
    {
        return new JsonResponse($this->libraryTreeService->getTree($loggedInUser));
    }

    /**
     * @throws JsonException
     */
    #[Route(path: '/v1/library/folders/{folder}', methods: [Request::METHOD_PATCH])]
    #[OA\Patch(summary: 'Renommer un dossier et/ou changer son parent.')]
    public function patchFolder(LibraryFolder $folder, Request $request, User $loggedInUser): JsonResponse
    {
        /** @var array<string,mixed> $payload */
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (is_string($payload['name'] ?? null)) {
            $name = trim($payload['name']);
            if ($name === '') {
                throw new HttpException(Response::HTTP_BAD_REQUEST, 'Field "name" must not be empty.');
            }

            $folder->setName($name);
        }

        if (array_key_exists('parentId', $payload)) {
            $newParent = null;
            $parentId = $payload['parentId'];
            if (is_string($parentId) && trim($parentId) !== '') {
                $newParent = $this->folderRepository->findOneByIdAndOwner(trim($parentId), $loggedInUser);
                if (!$newParent instanceof LibraryFolder) {
                    throw new HttpException(Response::HTTP_NOT_FOUND, 'Parent folder not found.');
                }

                if ($newParent->getId() === $folder->getId()) {
                    throw new HttpException(Response::HTTP_BAD_REQUEST, 'A folder cannot be its own parent.');
                }

                if ($this->isDescendantOfFolder($newParent, $folder)) {
                    throw new HttpException(Response::HTTP_BAD_REQUEST, 'Cannot move folder into one of its children.');
                }
            }

            $folder->setParent($newParent);
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'id' => $folder->getId(),
            'name' => $folder->getName(),
            'parentId' => $folder->getParent()?->getId(),
        ]);
    }

    #[Route(path: '/v1/library/folders/{folder}', methods: [Request::METHOD_DELETE])]
    #[OA\Delete(summary: 'Supprimer un dossier (et ses children).')]
    public function deleteFolder(LibraryFolder $folder, User $loggedInUser): JsonResponse
    {
        $this->entityManager->remove($folder);
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @throws JsonException
     */
    #[Route(path: '/v1/library/files/{file}', methods: [Request::METHOD_PATCH])]
    #[OA\Patch(summary: 'Renommer un fichier et/ou changer son dossier parent.')]
    public function patchFile(LibraryFile $file, Request $request, User $loggedInUser): JsonResponse
    {
        /** @var array<string,mixed> $payload */
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (is_string($payload['name'] ?? null)) {
            $name = trim($payload['name']);
            if ($name === '') {
                throw new HttpException(Response::HTTP_BAD_REQUEST, 'Field "name" must not be empty.');
            }

            $file->setName($name);
        }

        if (array_key_exists('folderId', $payload)) {
            $folder = null;
            $folderId = $payload['folderId'];
            if (is_string($folderId) && trim($folderId) !== '') {
                $folder = $this->folderRepository->findOneByIdAndOwner(trim($folderId), $loggedInUser);
                if (!$folder instanceof LibraryFolder) {
                    throw new HttpException(Response::HTTP_NOT_FOUND, 'Folder not found.');
                }
            }

            $file->setFolder($folder);
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'id' => $file->getId(),
            'name' => $file->getName(),
            'folderId' => $file->getFolder()?->getId(),
            'url' => $file->getUrl(),
            'fileType' => $file->getFileType()->value,
        ]);
    }

    #[Route(path: '/v1/library/files/{file}', methods: [Request::METHOD_DELETE])]
    #[OA\Delete(summary: 'Supprimer un fichier.')]
    public function deleteFile(LibraryFile $file, User $loggedInUser): JsonResponse
    {
        $this->deletePhysicalFileIfInsideLibraryUploads($file->getUrl());

        $this->entityManager->remove($file);
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function isDescendantOfFolder(LibraryFolder $candidate, LibraryFolder $target): bool
    {
        $current = $candidate->getParent();
        while ($current instanceof LibraryFolder) {
            if ($current->getId() === $target->getId()) {
                return true;
            }

            $current = $current->getParent();
        }

        return false;
    }

    private function deletePhysicalFileIfInsideLibraryUploads(string $url): void
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || !str_starts_with($path, '/uploads/library/')) {
            return;
        }

        $absolutePath = $this->projectDir . '/public' . $path;
        if ($this->filesystem->exists($absolutePath)) {
            $this->filesystem->remove($absolutePath);
        }
    }
}
