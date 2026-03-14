<?php

declare(strict_types=1);

namespace App\Chat\Application\Service;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MessagePayloadService
{
    /**
     * @param array<string, mixed> $payload
     */
    public function extractRequiredContent(array $payload): string
    {
        $content = $payload['content'] ?? null;
        if (!is_string($content) || $content === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "content" is required.');
        }

        return $content;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{content: ?string}
     */
    public function extractPatchFields(array $payload): array
    {
        $content = null;
        if (isset($payload['content'])) {
            if (!is_string($payload['content']) || $payload['content'] === '') {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "content" must be a non-empty string when provided.');
            }

            $content = $payload['content'];
        }

        return [
            'content' => $content,
        ];
    }
}
