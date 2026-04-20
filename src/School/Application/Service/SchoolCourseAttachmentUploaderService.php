<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\Media\Application\Service\MediaUploadValidationPolicy;
use App\Media\Application\Service\MediaUploaderService;
use Random\RandomException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

readonly class SchoolCourseAttachmentUploaderService
{
    public function __construct(
        private MediaUploaderService $mediaUploaderService,
    ) {
    }

    /**
     * @param list<UploadedFile> $files
     * @return list<array{url: string, originalName: string, mimeType: string, size: int, extension: string}>
     * @throws RandomException
     */
    public function upload(Request $request, array $files, string $relativeDirectory): array
    {
        if ($files === []) {
            return [];
        }

        return $this->mediaUploaderService->upload(
            $request,
            $files,
            $relativeDirectory,
            new MediaUploadValidationPolicy(
                maxSizeInBytes: 20 * 1024 * 1024,
                allowedMimeTypes: [
                    'application/pdf',
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-powerpoint',
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                ],
                allowedExtensions: ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'ppt', 'pptx'],
            ),
        );
    }
}
