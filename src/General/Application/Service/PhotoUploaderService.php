<?php

declare(strict_types=1);

namespace App\General\Application\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function bin2hex;
use function exif_imagetype;
use function function_exists;
use function random_bytes;
use function str_starts_with;

class PhotoUploaderService
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly string $projectDir,
    ) {
    }

    public function upload(Request $request, UploadedFile $photo, string $relativeDirectory): string
    {
        if (!$photo->isValid()) {
            throw new HttpException(Response::HTTP_BAD_REQUEST, 'Invalid uploaded file.');
        }

        if (!$this->isImageFile($photo)) {
            throw new HttpException(Response::HTTP_BAD_REQUEST, 'Uploaded file must be an image.');
        }

        $extension = $photo->guessExtension() ?? 'bin';
        $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
        $targetDirectory = $this->projectDir . '/public' . $relativeDirectory;

        $this->filesystem->mkdir($targetDirectory);
        $photo->move($targetDirectory, $fileName);

        return $request->getSchemeAndHttpHost() . $relativeDirectory . '/' . $fileName;
    }

    private function isImageFile(UploadedFile $photo): bool
    {
        if (str_starts_with((string) $photo->getMimeType(), 'image/')) {
            return true;
        }

        if (str_starts_with((string) $photo->getClientMimeType(), 'image/')) {
            return true;
        }

        if (function_exists('exif_imagetype')) {
            return false !== exif_imagetype($photo->getPathname());
        }

        return false;
    }
}
