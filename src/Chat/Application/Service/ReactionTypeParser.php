<?php

declare(strict_types=1);

namespace App\Chat\Application\Service;

use App\Chat\Domain\Enum\ChatReactionType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ReactionTypeParser
{
    public function parse(mixed $reaction): ChatReactionType
    {
        if (!is_string($reaction) || $reaction === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "reaction" must be a non-empty string.');
        }

        $reactionType = ChatReactionType::tryFrom($reaction);
        if (!$reactionType instanceof ChatReactionType) {
            throw new HttpException(
                JsonResponse::HTTP_BAD_REQUEST,
                sprintf('Invalid reaction "%s". Allowed values: %s.', $reaction, implode(', ', ChatReactionType::VALUES)),
            );
        }

        return $reactionType;
    }
}
