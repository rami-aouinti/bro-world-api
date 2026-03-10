<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\Media\Application\Service\MediaUploaderService;
use App\Media\Application\Service\MediaUploadValidationPolicy;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function str_contains;
use function strtolower;

class ResumeDocumentUploaderService
{
    public function __construct(
        private readonly MediaUploaderService $mediaUploaderService
    ) {
    }

    public function upload(Request $request, UploadedFile $document, string $relativeDirectory): string
    {
        if (!$this->isPdfFile($document)) {
            throw new HttpException(Response::HTTP_BAD_REQUEST, 'Uploaded file must be a PDF.');
        }

        $uploaded = $this->mediaUploaderService->upload(
            $request,
            [$document],
            $relativeDirectory,
            new MediaUploadValidationPolicy(
                maxSizeInBytes: 10 * 1024 * 1024,
                allowedMimeTypes: ['application/pdf'],
                allowedExtensions: ['pdf'],
            ),
        );

        return $uploaded[0]['url'];
    }

    private function isPdfFile(UploadedFile $document): bool
    {
        $mimeType = strtolower((string)$document->getMimeType());
        $clientMimeType = strtolower((string)$document->getClientMimeType());

        return $mimeType === 'application/pdf'
            || $clientMimeType === 'application/pdf'
            || str_contains(strtolower((string)$document->getClientOriginalName()), '.pdf');
    }
}
