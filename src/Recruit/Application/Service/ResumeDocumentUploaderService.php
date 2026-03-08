<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function bin2hex;
use function random_bytes;
use function str_contains;
use function strtolower;

class ResumeDocumentUploaderService
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly string $projectDir,
    ) {
    }

    public function upload(Request $request, UploadedFile $document, string $relativeDirectory): string
    {
        if (!$document->isValid()) {
            throw new HttpException(Response::HTTP_BAD_REQUEST, 'Invalid uploaded file.');
        }

        if (!$this->isPdfFile($document)) {
            throw new HttpException(Response::HTTP_BAD_REQUEST, 'Uploaded file must be a PDF.');
        }

        $fileName = bin2hex(random_bytes(16)) . '.pdf';
        $targetDirectory = $this->projectDir . '/public' . $relativeDirectory;

        $this->filesystem->mkdir($targetDirectory);
        $document->move($targetDirectory, $fileName);

        return $request->getSchemeAndHttpHost() . $relativeDirectory . '/' . $fileName;
    }

    private function isPdfFile(UploadedFile $document): bool
    {
        $mimeType = strtolower((string) $document->getMimeType());
        $clientMimeType = strtolower((string) $document->getClientMimeType());

        return $mimeType === 'application/pdf'
            || $clientMimeType === 'application/pdf'
            || str_contains(strtolower((string) $document->getClientOriginalName()), '.pdf');
    }
}
