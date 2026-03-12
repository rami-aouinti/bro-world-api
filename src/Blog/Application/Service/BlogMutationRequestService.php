<?php

declare(strict_types=1);

namespace App\Blog\Application\Service;

use App\Blog\Domain\Enum\BlogReactionType;
use App\Media\Application\Service\MediaUploaderService;
use App\Media\Application\Service\MediaUploadValidationPolicy;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function array_filter;
use function array_map;
use function is_array;
use function is_string;
use function preg_match;
use function sprintf;
use function trim;

final readonly class BlogMutationRequestService
{
    public function __construct(
        private MediaUploaderService $mediaUploaderService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function extractPayload(Request $request): array
    {
        $payload = (array)json_decode((string)$request->getContent(), true);

        if ($payload === []) {
            $payload = $request->request->all();
        }

        return $payload;
    }

    public function parseReactionType(string $reactionType): BlogReactionType
    {
        $parsed = BlogReactionType::tryFrom($reactionType);

        if ($parsed instanceof BlogReactionType) {
            return $parsed;
        }

        throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, sprintf('Unsupported reaction type "%s".', $reactionType));
    }

    public function resolveUploadedFileUrl(Request $request, string $fallbackUrl): string
    {
        $file = $request->files->get('file');

        if (!$file instanceof UploadedFile) {
            return $fallbackUrl;
        }

        return $this->uploadFiles($request, [$file])[0] ?? $fallbackUrl;
    }

    /**
     * @return list<string>
     */
    public function resolveUploadedFileUrls(Request $request): array
    {
        $files = $request->files->all('files');

        if (!is_array($files) || $files === []) {
            return [];
        }

        $uploadedFiles = array_values(array_filter($files, static fn ($file): bool => $file instanceof UploadedFile));

        if ($uploadedFiles === []) {
            return [];
        }

        /** @var list<UploadedFile> $uploadedFiles */
        return $this->uploadFiles($request, $uploadedFiles);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{content: ?string, sharedUrl: ?string}
     */
    public function normalizePostContent(array $payload): array
    {
        $rawContent = $payload['content'] ?? null;

        if (!is_string($rawContent)) {
            return ['content' => null, 'sharedUrl' => null];
        }

        $content = trim($rawContent);

        if ($content === '') {
            return ['content' => null, 'sharedUrl' => null];
        }

        if ((bool)preg_match('/^https?:\/\//i', $content)) {
            return ['content' => null, 'sharedUrl' => $content];
        }

        return ['content' => $content, 'sharedUrl' => null];
    }

    /**
     * @param list<UploadedFile> $files
     *
     * @return list<string>
     */
    private function uploadFiles(Request $request, array $files): array
    {
        $uploaded = $this->mediaUploaderService->upload(
            $request,
            $files,
            '/uploads/blog',
            new MediaUploadValidationPolicy(
                maxSizeInBytes: 25 * 1024 * 1024,
                allowedMimeTypes: [
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                    'video/mp4',
                    'video/webm',
                    'video/quicktime',
                    'application/pdf',
                ],
                allowedExtensions: ['jpg', 'jpeg', 'png', 'webp', 'mp4', 'webm', 'mov', 'pdf'],
            ),
        );

        return array_values(array_map(static fn (array $item): string => (string)($item['url'] ?? ''), $uploaded));
    }
}
