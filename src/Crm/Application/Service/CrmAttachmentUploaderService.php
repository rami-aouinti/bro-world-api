<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

use App\Media\Application\Service\MediaUploaderService;
use App\Media\Application\Service\MediaUploadValidationPolicy;
use Random\RandomException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function array_values;
use function is_array;

final readonly class CrmAttachmentUploaderService
{
    public function __construct(
        private MediaUploaderService $mediaUploaderService
    ) {
    }

    /**
     * @return list<UploadedFile>
     */
    public function extractFiles(Request $request): array
    {
        $files = [];

        $single = $request->files->get('file');
        if ($single instanceof UploadedFile) {
            $files[] = $single;
        }

        $multiple = $request->files->get('files');
        if (is_array($multiple)) {
            foreach ($multiple as $file) {
                if ($file instanceof UploadedFile) {
                    $files[] = $file;
                }
            }
        }

        return array_values($files);
    }

    /**
     * @param list<UploadedFile> $files
     * @return list<array{url:string,originalName:string,mimeType:string,size:int,extension:string}>
     * @throws RandomException
     */
    public function upload(Request $request, array $files, string $relativeDirectory): array
    {
        if ($files === []) {
            throw new HttpException(Response::HTTP_BAD_REQUEST, 'No file found. Expected "file" or "files[]".');
        }

        return $this->mediaUploaderService->upload(
            $request,
            $files,
            $relativeDirectory,
            new MediaUploadValidationPolicy(
                maxSizeInBytes: 15 * 1024 * 1024,
                allowedMimeTypes: [
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                    'application/pdf',
                    'text/plain',
                    'text/markdown',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ],
                allowedExtensions: [
                    'jpg',
                    'jpeg',
                    'png',
                    'webp',
                    'pdf',
                    'txt',
                    'md',
                    'doc',
                    'docx',
                    'xls',
                    'xlsx',
                ],
            ),
        );
    }
}
