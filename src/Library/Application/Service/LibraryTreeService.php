<?php

declare(strict_types=1);

namespace App\Library\Application\Service;

use App\Library\Domain\Entity\LibraryFile;
use App\Library\Domain\Entity\LibraryFolder;
use App\Library\Infrastructure\Repository\LibraryFileRepository;
use App\Library\Infrastructure\Repository\LibraryFolderRepository;
use App\User\Domain\Entity\User;

use function array_map;
use function array_values;

readonly class LibraryTreeService
{
    public function __construct(
        private LibraryFolderRepository $folderRepository,
        private LibraryFileRepository $fileRepository,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function getTree(User $owner): array
    {
        $folders = $this->folderRepository->findByOwner($owner);
        $files = $this->fileRepository->findByOwner($owner);

        $childrenByParent = [];
        $folderNodes = [];

        foreach ($folders as $folder) {
            $folderId = $folder->getId();
            $parent = $folder->getParent();
            $parentId = $parent?->getId();

            $folderNodes[$folderId] = [
                'id' => $folderId,
                'name' => $folder->getName(),
                'type' => 'folder',
                'folders' => [],
                'files' => [],
            ];

            $childrenByParent[$parentId ?? 'root'][] = $folderId;
        }

        foreach ($files as $file) {
            $folderId = $file->getFolder()?->getId();
            if ($folderId !== null && isset($folderNodes[$folderId])) {
                $folderNodes[$folderId]['files'][] = $this->normalizeFile($file);
                continue;
            }

            $childrenByParent['root_files'][] = $this->normalizeFile($file);
        }

        $buildFolder = function (string $folderId) use (&$buildFolder, &$childrenByParent, &$folderNodes): array {
            $node = $folderNodes[$folderId];

            $childIds = $childrenByParent[$folderId] ?? [];
            $node['folders'] = array_values(array_map(static fn (string $id): array => $buildFolder($id), $childIds));

            return $node;
        };

        $rootFolderIds = $childrenByParent['root'] ?? [];

        return [
            'folders' => array_values(array_map(fn (string $id): array => $buildFolder($id), $rootFolderIds)),
            'files' => array_values($childrenByParent['root_files'] ?? []),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function normalizeFile(LibraryFile $file): array
    {
        return [
            'id' => $file->getId(),
            'name' => $file->getName(),
            'type' => 'file',
            'fileType' => $file->getFileType()->value,
            'mimeType' => $file->getMimeType(),
            'size' => $file->getSize(),
            'extension' => $file->getExtension(),
            'url' => $file->getUrl(),
        ];
    }
}
