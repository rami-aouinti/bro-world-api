<?php

declare(strict_types=1);

namespace App\General\Application\Service;

use App\Media\Application\Service\MediaUploaderService;
use App\Media\Application\Service\MediaUploadValidationPolicy;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function exif_imagetype;
use function function_exists;
use function str_starts_with;

class PhotoUploaderService
{
    public function __construct(
        private readonly MediaUploaderService $mediaUploaderService
    ) {
    }

    public function upload(Request $request, UploadedFile $photo, string $relativeDirectory): string
    {
        if (!$this->isImageFile($photo)) {
            throw new HttpException(Response::HTTP_BAD_REQUEST, 'Uploaded file must be an image.');
        }

        $uploaded = $this->mediaUploaderService->upload(
            $request,
            [$photo],
            $relativeDirectory,
            new MediaUploadValidationPolicy(maxSizeInBytes: 10 * 1024 * 1024),
        );

        return $uploaded[0]['url'];
    }

    private function isImageFile(UploadedFile $photo): bool
    {
        if (str_starts_with((string)$photo->getMimeType(), 'image/')) {
            return true;
        }

        if (str_starts_with((string)$photo->getClientMimeType(), 'image/')) {
            return true;
        }

        if (function_exists('exif_imagetype')) {
            return exif_imagetype($photo->getPathname()) !== false;
        }

        return false;
    }
}
