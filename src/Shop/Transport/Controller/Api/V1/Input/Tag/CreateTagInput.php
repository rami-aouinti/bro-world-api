<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Input\Tag;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateTagInput
{
    #[Assert\NotBlank(message: 'label is required.')]
    public string $label = '';

    public ?string $type = null;

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        $input = new self();
        $input->label = trim((string) ($payload['label'] ?? ''));
        $input->type = is_string($payload['type'] ?? null) ? trim((string) $payload['type']) : null;

        return $input;
    }
}
