<?php

declare(strict_types=1);

namespace App\Media\Application\Service;

use Random\RandomException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function bin2hex;
use function in_array;
use function is_int;
use function random_bytes;
use function rtrim;
use function str_starts_with;
use function strtolower;
use function trim;

readonly class MediaUploaderService
{
    public function __construct(
        private Filesystem $filesystem,
        private string $projectDir,
        private string $publicBaseUrl = '',
    ) {
    }

    /**
     * @param list<UploadedFile> $files
     * @return list<array{url: string, originalName: string, mimeType: string, size: int, extension: string}>
     * @throws RandomException
     */
    public function upload(Request $request, array $files, string $relativeDirectory, MediaUploadValidationPolicy $validationPolicy): array
    {
        $normalizedDirectory = '/' . trim($relativeDirectory, '/');

        foreach ($files as $index => $file) {
            $this->assertFileIsValid($file, $validationPolicy, $index);
        }

        $targetDirectory = $this->projectDir . '/public' . $normalizedDirectory;
        $this->filesystem->mkdir($targetDirectory);

        $uploadedFiles = [];
        foreach ($files as $file) {
            $originalName = $file->getClientOriginalName();
            $mimeType = (string)($file->getMimeType() ?? $file->getClientMimeType() ?? 'application/octet-stream');
            $size = $this->extractSize($file);
            $extension = strtolower($file->guessExtension() ?? $file->getClientOriginalExtension() ?: 'bin');
            $fileName = bin2hex(random_bytes(16)) . '.' . $extension;

            $file->move($targetDirectory, $fileName);

            $uploadedFiles[] = [
                'url' => $this->resolvePublicBaseUrl($request) . $normalizedDirectory . '/' . $fileName,
                'originalName' => $originalName,
                'mimeType' => $mimeType,
                'size' => $size,
                'extension' => $extension,
            ];
        }

        return $uploadedFiles;
    }

    private function assertFileIsValid(UploadedFile $file, MediaUploadValidationPolicy $validationPolicy, int $index): void
    {
        if (!$file->isValid()) {
            throw new HttpException(Response::HTTP_BAD_REQUEST, 'Invalid uploaded file at index ' . $index . '.');
        }

        $maxSize = $validationPolicy->getMaxSizeInBytes();
        $size = $this->extractSize($file);
        if (is_int($maxSize) && $size > $maxSize) {
            throw new HttpException(Response::HTTP_BAD_REQUEST, 'File at index ' . $index . ' exceeds max allowed size.');
        }

        $allowedMimeTypes = $validationPolicy->getAllowedMimeTypes();
        $detectedMimeType = strtolower((string)($file->getMimeType() ?? $file->getClientMimeType() ?? ''));
        if ($allowedMimeTypes !== [] && !in_array($detectedMimeType, $allowedMimeTypes, true)) {
            throw new HttpException(Response::HTTP_BAD_REQUEST, 'File at index ' . $index . ' has unsupported MIME type.');
        }

        $allowedExtensions = $validationPolicy->getAllowedExtensions();
        $extension = strtolower($file->guessExtension() ?? $file->getClientOriginalExtension() ?: '');
        if ($allowedExtensions !== [] && !in_array($extension, $allowedExtensions, true)) {
            throw new HttpException(Response::HTTP_BAD_REQUEST, 'File at index ' . $index . ' has unsupported extension.');
        }
    }

    private function extractSize(UploadedFile $file): int
    {
        $size = $file->getSize();

        return is_int($size) ? $size : 0;
    }

    private function resolvePublicBaseUrl(Request $request): string
    {
        $normalizedPublicBaseUrl = rtrim(trim($this->publicBaseUrl), '/');
        if (
            $normalizedPublicBaseUrl !== ''
            && (str_starts_with($normalizedPublicBaseUrl, 'https://') || str_starts_with($normalizedPublicBaseUrl, 'http://'))
        ) {
            return $normalizedPublicBaseUrl;
        }

        return $request->getSchemeAndHttpHost();
    }
}
