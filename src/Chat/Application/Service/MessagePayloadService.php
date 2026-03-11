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
     * @return array{content: ?string, read: ?bool}
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

        $read = null;
        if (array_key_exists('read', $payload)) {
            if (!is_bool($payload['read'])) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "read" must be a boolean when provided.');
            }

            $read = $payload['read'];
        }

        return [
            'content' => $content,
            'read' => $read,
        ];
    }
}
