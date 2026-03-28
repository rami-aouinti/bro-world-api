<?php

declare(strict_types=1);

namespace App\Library\Application\Service;

use App\Library\Domain\Entity\LibraryFile;
use App\Library\Domain\Entity\LibraryFolder;
use App\Library\Infrastructure\Repository\LibraryFileRepository;
use App\Library\Infrastructure\Repository\LibraryFolderRepository;
use App\User\Domain\Entity\User;

use function is_array;
use function is_string;
use function str_starts_with;
use function substr;

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
                'children' => [],
            ];

            $childrenByParent[$parentId ?? 'root'][] = 'folder:' . $folderId;
        }

        foreach ($files as $file) {
            $folderId = $file->getFolder()?->getId();

            $nodeKey = $folderId ?? 'root';
            $childrenByParent[$nodeKey][] = $this->normalizeFile($file);
        }

        $buildFolder = function (string $folderId) use (&$buildFolder, &$childrenByParent, &$folderNodes): array {
            $node = $folderNodes[$folderId];

            $children = [];
            foreach ($childrenByParent[$folderId] ?? [] as $child) {
                if (is_string($child) && str_starts_with($child, 'folder:')) {
                    $children[] = $buildFolder(substr($child, 7));
                    continue;
                }

                if (is_array($child)) {
                    $children[] = $child;
                }
            }

            $node['children'] = $children;

            return $node;
        };

        $rootNodes = [];
        foreach ($childrenByParent['root'] ?? [] as $child) {
            if (is_string($child) && str_starts_with($child, 'folder:')) {
                $rootNodes[] = $buildFolder(substr($child, 7));
                continue;
            }

            if (is_array($child)) {
                $rootNodes[] = $child;
            }
        }

        return [
            'children' => $rootNodes,
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
