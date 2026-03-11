<?php

declare(strict_types=1);

namespace App\Chat\Application\Service;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ConversationPayloadService
{
    /**
     * @param array<string, mixed> $payload
     */
    public function extractRequiredUserId(array $payload): string
    {
        $targetUserId = $payload['userId'] ?? null;
        if (!is_string($targetUserId) || $targetUserId === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "userId" is required.');
        }

        return $targetUserId;
    }
}
