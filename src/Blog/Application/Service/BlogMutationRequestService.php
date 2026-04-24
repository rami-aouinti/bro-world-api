<?php

declare(strict_types=1);

namespace App\Blog\Application\Service;

use App\Blog\Domain\Enum\BlogReactionType;
use App\Media\Application\Service\MediaUploaderService;
use App\Media\Application\Service\MediaUploadValidationPolicy;
use JsonException;
use Random\RandomException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function array_filter;
use function array_map;
use function array_values;
use function is_string;
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
     * @throws JsonException
     */
    public function extractPayload(Request $request): array
    {
        $contentType = (string) $request->headers->get('Content-Type', '');

        if (str_contains($contentType, 'multipart/form-data')) {
            return $request->request->all();
        }

        if (str_contains($contentType, 'application/json')) {
            $content = trim((string) $request->getContent());

            if ($content === '') {
                return [];
            }

            return (array) json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        }

        return $request->request->all();
    }

    public function parseReactionType(string $reactionType): BlogReactionType
    {
        $parsed = BlogReactionType::tryFrom($reactionType);

        if ($parsed instanceof BlogReactionType) {
            return $parsed;
        }

        throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, sprintf('Unsupported reaction type "%s".', $reactionType));
    }

    /**
     * @throws RandomException
     */
    public function resolveUploadedFileUrl(Request $request, string $fallbackUrl): string
    {
        $file = $request->files->get('media');

        if (!$file instanceof UploadedFile) {
            return $fallbackUrl;
        }

        return $this->uploadFiles($request, [$file])[0] ?? $fallbackUrl;
    }

    /**
     * @param Request $request
     * @return list<string>
     * @throws RandomException
     */
    public function resolveUploadedFileUrls(Request $request): array
    {
        $files = $request->files->get('media');

        if ($files === null) {
            return [];
        }

        if ($files instanceof UploadedFile) {
            return $this->uploadFiles($request, [$files]);
        }

        if (!is_array($files)) {
            return [];
        }

        $uploadedFiles = array_values(array_filter(
            $files,
            static fn ($file): bool => $file instanceof UploadedFile
        ));

        if ($uploadedFiles === []) {
            return [];
        }

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
        $rawSharedUrl = $payload['sharedUrl'] ?? $payload['url'] ?? null;

        $content = is_string($rawContent) ? trim($rawContent) : null;
        $sharedUrl = is_string($rawSharedUrl) ? trim($rawSharedUrl) : null;

        return [
            'content' => $content !== '' ? $content : null,
            'sharedUrl' => $sharedUrl !== '' ? $sharedUrl : null,
        ];
    }

    /**
     * @return list<string>
     */
    public function normalizeTagIds(mixed $rawTagIds): array
    {
        if (!is_array($rawTagIds)) {
            return [];
        }

        return array_values(array_filter(array_map(static function ($tagId): ?string {
            if (!is_string($tagId)) {
                return null;
            }

            $normalized = trim($tagId);

            return $normalized !== '' ? $normalized : null;
        }, $rawTagIds)));
    }

    /**
     * @param Request $request
     * @param list<UploadedFile> $files
     *
     * @return list<string>
     * @throws RandomException
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
