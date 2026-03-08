<?php

declare(strict_types=1);

namespace App\Quiz\Application\Message;

use App\General\Domain\Message\Interfaces\MessageHighInterface;

final readonly class CreateQuizQuestionCommand implements MessageHighInterface
{
    /** @param array<int, array{label: string, correct: bool}> $answers */
    public function __construct(public string $operationId, public string $actorUserId, public string $applicationSlug, public string $title, public string $level, public string $category, public array $answers, public ?array $configuration = null) {}
}
